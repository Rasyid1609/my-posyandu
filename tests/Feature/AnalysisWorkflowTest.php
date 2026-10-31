<?php

namespace Tests\Feature;

use App\Jobs\GenerateAnalysis;
use App\Models\AnalysisRun;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Services\AggregateAnalysis;
use App\Services\ResponsesClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AnalysisWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_no_key_keeps_core_and_local_template_working_without_external_requests(): void
    {
        Http::preventStrayRequests();
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $this->post('/rekapitulasi/'.$period->id.'/analisis', ['use_ai' => true])->assertSessionHasErrors('analysis');
        $this->post('/rekapitulasi/'.$period->id.'/analisis', ['use_ai' => false])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->post('/rekapitulasi/'.$period->id.'/analisis', ['use_ai' => false])->assertRedirect();
        $this->assertDatabaseCount('analysis_runs', 1);
        $this->assertSame('local_template', AnalysisRun::sole()->provider);
        $this->get('/rekapitulasi?period_id='.$period->id)->assertOk();
        Http::assertNothingSent();
    }

    public function test_small_cells_block_outbound_and_permission_is_required(): void
    {
        Http::preventStrayRequests();
        $this->actingAs($this->createStaff('bendahara'));
        $period = ReportingPeriod::factory()->create();
        $this->post('/rekapitulasi/'.$period->id.'/analisis', ['use_ai' => false])->assertForbidden();
        $this->actingAs($this->createSuperadmin());
        config([
            'analysis.enabled' => true,
            'analysis.processing_approved' => true,
            'analysis.api_key' => 'test-only',
            'analysis.model' => 'configured-test-model',
        ]);
        $this->post('/rekapitulasi/'.$period->id.'/analisis', ['use_ai' => true])->assertSessionHasErrors('analysis');
        $this->assertDatabaseCount('analysis_runs', 0);
        Http::assertNothingSent();
    }

    public function test_queue_sends_only_allowlisted_evidence_and_validates_provider_references(): void
    {
        Queue::fake([GenerateAnalysis::class]);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($this->validResult())]]]],
        ])]);
        $this->actingAs($this->createSuperadmin());
        $period = $this->largePeriod();
        config([
            'analysis.enabled' => true,
            'analysis.processing_approved' => true,
            'analysis.api_key' => 'test-only',
            'analysis.model' => 'configured-test-model',
        ]);
        $this->post('/rekapitulasi/'.$period->id.'/analisis', ['use_ai' => true])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $run = AnalysisRun::sole();
        Queue::assertPushed(GenerateAnalysis::class, fn ($job) => $job->runId === $run->id);
        (new GenerateAnalysis($run->id))->handle(app(AggregateAnalysis::class), app(ResponsesClient::class));
        $this->assertSame('succeeded', $run->fresh()->status);
        Http::assertSent(fn ($request) => $request['store'] === false && ! str_contains(json_encode($request['input']), 'RAHASIA') && ! str_contains(json_encode($request['input']), 'birth_date') && $request['text']['format']['strict'] === true);
        $this->patch(
            '/rekapitulasi/'.$period->id.'/analisis/'.$run->id,
            ['review_status' => 'approved', 'review_note' => 'Bukti sudah diperiksa']
        )
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $run->fresh()->review_status);
    }

    public function test_unfounded_evidence_and_hallucinated_digits_are_rejected(): void
    {
        $service = app(AggregateAnalysis::class);
        foreach (['foreign_evidence', 'unsupported_number'] as $case) {
            $result = $this->validResult();
            if ($case === 'foreign_evidence') {
                $result['findings'][0]['evidence_ids'] = ['national_id_number'];
            } else {
                $result['summary'] = 'Semua 999 warga sehat';
            }
            try {
                $service->validateResult($result, [['id' => 'targets', 'value' => 10]]);
                $this->fail('Unsupported provider result accepted');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_source_changes_make_analysis_stale_and_prevent_approval(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $this->post('/rekapitulasi/'.$period->id.'/analisis', ['use_ai' => false])->assertRedirect();
        $run = AnalysisRun::sole();
        $this->post('/sasaran/'.$period->id.'/generate', ['revision' => 1])->assertRedirect();
        $this->patch(
            '/rekapitulasi/'.$period->id.'/analisis/'.$run->id,
            ['review_status' => 'approved', 'review_note' => 'Percobaan review versi lama']
        )->assertSessionHasErrors('analysis');
        $this->assertSame('pending', $run->fresh()->review_status);
    }

    public function test_provider_refusal_is_failure_without_fabricated_result(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['content' => [['type' => 'refusal', 'refusal' => 'No']]]],
        ])]);
        $user = $this->createSuperadmin();
        $period = ReportingPeriod::factory()->create();
        config([
            'analysis.enabled' => true,
            'analysis.processing_approved' => true,
            'analysis.api_key' => 'test-only',
            'analysis.model' => 'configured-test-model',
        ]);
        $run = AnalysisRun::create([
            'reporting_period_id' => $period->id,
            'requested_by' => $user->id,
            'deduplication_key' => str_repeat('a', 64),
            'source_revision' => 1,
            'source_hash' => str_repeat('b', 64),
            'provider' => 'openai',
            'model' => 'configured-test-model',
            'prompt_version' => 'v1',
            'schema_version' => 'v1',
            'evidence' => [],
        ]);
        (new GenerateAnalysis($run->id))->handle(app(AggregateAnalysis::class), app(ResponsesClient::class));
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertNull($run->fresh()->result);
        Http::assertSentCount(1);
    }

    private function validResult(): array
    {
        return [
            'summary' => 'Ringkasan data kegiatan.',
            'findings' => [[
                'title' => 'Sasaran',
                'description' => 'Jumlah sasaran tercatat pada bukti.',
                'evidence_ids' => ['targets'],
                'kind' => 'fact',
            ]],

            'data_quality_issues' => [],
            'limitations' => ['Bukan diagnosis.'],
            'suggested_actions' => [],
            'conclusion' => 'Lakukan review administratif.',
        ];
    }

    private function largePeriod(): ReportingPeriod
    {
        $period = ReportingPeriod::factory()->create();
        foreach (Resident::factory()->count(10)->create(['full_name' => 'RAHASIA sintetis']) as $resident) {
            $target = $period->targets()->create([
                'resident_id' => $resident->id,
                'category_code' => 'adult',
                'attendance_status' => 'present',
            ]);
            $visit = $period->visits()->create([
                'target_membership_id' => $target->id,
                'resident_id' => $resident->id,
                'visited_at' => '2026-01-15 02:00:00',
                'record_status' => 'finalized',
            ]);
            $visit->examination()->create([
                'category_code' => 'adult',
                'weight_state' => 'measured',
                'weight_kg' => 50,
                'glucose_state' => 'measured',
                'glucose_mg_dl' => 90,
            ]);
        }

        return $period;
    }
}
