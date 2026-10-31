<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Boost\BoostServiceProvider;
use Laravel\Boost\Mcp\Tools\GetAbsoluteUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DevelopmentLoggingSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function requestSources(): array
    {
        return [
            'localhost' => ['127.0.0.1', 'localhost', []],
            'network client' => ['192.168.1.50', '192.168.1.9:8443', []],
            'local HTTPS proxy' => [
                '127.0.0.1',
                '192.168.1.9:8443',
                ['X-Forwarded-For' => '192.168.1.50', 'X-Forwarded-Proto' => 'https'],
            ],
            'spoofed localhost header' => ['192.168.1.50', '192.168.1.9:8443', ['X-Forwarded-For' => '127.0.0.1']],
        ];
    }

    #[DataProvider('requestSources')]
    public function test_the_browser_log_collector_is_unavailable_without_login(string $remoteAddress, string $host, array $headers): void
    {
        $this->bootDevelopmentTools();

        $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress, 'HTTP_HOST' => $host])
            ->postJson('/_boost/browser-logs', ['logs' => []], $headers)
            ->assertNotFound();

        $this->assertGuest();
    }

    public function test_plain_text_beacon_requests_cannot_reach_the_browser_logger(): void
    {
        $this->bootDevelopmentTools();
        Log::shouldReceive('channel')->with('browser')->never();
        $payload = json_encode(['logs' => [[
            'type' => 'error',
            'data' => ['DEMO Security test'],
            'url' => 'https://example.test',
            'userAgent' => 'DEMO',
        ]]], JSON_THROW_ON_ERROR);

        $this->call('POST', '/_boost/browser-logs', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $payload)
            ->assertNotFound();
    }

    public function test_authenticated_users_cannot_reenable_the_http_log_collector(): void
    {
        $this->actingAs($this->createSuperadmin());
        $this->bootDevelopmentTools();
        Log::shouldReceive('channel')->with('browser')->never();

        $this->postJson('/_boost/browser-logs', ['logs' => []])->assertNotFound();

        $this->assertAuthenticated();
    }

    public function test_login_does_not_inject_the_browser_log_sender(): void
    {
        $this->bootDevelopmentTools();

        $this->get('/login')->assertOk()->assertDontSee('browser-logger-active', false);
    }

    public function test_boost_console_tools_remain_available(): void
    {
        $this->bootDevelopmentTools();
        $this->withoutMockingConsoleOutput();

        ob_start();
        try {
            $exitCode = $this->artisan('boost:execute-tool', [
                'tool' => GetAbsoluteUrl::class,
                'arguments' => base64_encode(json_encode(['path' => '/login'], JSON_THROW_ON_ERROR)),
            ]);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(0, $exitCode);
        $this->assertFalse($result['isError']);
        $this->assertStringEndsWith('/login', $result['content'][0]['text']);
    }

    private function bootDevelopmentTools(): void
    {
        config(['app.env' => 'local', 'app.debug' => true]);
        $this->app->instance('env', 'local');
        $this->app->register(BoostServiceProvider::class, force: true);
    }
}
