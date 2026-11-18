<?php

namespace Tests\Feature;

use App\Models\Resident;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ResidentReasonTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reason_is_optional_for_new_resident_and_required_when_editing(): void
    {
        $this->actingAs($this->createSuperadmin());

        $this->post('/masyarakat', $this->residentPayload(['reason' => null]))
            ->assertSessionHasNoErrors();

        $resident = Resident::sole();
        $this->assertDatabaseHas('resident_status_histories', [
            'resident_id' => $resident->id,
            'status' => 'active',
            'reason' => 'Pendaftaran warga baru',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'resident.created',
            'reason' => null,
        ]);

        $this->put('/masyarakat/'.$resident->id, $this->residentPayload([
            'revision' => $resident->revision,
            'full_name' => $resident->full_name,
            'reason' => null,
        ]))->assertSessionHasErrors(['reason']);
    }
}
