<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create(['enabled' => true]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'anda telah keluar',
            ]);

        Auth::forgetGuards();

        $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ])->assertUnauthorized();
    }

    public function test_logout_only_invalidates_current_token(): void
    {
        $user = User::factory()->create(['enabled' => true]);
        $deviceAToken = $user->createToken('device_a')->plainTextToken;
        $deviceBToken = $user->createToken('device_b')->plainTextToken;

        $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer '.$deviceAToken,
        ])->assertOk();

        Auth::forgetGuards();

        $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$deviceAToken,
        ])->assertUnauthorized();

        Auth::forgetGuards();

        $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$deviceBToken,
        ])->assertOk();
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}