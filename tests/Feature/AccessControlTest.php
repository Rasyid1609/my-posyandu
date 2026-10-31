<?php

namespace Tests\Feature;

use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_and_public_registration_is_unavailable(): void
    {
        $this->get('/masyarakat')->assertRedirectToRoute('login');
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public static function nonChairRoles(): array
    {
        return [['sekretaris'], ['bendahara'], ['admin']];
    }

    #[DataProvider('nonChairRoles')]
    public function test_non_chair_roles_follow_active_matrix_and_cannot_manage_accounts(string $role): void
    {
        $staff = $this->createStaff($role);
        $resident = Resident::factory()->create();
        $this->actingAs($staff);

        $this->assertSame($role !== 'bendahara', $staff->can('view', $resident));
        $this->assertSame($role !== 'bendahara', $staff->can('create', Resident::class));
        $this->get('/masyarakat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Residents/Index'));
        $this->get('/pengaturan/akun')->assertForbidden();
        $this->assertDatabaseCount('residents', 1);
    }

    public function test_aggregate_only_user_cannot_receive_identity_props(): void
    {
        $staff = $this->createStaff('bendahara', ['aggregates.view']);
        Resident::factory()->create(['full_name' => 'DEMO Nama Rahasia', 'national_id_number' => '1234567890123456']);
        $this->actingAs($staff);

        $response = $this->get('/masyarakat');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Residents/Index')->where('stats.total', 1)->where('residents', null));
        $response->assertDontSee('DEMO Nama Rahasia')->assertDontSee('1234567890123456');
        $this->get('/masyarakat/create')->assertForbidden();
        $this->post('/masyarakat', $this->residentPayload())->assertForbidden();
        $this->assertDatabaseCount('residents', 1);
    }

    public function test_identity_view_does_not_authorize_writes_or_expose_nik(): void
    {
        $this->seed(AccessControlSeeder::class);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('residents.view');
        $this->actingAs($viewer);
        $resident = Resident::factory()->create(['national_id_number' => '1234567890123456']);

        $this->get('/masyarakat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('residents.data', 1)
                ->missing('residents.data.0.national_id_number')
                ->where('stats', null));
        $this->put('/masyarakat/'.$resident->id, $this->residentPayload(['revision' => 1]))->assertForbidden();
    }

    public function test_aggregate_only_user_cannot_probe_names_through_search(): void
    {
        $this->actingAs($this->createStaff('bendahara', ['aggregates.view']));

        $this->get('/masyarakat?q=Nama%20Rahasia')->assertForbidden();
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $this->actingAs($this->createSuperadmin());

        $this->post('/logout')->assertRedirectToRoute('login');

        $this->assertGuest();
        $this->get('/masyarakat')->assertRedirectToRoute('login');
    }

    public function test_active_permission_can_be_granted_without_automatic_role_privilege(): void
    {
        $this->seed(AccessControlSeeder::class);
        $staff = User::factory()->create();
        $staff->givePermissionTo(['residents.view', 'residents.manage']);
        $this->actingAs($staff);

        $this->post('/masyarakat', $this->residentPayload([
            'is_active' => false,
            'national_id_ciphertext' => 'UNTRUSTED',
            'registration_number' => 'INJECTED',
        ]))->assertSessionHasNoErrors();

        $resident = Resident::sole();
        $this->assertStringStartsWith('PJ-', $resident->registration_number);
        $this->assertNull($resident->national_id_number);
        $this->assertNull($resident->national_id_ciphertext);
    }

    public function test_inactive_account_is_rejected_on_login_and_existing_session(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/masyarakat')->assertRedirectToRoute('login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'rate@demo.test']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'rate@demo.test', 'password' => 'incorrect'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'rate@demo.test', 'password' => 'incorrect'])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_last_active_superadmin_cannot_be_demoted_or_deactivated(): void
    {
        $chair = $this->createSuperadmin();
        $this->actingAs($chair);
        $payload = [
            'name' => $chair->name,
            'email' => $chair->email,
            'role' => 'admin',
            'is_active' => true,
            'permissions' => [],
        ];

        $this->put('/pengaturan/akun/'.$chair->id, $payload)->assertSessionHasErrors('role');
        $this->put(
            '/pengaturan/akun/'.$chair->id,
            [...$payload, 'role' => 'superadmin', 'is_active' => false]
        )->assertSessionHasErrors('role');

        $this->assertTrue($chair->fresh()->is_active);
        $this->assertTrue($chair->fresh()->hasRole('superadmin'));
    }

    public function test_non_chair_with_account_permission_still_cannot_grant_accounts(): void
    {
        $this->actingAs($this->createStaff('admin', ['accounts.manage']));

        $this->post('/pengaturan/akun', [
            'name' => 'Attacker',
            'email' => 'attacker@demo.test',
            'password' => 'Long-Password-2026!',
            'role' => 'superadmin',
            'is_active' => true,
            'permissions' => [],
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'attacker@demo.test']);
    }

    public function test_account_creation_grants_secretary_baseline_without_approval_permission(): void
    {
        $this->actingAs($this->createSuperadmin());

        $this->post('/pengaturan/akun', [
            'name' => 'DEMO Sekretaris',
            'email' => 'sekretaris@demo.test',
            'password' => 'Long-Password-2026!',
            'role' => 'sekretaris',
            'is_active' => true,
            'permissions' => [],
        ])->assertSessionHasNoErrors();

        $staff = User::where('email', 'sekretaris@demo.test')->sole();
        $this->assertTrue($staff->hasRole('sekretaris'));
        $this->assertTrue($staff->can('residents.view'));
        $this->assertTrue($staff->can('visits.finalize'));
        $this->assertTrue($staff->can('reports.submit'));
        $this->assertFalse($staff->can('reports.approve'));
        $this->assertSame(0, $staff->permissions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.created', 'resource_id' => $staff->id]);
    }

    public function test_record_permission_does_not_allow_administrative_finalization(): void
    {
        $this->actingAs($this->createStaff('admin', ['residents.view', 'visits.view', 'visits.record']));
        $period = ReportingPeriod::factory()->create();
        $resident = Resident::factory()->create();
        $target = $period->targets()->create(['resident_id' => $resident->id, 'category_code' => 'general']);
        $visit = $period->visits()->create([
            'target_membership_id' => $target->id,
            'resident_id' => $resident->id,
            'visited_at' => '2026-01-15 02:00:00',
        ]);

        $this->post('/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/finalisasi', ['revision' => 1])->assertForbidden();

        $this->assertSame('draft', $visit->fresh()->record_status);
    }

    public function test_dashboard_filter_and_server_pagination_share_database_scope(): void
    {
        $this->actingAs($this->createSuperadmin());
        Resident::factory()
            ->count(17)
            ->create(['full_name' => 'DEMO Aktif', 'residency_status' => 'active']);
        Resident::factory()->create(['full_name' => 'DEMO Pindah', 'residency_status' => 'moved']);

        $this->get('/masyarakat?status=active')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stats.total', 17)->where('residents.total', 17)->has('residents.data', 15));
        $this->get('/masyarakat?q=Aktif&status=active&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stats.total', 17)->has('residents.data', 2));
    }
}
