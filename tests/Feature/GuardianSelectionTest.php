<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuardianSelectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guardian_choices_include_family_membership_and_exclude_self_unassigned_and_deleted_residents(): void
    {
        $this->actingAs($this->createSuperadmin());
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga', 'is_active' => true]);
        $guardian = Resident::factory()->create(['full_name' => 'DEMO A Wali', 'household_id' => $family->id]);
        Resident::factory()->create(['full_name' => 'DEMO B Anggota', 'household_id' => $family->id]);
        Resident::factory()->create(['full_name' => 'DEMO Tanpa Keluarga']);
        Resident::factory()
            ->create(['full_name' => 'DEMO Dihapus', 'household_id' => $family->id])
            ->delete();

        $this->get('/masyarakat/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('guardians', 2)->where('guardians.0.id', $guardian->id)->where('guardians.0.household_id', $family->id)
            ->missing('guardians.0.national_id_ciphertext')->missing('guardians.0.birth_date'));
        $this->get(route('residents.edit', $guardian))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('guardians', 1)->where('guardians.0.full_name', 'DEMO B Anggota'));
    }

    public function test_guardian_from_selected_family_is_saved_and_can_be_cleared(): void
    {
        $this->actingAs($this->createSuperadmin());
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga', 'is_active' => true]);
        $guardian = Resident::factory()->create(['household_id' => $family->id]);

        $this->post(
            '/masyarakat',
            $this->residentPayload(['household_id' => $family->id, 'guardian_id' => $guardian->id])
        )
            ->assertSessionHasNoErrors()
            ->assertRedirectToRoute('residents.index');

        $child = Resident::where('full_name', 'DEMO Warga Uji')->sole();
        $this->assertSame($family->id, $child->household_id);
        $this->assertSame($guardian->id, $child->guardian_id);
        $this->put(route('residents.update', $child), $this->residentPayload([
            'revision' => $child->revision,
            'household_id' => $family->id,
            'guardian_id' => null,
        ]))->assertSessionHasNoErrors();
        $this->assertNull($child->fresh()->guardian_id);
    }

    public static function invalidGuardians(): array
    {
        return [
            'different family' => ['different'],
            'no family selected' => ['no_family'],
            'unassigned guardian' => ['unassigned'],
            'deleted guardian' => ['deleted'],
        ];
    }

    #[DataProvider('invalidGuardians')]
    public function test_guardian_outside_selected_family_is_rejected(string $case): void
    {
        $this->actingAs($this->createSuperadmin());
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga A', 'is_active' => true]);
        $other = Household::create(['head_of_household_name' => 'DEMO Keluarga B', 'is_active' => true]);
        $guardian = Resident::factory()->create(['household_id' => match ($case) {
            'different' => $other->id, 'unassigned' => null, default => $family->id
        }]);
        if ($case === 'deleted') {
            $guardian->delete();
        }

        $this->post('/masyarakat', $this->residentPayload([
            'household_id' => $case === 'no_family' ? null : $family->id,
            'guardian_id' => $guardian->id,
        ]))
            ->assertSessionHasErrors(['guardian_id' => 'Pilih orang tua / wali dari keluarga yang dipilih.']);

        $this->assertDatabaseCount('residents', 1);
        $this->assertDatabaseMissing('residents', ['full_name' => 'DEMO Warga Uji']);
    }

    public function test_changing_family_without_clearing_existing_guardian_is_rejected(): void
    {
        $this->actingAs($this->createSuperadmin());
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga A', 'is_active' => true]);
        $other = Household::create(['head_of_household_name' => 'DEMO Keluarga B', 'is_active' => true]);
        $guardian = Resident::factory()->create(['household_id' => $family->id]);
        $child = Resident::factory()->create(['household_id' => $family->id, 'guardian_id' => $guardian->id]);

        $this->put(route('residents.update', $child), $this->residentPayload([
            'full_name' => $child->full_name,
            'revision' => $child->revision,
            'household_id' => $other->id,
        ]))
            ->assertSessionHasErrors(['guardian_id' => 'Pilih orang tua / wali dari keluarga yang dipilih.']);

        $this->assertSame($family->id, $child->fresh()->household_id);
        $this->assertSame($guardian->id, $child->fresh()->guardian_id);
    }

    public function test_resident_cannot_choose_self_as_guardian(): void
    {
        $this->actingAs($this->createSuperadmin());
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga', 'is_active' => true]);
        $child = Resident::factory()->create(['household_id' => $family->id]);

        $this->put(route('residents.update', $child), $this->residentPayload([
            'full_name' => $child->full_name,
            'revision' => $child->revision,
            'household_id' => $family->id,
            'guardian_id' => $child->id,
        ]))
            ->assertSessionHasErrors(['guardian_id' => 'Warga tidak dapat dipilih sebagai wali dirinya sendiri.']);

        $this->assertNull($child->fresh()->guardian_id);
    }

    public function test_circular_guardian_relationship_is_still_rejected_within_one_family(): void
    {
        $this->actingAs($this->createSuperadmin());
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga', 'is_active' => true]);
        $child = Resident::factory()->create(['household_id' => $family->id]);
        $guardian = Resident::factory()->create(['household_id' => $family->id, 'guardian_id' => $child->id]);

        $this->put(route('residents.update', $child), $this->residentPayload([
            'full_name' => $child->full_name,
            'revision' => $child->revision,
            'household_id' => $family->id,
            'guardian_id' => $guardian->id,
        ]))
            ->assertSessionHasErrors(['guardian_id' => 'Relasi wali tidak boleh membentuk lingkaran.']);

        $this->assertNull($child->fresh()->guardian_id);
    }
}
