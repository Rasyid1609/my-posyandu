<?php

namespace Tests\Feature;

use App\Models\ReportingPeriod;
use App\Models\Resident;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ActivityWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sessions_are_period_bound_and_service_cannot_reference_another_period(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $this->post(
            '/sasaran/'.$period->id.'/kegiatan',
            ['revision' => 1, 'name' => 'DEMO Bulanan', 'scheduled_on' => '2026-02-01']
        )->assertSessionHasErrors('scheduled_on');
        $this->post(
            '/sasaran/'.$period->id.'/kegiatan',
            ['revision' => 1, 'name' => 'DEMO Bulanan', 'scheduled_on' => '2026-01-15']
        )
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $session = $period->activities()->sole();
        $other = ReportingPeriod::factory()->create(['month' => 2, 'reference_date' => '2026-02-28']);
        $target = $other->targets()->create(['resident_id' => Resident::factory()->create()->id, 'category_code' => 'adult']);
        $this->post(
            '/sasaran/'.$other->id.'/anggota/'.$target->id.'/kunjungan',
            $this->visitPayload(1, ['visited_at' => '2026-02-15T09:00', 'activity_session_id' => $session->id])
        )->assertSessionHasErrors('activity_session_id');
        $period->update(['status' => 'locked']);
        $this->post('/sasaran/'.$period->id.'/kegiatan', [
            'revision' => $period->fresh()->revision,
            'name' => 'DEMO Terkunci',
            'scheduled_on' => '2026-01-16',
        ])->assertSessionHasErrors('period');
        $this->assertDatabaseCount('activity_sessions', 1);
    }
}
