<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HouseholdDetailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_must_log_in_to_view_family_details(): void
    {
        $this->get('/masyarakat/keluarga/1')->assertRedirectToRoute('login');
    }

    public function test_identity_permission_alone_does_not_allow_family_details(): void
    {
        $this->actingAs($this->createStaff('bendahara', ['identity.reveal']));
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga', 'is_active' => true]);

        $this->get(route('households.show', $family))->assertForbidden();
    }

    public function test_details_include_only_this_family_and_preserve_historical_participants(): void
    {
        $user = $this->createSuperadmin();
        $this->actingAs($user);
        $family = Household::create([
            'head_of_household_name' => 'DEMO Keluarga Satu',
            'address' => 'DEMO Jalan Jeruk',
            'rt' => '01',
            'rw' => '15',
            'is_active' => true,
        ]);
        $family->forceFill(['family_card_ciphertext' => '0123456789098765'])->save();
        $member = Resident::factory()->create([
            'household_id' => $family->id,
            'full_name' => 'DEMO Ana',
            'family_relationship' => 'Ibu',
            'national_id_ciphertext' => '0123456789012345',
            'phone_ciphertext' => '081234567890',
        ]);
        Resident::factory()->create(['household_id' => $family->id, 'full_name' => 'DEMO Budi']);
        $former = Resident::factory()->create(['household_id' => $family->id, 'full_name' => 'DEMO Anggota Lama']);
        $outside = Household::create(['head_of_household_name' => 'DEMO Keluarga Lain', 'is_active' => true]);
        Resident::factory()->create(['household_id' => $outside->id, 'full_name' => 'DEMO Warga Lain']);
        $january = ReportingPeriod::factory()->create();
        $february = ReportingPeriod::factory()->create(['month' => 2, 'reference_date' => '2026-02-28']);
        $january->homeVisits()->create([
            'household_id' => $family->id,
            'visited_on' => '2026-01-15',
            'reason' => 'DEMO Kunjungan Januari',
            'record_status' => 'draft',
            'created_by' => $user->id,
        ]);
        $latest = $february->homeVisits()->create([
            'household_id' => $family->id,
            'visited_on' => '2026-02-15',
            'reason' => 'DEMO Kunjungan Februari',
            'follow_up' => 'DEMO Tindak lanjut',
            'record_status' => 'finalized',
            'created_by' => $user->id,
        ]);
        $latest->staff()->attach($user);
        $latest->residents()->attach([
            $member->id => ['category_snapshot' => 'adult'],
            $former->id => ['category_snapshot' => 'adult'],
        ], [
            'no_service' => 'unknown',
            'danger_sign' => 'unknown',
            'tb_symptom' => 'unknown',
            'irregular_medication' => 'unknown',
        ]);
        $former->delete();
        $january->homeVisits()->create([
            'household_id' => $outside->id,
            'visited_on' => '2026-01-20',
            'reason' => 'DEMO Kunjungan Keluarga Lain',
            'record_status' => 'draft',
            'created_by' => $user->id,
        ]);

        $response = $this->get(route('households.show', $family));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Residents/HouseholdDetail')
            ->where('household.head_of_household_name', 'DEMO Keluarga Satu')
            ->where('household.family_card_display', '0123456789098765')
            ->where('household.residents_count', 2)
            ->missing('household.family_card_ciphertext')->missing('household.family_card_lookup_hash')
            ->has('members.data', 2)->where('members.data.0.full_name', 'DEMO Ana')
            ->where('members.data.0.family_relationship', 'Ibu')
            ->where('members.data.0.detail_url', route('residents.edit', $member))
            ->missing('members.data.0.national_id_ciphertext')->missing('members.data.0.phone_ciphertext')
            ->has('homeVisits.data', 2)->where('homeVisits.data.0.id', $latest->id)
            ->where('homeVisits.data.0.period.month', 2)->where('homeVisits.data.0.record_status', 'finalized')
            ->where('homeVisits.data.0.follow_up', 'DEMO Tindak lanjut')
            ->where('homeVisits.data.0.staff.0.name', $user->name)->missing('homeVisits.data.0.staff.0.email')
            ->where('homeVisits.data.0.residents.1.full_name', 'DEMO Anggota Lama')
            ->where('homeVisits.data.0.residents.1.detail_url', null)
            ->missing('homeVisits.data.0.residents.0.pivot')
            ->where('homeVisits.data.0.period_url', route('home-visits.index', ['period' => $february->id, 'household_id' => $family->id])));
        $response->assertDontSee('DEMO Warga Lain')->assertDontSee('DEMO Kunjungan Keluarga Lain')
            ->assertDontSee('0123456789012345')->assertDontSee('081234567890');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'household.viewed',
            'resource_id' => $family->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas(
            'audit_logs',
            ['action' => 'household.identity_revealed', 'resource_id' => $family->id]
        );
        $this->assertDatabaseHas(
            'audit_logs',
            ['action' => 'household.home_visits_viewed', 'resource_id' => $family->id]
        );
    }

    public function test_identity_viewer_gets_masked_kk_without_service_history(): void
    {
        $user = $this->createStaff('bendahara', ['residents.view']);
        $this->actingAs($user);
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga', 'is_active' => true]);
        $family->forceFill(['family_card_ciphertext' => '0123456789098765'])->save();
        Resident::factory()->create(['household_id' => $family->id]);
        $period = ReportingPeriod::factory()->create();
        $period->homeVisits()->create([
            'household_id' => $family->id,
            'visited_on' => '2026-01-15',
            'reason' => 'DEMO Riwayat Terbatas',
            'record_status' => 'draft',
            'created_by' => $user->id,
        ]);

        $response = $this->get(route('households.show', $family));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('household.family_card_display', '************8765')
            ->where('household.residents_count', 1)->has('members.data', 1)->where('homeVisits', null));
        $response->assertDontSee('0123456789098765')->assertDontSee('DEMO Riwayat Terbatas');
        $this->assertDatabaseMissing(
            'audit_logs',
            ['action' => 'household.identity_revealed', 'resource_id' => $family->id]
        );
        $this->assertDatabaseMissing(
            'audit_logs',
            ['action' => 'household.home_visits_viewed', 'resource_id' => $family->id]
        );
    }

    public function test_empty_archived_family_can_be_viewed_without_inventing_members_or_visits(): void
    {
        $this->actingAs($this->createSuperadmin());
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga Arsip', 'is_active' => false]);

        $this->get(route('households.show', $family))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('household.is_active', false)->where('household.family_card_display', null)
            ->where('members.total', 0)->has('members.data', 0)->where('homeVisits.total', 0)->has('homeVisits.data', 0));
    }

    public function test_member_and_visit_pagination_are_independent(): void
    {
        $user = $this->createSuperadmin();
        $this->actingAs($user);
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga Besar', 'is_active' => true]);
        Resident::factory()
            ->count(15)
            ->create(['household_id' => $family->id, 'full_name' => 'DEMO Anggota A']);
        $last = Resident::factory()->create(['household_id' => $family->id, 'full_name' => 'DEMO Anggota Z']);
        $period = ReportingPeriod::factory()->create();
        for ($index = 0; $index < 16; $index++) {
            $period->homeVisits()->create([
                'household_id' => $family->id,
                'visited_on' => '2026-01-15',
                'reason' => 'DEMO Kunjungan '.$index,
                'record_status' => 'draft',
                'created_by' => $user->id,
            ]);
        }

        $response = $this->get(route('households.show', ['household' => $family, 'members_page' => 2, 'visits_page' => 2]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('members.current_page', 2)->where('members.total', 16)->has('members.data', 1)->where('members.data.0.id', $last->id)
            ->where('homeVisits.current_page', 2)->where('homeVisits.total', 16)->has('homeVisits.data', 1)->where('homeVisits.data.0.reason', 'DEMO Kunjungan 0'));
        $this->assertStringContainsString('visits_page=2', $response->inertiaProps('members.prev_page_url'));
        $this->assertStringContainsString(
            'members_page=2',
            $response->inertiaProps('homeVisits.prev_page_url')
        );
    }

    public function test_missing_and_deleted_families_return_not_found(): void
    {
        $this->actingAs($this->createSuperadmin());
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga Dihapus', 'is_active' => true]);
        $family->delete();

        $this->get(route('households.show', $family))->assertNotFound();
        $this->get('/masyarakat/keluarga/999999')->assertNotFound();
    }
}
