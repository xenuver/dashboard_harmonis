<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteUserTest extends TestCase
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

    public function test_admin_can_delete_user()
    {
        $username = fake()->unique()->userName();
        $password = fake()->password();

        $someone = User::factory()->create([
            'username' => $username,
            'password' => $password,
        ]);

        $deleteAccountResponse = $this->post('/api/delete-user', [
            'userid' => $someone->id,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertOk();

    }

    public function test_cannot_delete_self()
    {
        $me = $this->get('/api/whoami', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $userId = $me->json('id');

        $deleteAccountResponse = $this->post('/api/delete-user', [
            'userid' => $userId,
        ], [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);
        $deleteAccountResponse->assertUnprocessable();
    }

    public function test_cannot_delete_account_if_user_is_not_admin()
    {
        $someone = User::factory()->create([
            'username' => fake()->unique()->userName(),
        ]);

        $deleteAccountResponse = $this->post('/api/delete-user', [
            'userid' => $someone->id,
        ], [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);
        $deleteAccountResponse->assertForbidden();
    }
}
