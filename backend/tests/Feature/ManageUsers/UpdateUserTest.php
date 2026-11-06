<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = User::factory()->create([
            'role' => 'it_staff',
        ])->createToken('auth_token')->plainTextToken;
        $this->ownerToken = User::factory()->create([
            'role' => 'owner',
        ])->createToken('auth_token')->plainTextToken;
    }

    public function test_update_account()
    {
        $someone = User::factory()->create([
            'username' => fake()->unique()->userName(),
            'password' => fake()->password(),
            'role' => 'owner',
            'enabled' => true,
        ]);

        $updateAccountResponse = $this->postJson('/api/update-user', [
            'userid' => $someone->id,
            'password' => fake()->password(),
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $updateAccountResponse->assertOk();
    }

    public function test_cannot_demote_self_from_admin()
    {
        $me = $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $userId = $me->json('id');

        $updateAccountResponse = $this->postJson('/api/update-user', [
            'userid' => $userId,
            'role' => 'owner',
        ], [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);
        $updateAccountResponse->assertUnprocessable();
    }

    public function test_cannot_update_account_if_user_is_not_admin()
    {
        $someone = User::factory()->create([
            'username' => fake()->unique()->userName(),
            'role' => 'owner',
        ]);

        $updateAccountResponse = $this->postJson('/api/update-user', [
            'userid' => $someone->id,
            'password' => fake()->password(),
        ], [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);
        $updateAccountResponse->assertForbidden();
    }
}
