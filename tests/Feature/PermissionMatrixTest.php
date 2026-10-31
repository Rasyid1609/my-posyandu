<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ReportingPeriod;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function expectedPermissions(): array
    {
        return [
            'Ketua' => [
                'superadmin',
                [
                    'aggregates.view',
                    'residents.view',
                    'residents.manage',
                    'targets.manage',
                    'visits.view',
                    'visits.record',
                    'visits.finalize',
                    'reports.submit',
                    'reports.approve',
                    'accounts.manage',
                    'visits.correct',
                    'exports.aggregate',
                    'analysis.manage',
                    'imports.manage',
                    'identity.reveal',
                ],
            ],
            'Sekretaris' => [
                'sekretaris',
                [
                    'aggregates.view',
                    'residents.view',
                    'residents.manage',
                    'targets.manage',
                    'visits.view',
                    'visits.record',
                    'visits.finalize',
                    'reports.submit',
                    'exports.aggregate',
                    'analysis.manage',
                    'imports.manage',
                ],
            ],
            'Bendahara' => ['bendahara', ['aggregates.view']],
            'Anggota' => [
                'admin',
                [
                    'aggregates.view',
                    'residents.view',
                    'residents.manage',
                    'targets.manage',
                    'visits.view',
                    'visits.record',
                ],
            ],
        ];
    }

    #[DataProvider('expectedPermissions')]
    public function test_roles_receive_exact_administrative_permission_matrix(string $role, array $permissions): void
    {
        $staff = $this->createStaff($role);
        sort($permissions);

        $actual = $staff->getAllPermissions()->pluck('name')->sort()->values()->all();

        $this->assertSame($permissions, $actual);
        $this->assertFalse($staff->can('clinical.validate'));
    }

    public function test_reapplying_matrix_is_idempotent_and_preserves_individual_grants(): void
    {
        $staff = $this->createStaff('admin', ['reports.submit']);
        $auditCount = AuditLog::where('action', 'permission_matrix.applied')->count();

        $this->seed(AccessControlSeeder::class);
        $this->seed(AccessControlSeeder::class);

        $this->assertTrue($staff->fresh()->can('reports.submit'));
        $this->assertFalse($staff->fresh()->can('reports.approve'));
        $this->assertSame($auditCount, AuditLog::where('action', 'permission_matrix.applied')->count());
        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('permissions', 17);
    }

    public function test_secretary_can_submit_but_cannot_approve_report(): void
    {
        $this->actingAs($this->createStaff('sekretaris'));
        $period = ReportingPeriod::factory()->create();

        $this->patch('/rekapitulasi/'.$period->id, [
            'revision' => 1,
            'action' => 'submit',
            'reason' => 'Kelengkapan telah diperiksa',
        ])->assertSessionHasNoErrors();
        $this->patch('/rekapitulasi/'.$period->id, [
            'revision' => $period->fresh()->revision,
            'action' => 'approve',
            'reason' => 'Percobaan persetujuan sekretaris',
        ])->assertForbidden();

        $this->assertSame('submitted', $period->fresh()->status);
        $this->assertDatabaseCount('report_snapshots', 0);
    }

    public function test_member_cannot_submit_report_using_direct_endpoint(): void
    {
        $this->actingAs($this->createStaff('admin'));
        $period = ReportingPeriod::factory()->create();

        $this->patch('/rekapitulasi/'.$period->id, [
            'revision' => 1,
            'action' => 'submit',
            'reason' => 'Percobaan pengajuan anggota',
        ])->assertForbidden();

        $this->assertSame('open', $period->fresh()->status);
    }

    public function test_account_page_exposes_role_defaults_and_keeps_clinical_rules_disabled(): void
    {
        $this->actingAs($this->createSuperadmin());

        $this->get('/pengaturan/akun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Accounts')
            ->where('matrixVersion', 'operational-v3')
            ->where('rolePermissions.bendahara', ['aggregates.view'])
            ->has('rolePermissions.sekretaris', 11)
            ->where('configuration.clinical_rules', false)
            ->where('configuration.real_data_approved', false));
    }

    public function test_changing_role_replaces_inherited_access_without_retaining_previous_baseline(): void
    {
        $this->actingAs($this->createSuperadmin());
        $staff = User::factory()->create();
        $staff->assignRole('sekretaris');

        $this->put('/pengaturan/akun/'.$staff->id, [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => 'bendahara',
            'is_active' => true,
            'permissions' => [],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($staff->fresh()->can('aggregates.view'));
        $this->assertFalse($staff->fresh()->can('residents.view'));
        $this->assertFalse($staff->fresh()->can('visits.finalize'));
    }

    public function test_unassigned_account_remains_without_data_access(): void
    {
        $this->seed(AccessControlSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->get('/masyarakat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('AccessPending'));
        $this->post('/masyarakat', $this->residentPayload())->assertForbidden();
    }
}
