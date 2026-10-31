<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordSessionRevocationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    public static function sessionVersions(): array
    {
        return [
            'current sessions' => [false],
            'legacy session without a password fingerprint' => [true],
        ];
    }

    #[DataProvider('sessionVersions')]
    public function test_password_reset_revokes_all_target_sessions_and_keeps_the_chair_signed_in(bool $legacy): void
    {
        $chair = $this->createSuperadmin();
        $staff = $this->createStaff();
        $firstSession = $this->signIn($staff);
        $secondSession = $this->signIn($staff);
        if ($legacy) {
            $payload = json_decode(
                Crypt::decrypt(base64_decode(DB::table('sessions')->where('id', $firstSession)->value('payload'))),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            unset($payload['password_hash_web']);
            DB::table('sessions')
                ->where('id', $firstSession)
                ->update(['payload' => base64_encode(Crypt::encrypt(json_encode($payload, JSON_THROW_ON_ERROR)))]);
        }
        $chairSession = $this->signIn($chair);

        $this->put(
            '/pengaturan/akun/'.$staff->id,
            $this->accountPayload($staff, ['password' => 'New-Password-2026!'])
        )
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('New-Password-2026!', $staff->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $staff->id]);
        $this->assertDatabaseHas('sessions', ['id' => $chairSession, 'user_id' => $chair->id]);
        $this->resumeSession($chairSession);
        $this->get('/pengaturan/akun')->assertOk();
        foreach ([$firstSession, $secondSession] as $sessionId) {
            $this->resumeSession($sessionId);
            $this->get('/masyarakat')->assertRedirectToRoute('login');
            $this->assertGuest();
        }
        $this->resumeSession();
        $this->from('/masyarakat/create')->post(
            '/login',
            ['email' => $staff->email, 'password' => 'password']
        )
            ->assertRedirectToRoute('login')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->signIn($staff, 'New-Password-2026!');
        $this->get('/masyarakat')->assertOk();
    }

    public function test_a_stale_session_written_after_a_password_reset_is_rejected_before_a_write(): void
    {
        $chair = $this->createSuperadmin();
        $staff = $this->createStaff();
        $staffSession = $this->signIn($staff);
        $staleSession = (array) DB::table('sessions')->where('id', $staffSession)->first();
        $this->signIn($chair);
        $this->put(
            '/pengaturan/akun/'.$staff->id,
            $this->accountPayload($staff, ['password' => 'New-Password-2026!'])
        )
            ->assertSessionHasNoErrors();
        DB::table('sessions')->insert($staleSession);
        $this->resumeSession($staffSession);

        $this->post('/masyarakat', $this->residentPayload())->assertRedirectToRoute('login');

        $this->assertGuest();
        $this->assertDatabaseCount('residents', 0);
        $response = $this->get('/login');
        $response->assertOk();
        $this->assertTrue($response->viewData('page')['clearHistory']);
    }

    public function test_updating_an_account_without_a_password_keeps_its_sessions_valid(): void
    {
        $chair = $this->createSuperadmin();
        $staff = $this->createStaff();
        $staffSession = $this->signIn($staff);
        $originalHash = $staff->password;
        $this->signIn($chair);

        $this->put(
            '/pengaturan/akun/'.$staff->id,
            $this->accountPayload($staff, ['name' => 'DEMO Nama Diperbarui', 'password' => null])
        )
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'name' => 'DEMO Nama Diperbarui']);
        $this->assertSame($originalHash, $staff->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => $staffSession, 'user_id' => $staff->id]);
        $this->resumeSession($staffSession);
        $this->get('/masyarakat')->assertOk();
        $this->assertAuthenticatedAs($staff);
    }

    public function test_changing_ones_own_password_logs_out_all_own_sessions_without_affecting_other_users(): void
    {
        $chair = $this->createSuperadmin();
        $staff = $this->createStaff();
        $staffSession = $this->signIn($staff);
        $otherChairSession = $this->signIn($chair);
        $currentChairSession = $this->signIn($chair);

        $this->put(
            '/pengaturan/akun/'.$chair->id,
            $this->accountPayload($chair, ['role' => 'superadmin', 'password' => 'New-Password-2026!'])
        )
            ->assertRedirectToRoute('login')->assertSessionHas('success', 'Kata sandi diperbarui. Silakan masuk kembali.');

        $this->assertGuest();
        $this->assertTrue(Hash::check('New-Password-2026!', $chair->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $chair->id]);
        $this->assertDatabaseHas('sessions', ['id' => $staffSession, 'user_id' => $staff->id]);
        foreach ([$currentChairSession, $otherChairSession] as $sessionId) {
            $this->resumeSession($sessionId);
            $this->get('/pengaturan/akun')->assertRedirectToRoute('login');
        }
        $this->signIn($chair, 'New-Password-2026!');
        $this->get('/pengaturan/akun')->assertOk();
    }

    public function test_a_forbidden_password_reset_does_not_change_the_password_or_revoke_sessions(): void
    {
        $chair = $this->createSuperadmin();
        $chairSession = $this->signIn($chair);
        $staff = $this->createStaff();
        $this->signIn($staff);
        $originalHash = $chair->password;

        $this->put(
            '/pengaturan/akun/'.$chair->id,
            $this->accountPayload($chair, ['role' => 'superadmin', 'password' => 'New-Password-2026!'])
        )
            ->assertForbidden();

        $this->assertSame($originalHash, $chair->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => $chairSession, 'user_id' => $chair->id]);
        $this->resumeSession($chairSession);
        $this->get('/pengaturan/akun')->assertOk();
    }

    public function test_invalid_password_input_does_not_revoke_sessions(): void
    {
        $chair = $this->createSuperadmin();
        $staff = $this->createStaff();
        $staffSession = $this->signIn($staff);
        $originalHash = $staff->password;
        $this->signIn($chair);

        $this->put('/pengaturan/akun/'.$staff->id, $this->accountPayload($staff, ['password' => 'short']))
            ->assertSessionHasErrors('password');

        $this->assertSame($originalHash, $staff->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => $staffSession, 'user_id' => $staff->id]);
    }

    private function signIn(User $user, string $password = 'password'): string
    {
        $this->resumeSession();
        $this->post('/login', ['email' => $user->email, 'password' => $password])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('residents.index');
        $this->assertAuthenticatedAs($user);

        return $this->app['session']->driver()->getId();
    }

    private function resumeSession(?string $sessionId = null): void
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['redirect']->setSession($this->app['session']->driver());
        $this->defaultCookies = [];
        if ($sessionId !== null) {
            $this->withCookie(config('session.cookie'), $sessionId);
        }
    }

    private function accountPayload(User $user, array $overrides = []): array
    {
        return array_replace([
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
            'is_active' => true,
            'permissions' => [],
        ], $overrides);
    }
}
