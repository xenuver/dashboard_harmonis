<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FallbackRouteTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fallback route — no auth required
    // -------------------------------------------------------------------------

    public function test_unknown_api_route_returns_404(): void
    {
        $this->getJson('/api/nonexistent-endpoint')
            ->assertNotFound()
            ->assertJson(['message' => 'Not Found']);
    }

    public function test_unknown_api_route_returns_json_not_html(): void
    {
        $response = $this->getJson('/api/nonexistent-endpoint')
            ->assertNotFound();

        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function test_fallback_works_for_deeply_nested_unknown_path(): void
    {
        $this->getJson('/api/some/deeply/nested/unknown/path')
            ->assertNotFound()
            ->assertJson(['message' => 'Not Found']);
    }

    public function test_fallback_works_without_authentication_token(): void
    {
        // Confirm fallback is outside the auth middleware group — no token needed
        $this->getJson('/api/totally-unknown-route')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);
    }

    public function test_fallback_message_is_exact(): void
    {
        $this->getJson('/api/xyz-does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);
    }
}
