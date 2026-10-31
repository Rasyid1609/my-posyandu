<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The entry point redirects into the authenticated workspace.
     */
    public function test_the_entry_point_redirects_to_the_workspace(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/masyarakat');
    }
}
