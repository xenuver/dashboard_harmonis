<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_three_requests_per_second(): void
    {
        $user = User::factory()->create(['enabled' => true]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/login', [
                'username' => $user->username,
                'password' => 'wrong-pass',
            ])->assertUnauthorized();
        }

        // 4th request in the same second exceeds rate limit
        $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'wrong-pass',
        ])->assertStatus(429);
    }
}
