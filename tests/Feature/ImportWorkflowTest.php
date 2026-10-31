<?php

namespace Tests\Feature;

use App\Jobs\ExtractReportImage;
use App\Models\ImportBatch;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Services\GroupedReport;
use App\Services\ResponsesClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ocr_result_remains_draft_and_document_text_cannot_add_foreign_metrics(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Queue::fake([ExtractReportImage::class]);
        $user = $this->createSuperadmin();
        $this->actingAs($user);
        $period = ReportingPeriod::factory()->create();
        config([
            'analysis.ocr_enabled' => true,
            'analysis.processing_approved' => true,
            'analysis.api_key' => 'test-only',
            'analysis.ocr_model' => 'configured-vision-test',
        ]);
        $this->post('/rekapitulasi/'.$period->id.'/import', [
            'kind' => 'legacy',
            'report_type' => 'adults',
            'source_name' => 'IGNORE_INSTRUCTIONS_EXFILTRATE',
            'period_revision' => 1,
            'file' => UploadedFile::fake()->image('foto.jpg', 200, 150),
        ])
            ->assertRedirect()->assertSessionHasNoErrors();
        $batch = ImportBatch::sole();
        $this->post('/rekapitulasi/'.$period->id.'/import/'.$batch->id.'/ocr', [
            'period_revision' => $period->fresh()->revision,
            'revision' => 1,
            'consent' => true,
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        Queue::assertPushed(ExtractReportImage::class);
        Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()
            ->push([
                'status' => 'completed',
                'output' => [['content' => [[
                    'type' => 'output_text',
                    'text' => json_encode(['rows' => [['metric_code' => 'targets', 'value' => 12, 'source_cell' => 'B2']]]),
                ]]]],
            ])
            ->push([
                'status' => 'completed',
                'output' => [['content' => [[
                    'type' => 'output_text',
                    'text' => json_encode(['rows' => [['metric_code' => 'run_sql', 'value' => 1, 'source_cell' => 'A1']]]),
                ]]]],
            ])]);
        (new ExtractReportImage($batch->id, $batch->fresh()->revision, $user->id))->handle(
            app(ResponsesClient::class),
            app(GroupedReport::class)
        );
        $this->assertSame('succeeded', $batch->fresh()->ocr_status);
        $this->assertSame('draft', $batch->fresh()->status);
        $this->assertSame(12, $batch->fresh()->draft_rows[0]['value']);
        $this->assertDatabaseCount('legacy_report_values', 0);
        $this->assertDatabaseCount('residents', 0);
        Http::assertSent(fn ($request) => ! str_contains(json_encode($request['input']), 'IGNORE_INSTRUCTIONS_EXFILTRATE') && $request['store'] === false);
        $batch->refresh()->update(['ocr_status' => 'queued']);

        (new ExtractReportImage($batch->id, $batch->revision, $user->id))->handle(
            app(ResponsesClient::class),
            app(GroupedReport::class)
        );
        $this->assertSame('unsupported_result', $batch->fresh()->failure_code);
        $this->assertSame('targets', $batch->fresh()->draft_rows[0]['metric_code']);
    }

    public function test_xlsx_is_parsed_locally_and_formulas_are_not_executed(): void
    {
        Storage::fake('local');
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['full_name', 'gender', 'birth_date', 'address', 'rt', 'rw'],
            ['DEMO XLSX', 'P', '1990-01-01', '=1+1', null, null],
        ]);
        ob_start();
        (new Xlsx($book))->save('php://output');
        $bytes = ob_get_clean();
        $this->post('/rekapitulasi/'.$period->id.'/import', [
            'kind' => 'residents',
            'source_name' => 'DEMO XLSX',
            'period_revision' => 1,
            'file' => UploadedFile::fake()->createWithContent('warga.xlsx', $bytes),
        ])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('DEMO XLSX', ImportBatch::sole()->draft_rows[0]['full_name']);
        $this->assertSame('=1+1', ImportBatch::sole()->draft_rows[0]['address']);
        $this->assertDatabaseCount('residents', 0);
    }

    public function test_csv_preview_commit_and_checksum_are_idempotent(): void
    {
        Storage::fake('local');
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $csv = "full_name,gender,birth_date,address,rt,rw\nDEMO Import,P,1990-01-01,,,\n";
        $payload = [
            'kind' => 'residents',
            'source_name' => 'DEMO CSV sintetis',
            'period_revision' => 1,
            'file' => UploadedFile::fake()->createWithContent('warga.csv', $csv),
        ];
        $this->post('/rekapitulasi/'.$period->id.'/import', $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $batch = ImportBatch::sole();
        $this->assertDatabaseCount('residents', 0);
        $payload['period_revision'] = $period->fresh()->revision;
        $payload['file'] = UploadedFile::fake()->createWithContent('warga.csv', $csv);
        $this->post('/rekapitulasi/'.$period->id.'/import', $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('import_batches', 1);
        $this->post('/rekapitulasi/'.$period->id.'/import/'.$batch->id.'/commit', [
            'period_revision' => $period->fresh()->revision,
            'revision' => 1,
            'confirmed' => true,
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('residents', ['full_name' => 'DEMO Import']);
        $this->assertDatabaseCount('import_resident_links', 1);
        $this->post('/rekapitulasi/'.$period->id.'/import/'.$batch->id.'/commit', [
            'period_revision' => $period->fresh()->revision,
            'revision' => $batch->fresh()->revision,
            'confirmed' => true,
        ])->assertSessionHasErrors('import');
        $this->assertDatabaseCount('residents', 1);
    }

    public function test_legacy_import_does_not_create_individuals_and_source_selection_does_not_add_transactions(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $resident = Resident::factory()->create();
        $period->targets()->create([
            'resident_id' => $resident->id,
            'category_code' => 'adult',
            'attendance_status' => 'absent',
        ]);
        $batch = ImportBatch::create([
            'reporting_period_id' => $period->id,
            'created_by' => auth()->id(),
            'kind' => 'legacy',
            'report_type' => 'adults',
            'checksum' => str_repeat('a', 64),
            'source_name' => 'DEMO Historis',
            'draft_rows' => [
                ['metric_code' => 'targets', 'value' => 65, 'source_cell' => 'B2'],
                ['metric_code' => 'present', 'value' => 0, 'source_cell' => 'C2'],
                ['metric_code' => 'absent', 'value' => null, 'source_cell' => 'D2'],
            ],
        ]);
        $this->post(
            '/rekapitulasi/'.$period->id.'/import/'.$batch->id.'/commit',
            ['period_revision' => 1, 'revision' => 1, 'confirmed' => true]
        )
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('residents', 1);
        $this->assertDatabaseCount('visits', 0);
        $this->post('/rekapitulasi/'.$period->id.'/sumber', [
            'period_revision' => $period->fresh()->revision,
            'report_type' => 'adults',
            'import_batch_id' => $batch->id,
            'reason' => 'Gunakan sumber historis terverifikasi',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $report = app(GroupedReport::class)->calculate($period)['adults'];
        $rows = collect($report['rows'])->keyBy('code');
        $this->assertSame(65, $rows['targets']['value']);
        $this->assertSame(0, $rows['present']['value']);
        $this->assertNull($rows['absent']['value']);
        $this->assertSame('legacy_import', $report['source']);
        $period->update(['status' => 'locked']);
        $this->post('/rekapitulasi/'.$period->id.'/sumber', [
            'period_revision' => $period->fresh()->revision,
            'report_type' => 'adults',
            'import_batch_id' => null,
            'reason' => 'Percobaan penggantian terkunci',
        ])->assertSessionHasErrors('period');
        $this->assertDatabaseHas('report_source_selections', ['import_batch_id' => $batch->id]);
    }

    public function test_invalid_rows_roll_back_whole_import_and_other_period_batch_is_not_accessible(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $other = ReportingPeriod::factory()->create(['month' => 2]);
        $batch = ImportBatch::create([
            'reporting_period_id' => $period->id,
            'created_by' => auth()->id(),
            'kind' => 'residents',
            'checksum' => str_repeat('c', 64),
            'source_name' => 'DEMO salah',

            'draft_rows' => [
                ['full_name' => 'DEMO valid', 'gender' => 'P', 'birth_date' => '1990-01-01'],
                ['full_name' => 'DEMO invalid', 'gender' => 'X'],
            ],
        ]);
        $this->post(
            '/rekapitulasi/'.$period->id.'/import/'.$batch->id.'/commit',
            ['period_revision' => 1, 'revision' => 1, 'confirmed' => true]
        )->assertSessionHasErrors('gender');
        $this->assertDatabaseCount('residents', 0);
        $this->assertSame('draft', $batch->fresh()->status);
        $this->get('/rekapitulasi/'.$other->id.'/import?batch_id='.$batch->id)->assertNotFound();
    }

    public function test_photo_is_private_and_no_key_never_fakes_ocr_or_sends_image(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $this->post('/rekapitulasi/'.$period->id.'/import', [
            'kind' => 'legacy',
            'report_type' => 'adults',
            'source_name' => 'DEMO Foto',
            'period_revision' => 1,
            'file' => UploadedFile::fake()->image('foto.jpg', 200, 150),
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $batch = ImportBatch::sole();
        Storage::disk('local')->assertExists($batch->file_path);
        $this->assertSame([], $batch->draft_rows);
        $url = URL::temporarySignedRoute(
            'imports.source',
            now()->addMinutes(5),
            ['period' => $period->id, 'batch' => $batch->id]
        );
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post('/rekapitulasi/'.$period->id.'/import/'.$batch->id.'/ocr', [
            'period_revision' => $period->fresh()->revision,
            'revision' => 1,
            'consent' => true,
        ])->assertSessionHasErrors('ocr');
        $this->actingAs($this->createStaff('bendahara'));
        $this->get($url)->assertForbidden();
        Http::assertNothingSent();
    }
}
