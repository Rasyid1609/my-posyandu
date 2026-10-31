<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use App\Services\IdentityDetails;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdentityDetailsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_optional_identity_is_encrypted_and_only_revealed_to_authorized_accounts(): void
    {
        $chair = $this->createSuperadmin();
        $this->actingAs($chair);
        $nik = '0123456789012345';
        $phone = '+62 812-3456-7890';
        $this->post(
            '/masyarakat',
            $this->residentPayload(['national_id_number' => $nik, 'phone_number' => $phone])
        )->assertSessionHasNoErrors();
        $resident = Resident::sole();
        $raw = DB::table('residents')->find($resident->id);
        $this->assertSame($nik, Crypt::decryptString($raw->national_id_ciphertext));
        $this->assertSame($phone, Crypt::decryptString($raw->phone_ciphertext));
        $this->assertNotSame($nik, $raw->national_id_ciphertext);
        $this->assertSame(IdentityDetails::lookup('national_id', $nik), $raw->national_id_lookup_hash);
        $this->assertNull($raw->national_id_number);
        $this->assertNull($raw->phone_number);
        $this->assertArrayNotHasKey('national_id_ciphertext', $resident->toArray());
        $this->assertArrayNotHasKey('phone_ciphertext', $resident->toArray());
        $this->get('/masyarakat/'.$resident->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->where('identityDetails.national_id_number', $nik)->where('identityDetails.phone_number', $phone)
            ->missing('resident.national_id_number')->missing('resident.national_id_lookup_hash'));
        $this->assertTrue(AuditLog::where('action', 'resident.identity_revealed')->exists());

        $staff = $this->createStaff('admin');
        $this->actingAs($staff);
        $this->get('/masyarakat/'.$resident->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->where('identityDetails.national_id_number', '************2345')
            ->where('identityDetails.phone_number', str_repeat('*', strlen($phone) - 4).'7890'));
        $this->get('/masyarakat')->assertInertia(fn (Assert $page) => $page
            ->missing('residents.data.0.national_id_ciphertext')->missing('identityDetails'));
        $this->assertStringNotContainsString($nik, AuditLog::all()->toJson());
        $staff->givePermissionTo('identity.reveal');
        $this->get('/masyarakat/'.$resident->id.'/edit')->assertInertia(fn (Assert $page) => $page->where('identityDetails.national_id_number', $nik));
    }

    public function test_empty_identity_is_allowed_and_omitted_updates_preserve_existing_values(): void
    {
        $this->actingAs($this->createSuperadmin());
        $this->post(
            '/masyarakat',
            $this->residentPayload(['full_name' => 'DEMO Bayi tanpa NIK', 'birth_date' => now()->toDateString()])
        )->assertSessionHasNoErrors();
        $baby = Resident::sole();
        $this->assertNull($baby->national_id_ciphertext);
        $this->assertNull($baby->national_id_lookup_hash);
        $this->put('/masyarakat/'.$baby->id, $this->residentPayload([
            'revision' => 1,
            'full_name' => $baby->full_name,
            'national_id_number' => '0123456789012345',
            'phone_number' => '081234567890',
        ]))->assertSessionHasNoErrors();
        $this->put(
            '/masyarakat/'.$baby->id,
            $this->residentPayload(['revision' => 2, 'full_name' => $baby->full_name])
        )->assertSessionHasNoErrors();
        $this->assertSame('0123456789012345', $baby->fresh()->national_id_ciphertext);
        $this->assertSame('081234567890', $baby->fresh()->phone_ciphertext);
        $this->put('/masyarakat/'.$baby->id, $this->residentPayload([
            'revision' => 3,
            'full_name' => $baby->full_name,
            'national_id_number' => null,
            'phone_number' => null,
        ]))->assertSessionHasNoErrors();
        $this->assertNull($baby->fresh()->national_id_ciphertext);
        $this->assertNull($baby->fresh()->national_id_lookup_hash);
        $this->assertNull($baby->fresh()->phone_ciphertext);
    }

    public function test_invalid_values_are_rejected_without_flashing_sensitive_input_and_archived_nik_cannot_be_reused(): void
    {
        $this->actingAs($this->createSuperadmin());
        $this->post(
            '/masyarakat',
            $this->residentPayload(['national_id_number' => '123456789012345', 'phone_number' => 'bukan-telepon'])
        )->assertSessionHasErrors(['national_id_number', 'phone_number']);
        $this->assertNull(session()->getOldInput('national_id_number'));
        $this->assertNull(session()->getOldInput('phone_number'));
        $this->assertDatabaseCount('residents', 0);
        $this->post('/masyarakat', $this->residentPayload(['national_id_number' => '0123456789012345']))->assertSessionHasNoErrors();
        Resident::sole()->delete();
        $this->post(
            '/masyarakat',
            $this->residentPayload(['full_name' => 'DEMO Nama berbeda', 'national_id_number' => '0123456789012345'])
        )->assertSessionHasErrors('national_id_number');
        $this->assertDatabaseCount('residents', 1);
    }

    public function test_kk_is_unique_per_family_encrypted_masked_and_shared_through_membership(): void
    {
        $chair = $this->createSuperadmin();
        $this->actingAs($chair);
        $payload = [
            'head_of_household_name' => 'DEMO Kepala Keluarga',
            'is_active' => true,
            'family_card_number' => '0123456789098765',
        ];
        $this->post('/masyarakat/keluarga', $payload)->assertSessionHasNoErrors();
        $household = Household::sole();
        $this->assertSame(
            $payload['family_card_number'],
            Crypt::decryptString(DB::table('households')->value('family_card_ciphertext'))
        );
        $this->assertNull(DB::table('households')->value('family_card_number'));
        $this->post('/masyarakat/keluarga', [...$payload, 'head_of_household_name' => 'DEMO Duplikat'])->assertSessionHasErrors('family_card_number');
        $this->post('/masyarakat/keluarga', [...$payload, 'family_card_number' => 'angka tidak valid'])->assertSessionHasErrors('family_card_number');
        $this->assertNull(session()->getOldInput('family_card_number'));
        $this->put('/masyarakat/keluarga/'.$household->id, [
            'revision' => 1,
            'head_of_household_name' => 'DEMO Kepala Diperbarui',
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame($payload['family_card_number'], $household->fresh()->family_card_ciphertext);
        $resident = Resident::factory()->create(['household_id' => $household->id]);
        $this->get('/masyarakat/'.$resident->id.'/edit')->assertInertia(fn (Assert $page) => $page->where('identityDetails.family_card_number', $payload['family_card_number']));
        $this->actingAs($this->createStaff('sekretaris'));
        $this->get('/masyarakat/keluarga/daftar')->assertInertia(fn (Assert $page) => $page
            ->where('households.data.0.family_card_display', '************8765')
            ->missing('households.data.0.family_card_number')->missing('households.data.0.family_card_ciphertext'));
        $this->put('/masyarakat/keluarga/'.$household->id, [
            'revision' => 2,
            'head_of_household_name' => 'DEMO Kepala Diperbarui',
            'is_active' => true,
            'family_card_number' => null,
        ])->assertSessionHasNoErrors();
        $this->assertNull($household->fresh()->family_card_ciphertext);
        $this->assertNull($household->fresh()->family_card_lookup_hash);
    }

    public function test_aggregate_only_account_cannot_write_or_read_identity_and_ciphertext_cannot_be_mass_assigned(): void
    {
        $this->actingAs($this->createStaff('bendahara'));
        $resident = Resident::factory()->create();
        $this->get('/masyarakat/'.$resident->id.'/edit')->assertForbidden();
        $this->post('/masyarakat', $this->residentPayload(['national_id_number' => '0123456789012345']))->assertForbidden();
        $this->post('/masyarakat/keluarga', [
            'head_of_household_name' => 'DEMO Tidak berizin',
            'is_active' => true,
            'family_card_number' => '0123456789098765',
        ])->assertForbidden();
        $this->actingAs($this->createSuperadmin());
        $this->put('/masyarakat/'.$resident->id, $this->residentPayload([
            'revision' => $resident->revision,
            'national_id_ciphertext' => 'injected',
            'national_id_lookup_hash' => str_repeat('a', 64),
        ]))->assertSessionHasNoErrors();
        $this->assertNull($resident->fresh()->national_id_ciphertext);
        $this->assertNull($resident->fresh()->national_id_lookup_hash);
    }
}
