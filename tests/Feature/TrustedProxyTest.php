<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_a_local_https_proxy_keeps_authentication_redirects_on_https(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => '192.168.1.9:8443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '8443',
        ])->get('http://192.168.1.9:8443/masyarakat');

        $response->assertRedirect('https://192.168.1.9:8443/login');
    }

    public function test_a_network_client_cannot_spoof_the_forwarded_scheme(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.50',
            'HTTP_HOST' => '192.168.1.9:8000',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '8443',
        ])->get('http://192.168.1.9:8000/masyarakat');

        $response->assertRedirect('http://192.168.1.9:8000/login');
    }

    public function test_a_proxy_cannot_replace_the_host_with_a_forwarded_host(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => '192.168.1.9:8443',
            'HTTP_X_FORWARDED_HOST' => 'untrusted.example',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '8443',
        ])->get('http://192.168.1.9:8443/masyarakat');

        $response->assertRedirect('https://192.168.1.9:8443/login');
    }

    public function test_login_still_requires_encrypted_browser_history(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertTrue($response->viewData('page')['encryptHistory']);
    }
}
