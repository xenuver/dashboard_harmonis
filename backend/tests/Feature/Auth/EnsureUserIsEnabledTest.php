<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class EnsureUserIsEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_user_can_access_protected_routes(): void
    {
        $user = User::factory()->create(['enabled' => true]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('username', $user->username);
    }

    public function test_tokens_are_revoked_if_user_is_disabled_mid_session(): void
    {
        $user = User::factory()->create(['enabled' => true]);
        $token = $user->createToken('auth_token')->plainTextToken;

        // Verify user can access initially
        $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        // User account is disabled
        $user->update(['enabled' => false]);

        // Reset auth guards so Sanctum re-evaluates the request
        Auth::forgetGuards();

        // Next request is rejected by EnsureUserIsEnabled middleware
        $response = $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        // Verify all user tokens in database are now expired
        $personalAccessToken = PersonalAccessToken::findToken($token);
        $this->assertNotNull($personalAccessToken);
        $this->assertTrue($personalAccessToken->expires_at->isPast());
    }

    public function test_disabled_user_with_token_cannot_access_protected_routes(): void
    {
        $user = User::factory()->create(['enabled' => false]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $personalAccessToken = PersonalAccessToken::findToken($token);
        $this->assertNotNull($personalAccessToken);
        $this->assertTrue($personalAccessToken->expires_at->isPast());
    }
}
