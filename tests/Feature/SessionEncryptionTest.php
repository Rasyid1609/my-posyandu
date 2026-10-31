<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Session\EncryptedStore;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SessionEncryptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function drivers(): array
    {
        return ['database sessions' => ['database'], 'file sessions' => ['file']];
    }

    private function configureDriver(string $driver): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('encrypted-sessions');
        config([
            'session.driver' => $driver,
            'session.files' => Storage::disk('local')->path('encrypted-sessions'),
        ]);
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

    #[DataProvider('drivers')]
    public function test_login_and_validation_data_are_stored_encrypted_and_remain_usable(string $driver): void
    {
        $this->configureDriver($driver);
        $user = $this->createSuperadmin();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->resumeSession($this->app['session']->driver()->getId());
        $this->from('/masyarakat/create')->post(
            '/masyarakat',
            $this->residentPayload(['full_name' => 'DEMO Identitas dalam formulir', 'gender' => 'invalid'])
        )
            ->assertRedirect('/masyarakat/create')->assertSessionHasErrors('gender');
        $session = $this->app['session']->driver();
        $id = $session->getId();
        $raw = $session->getHandler()->read($id);

        $this->assertInstanceOf(EncryptedStore::class, $session);
        $this->assertStringNotContainsString('DEMO Identitas dalam formulir', $raw);
        $this->assertStringNotContainsString('_old_input', $raw);
        $payload = json_decode(Crypt::decrypt($raw), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('DEMO Identitas dalam formulir', $payload['_old_input']['full_name']);
        $this->resumeSession($id);
        $this->get('/masyarakat/create')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('drivers')]
    public function test_plaintext_legacy_sessions_require_login_again(string $driver): void
    {
        $this->configureDriver($driver);
        config(['session.encrypt' => false]);
        $this->resumeSession();
        $user = $this->createSuperadmin();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $session = $this->app['session']->driver();
        $id = $session->getId();
        $this->assertStringContainsString('password_hash_web', $session->getHandler()->read($id));
        config(['session.encrypt' => true]);
        $this->resumeSession($id);

        $this->get('/masyarakat')->assertRedirectToRoute('login');

        $this->assertGuest();
        $this->assertStringNotContainsString(
            'password_hash_web',
            $this->app['session']->driver()->getHandler()->read($id)
        );
    }

    #[DataProvider('drivers')]
    public function test_modified_session_payload_cannot_authenticate_a_request(string $driver): void
    {
        $this->configureDriver($driver);
        $user = $this->createSuperadmin();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $session = $this->app['session']->driver();
        $id = $session->getId();
        $session->getHandler()->write($id, 'invalid-encrypted-payload');
        $this->resumeSession($id);

        $this->post('/masyarakat', $this->residentPayload())->assertRedirectToRoute('login');

        $this->assertGuest();
        $this->assertDatabaseCount('residents', 0);
    }
}
