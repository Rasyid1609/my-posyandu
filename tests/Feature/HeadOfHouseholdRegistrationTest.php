<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Resident;
use App\Services\IdentityDetails;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HeadOfHouseholdRegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_looking_up_households_exposes_address_and_revealed_head_phone(): void
    {
        $this->actingAs($this->createSuperadmin());
        Household::create([
            'head_of_household_name' => 'DEMO Kepala Uji',
            'address' => 'Jl. Uji No. 1',
            'rt' => '12',
            'rw' => '34',
            'is_active' => true,
        ]);
        $head = Resident::factory()->create([
            'full_name' => 'DEMO Kepala Uji',
            'address' => 'Jl. Uji No. 1, Manguharjo, Kota Madiun, Jawa Timur',
            'rt' => '12',
            'rw' => '34',
        ]);
        $head->forceFill(['phone_ciphertext' => '081234567890'])->save();

        $this->get('/masyarakat/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('households.0.head_of_household_name', 'DEMO Kepala Uji')
            ->where('households.0.address', 'Jl. Uji No. 1')
            ->where('households.0.rt', '12')
            ->where('households.0.rw', '34')
            ->where('households.0.head_phone', '081234567890')
            ->etc());
    }

    public function test_registering_resident_as_head_of_household_creates_linked_family(): void
    {
        $this->actingAs($this->createSuperadmin());

        $this->post('/masyarakat', $this->residentPayload([
            'full_name' => 'DEMO Kepala Baru',
            'address' => 'Jl. Kepala No. 7, Manguharjo, Kota Madiun, Jawa Timur',
            'rt' => '21',
            'rw' => '43',
            'is_head_of_household' => true,
        ]))->assertRedirect('/masyarakat');

        $resident = Resident::query()->where('full_name', 'DEMO Kepala Baru')->firstOrFail();
        $household = Household::query()->where('head_of_household_name', 'DEMO Kepala Baru')->firstOrFail();

        $this->assertSame($household->id, $resident->household_id);
        $this->assertNull($resident->guardian_id);
        $this->assertSame('Kepala Keluarga', $resident->family_relationship);
        $this->assertSame('Jl. Kepala No. 7, Manguharjo, Kota Madiun, Jawa Timur', $household->address);
        $this->assertSame('21', $household->rt);
        $this->assertSame('43', $household->rw);
        $this->assertSame(1, $household->revision);
        $this->assertDatabaseCount('households', 1);
    }

    public function test_registering_head_with_family_card_stores_it_on_the_new_household(): void
    {
        $this->actingAs($this->createSuperadmin());

        $this->post('/masyarakat', $this->residentPayload([
            'full_name' => 'DEMO Kepala KK',
            'family_card_number' => '3577014508850001',
            'is_head_of_household' => true,
        ]))->assertRedirect('/masyarakat');

        $household = Household::query()
            ->where('head_of_household_name', 'DEMO Kepala KK')
            ->firstOrFail();

        $this->assertSame('3577014508850001', $household->family_card_ciphertext);
        $this->assertSame(
            IdentityDetails::lookup('family_card', '3577014508850001'),
            $household->family_card_lookup_hash,
        );
        $this->assertNull(Resident::where('full_name', 'DEMO Kepala KK')->firstOrFail()->guardian_id);
    }

    public function test_duplicate_family_card_on_head_registration_is_rejected(): void
    {
        $this->actingAs($this->createSuperadmin());
        Household::create(['head_of_household_name' => 'DEMO Keluarga Lain', 'is_active' => true])
            ->forceFill([
                'family_card_ciphertext' => '3577014508850001',
                'family_card_lookup_hash' => IdentityDetails::lookup('family_card', '3577014508850001'),
            ])
            ->save();

        $this->post('/masyarakat', $this->residentPayload([
            'full_name' => 'DEMO Kepala Duplikat',
            'family_card_number' => '3577014508850001',
            'is_head_of_household' => true,
        ]))->assertSessionHasErrors(['family_card_number']);

        $this->assertDatabaseMissing('residents', ['full_name' => 'DEMO Kepala Duplikat']);
        $this->assertDatabaseMissing('households', ['head_of_household_name' => 'DEMO Kepala Duplikat']);
    }

    public function test_head_flag_without_consent_keeps_resident_unlinked(): void
    {
        $this->actingAs($this->createSuperadmin());

        $this->post('/masyarakat', $this->residentPayload())->assertRedirect('/masyarakat');

        $this->assertDatabaseCount('households', 0);
        $this->assertDatabaseCount('residents', 1);
    }

    public function test_head_flag_is_ignored_when_updating_existing_resident(): void
    {
        $this->actingAs($this->createSuperadmin());
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga Ada', 'is_active' => true]);
        $member = Resident::factory()->create([
            'full_name' => 'DEMO Anggota Ada',
            'household_id' => $family->id,
            'revision' => 1,
        ]);

        $this->put(route('residents.update', $member), $this->residentPayload([
            'full_name' => $member->full_name,
            'revision' => $member->revision,
            'household_id' => $family->id,
            'is_head_of_household' => true,
        ]))->assertRedirect('/masyarakat');

        $member->refresh();
        $this->assertSame($family->id, $member->household_id);
        $this->assertDatabaseCount('households', 1);
    }
}
