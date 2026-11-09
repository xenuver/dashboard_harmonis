<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteUserTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $ownerUser;

    private string $adminToken;

    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'it_staff',
        ]);
        $this->ownerUser = User::factory()->create([
            'role' => 'owner',
        ]);

        $this->adminToken = $this->adminUser->createToken('auth_token')->plainTextToken;
        $this->ownerToken = $this->ownerUser->createToken('auth_token')->plainTextToken;
    }

    public function test_admin_can_delete_user()
    {
        $username = fake()->unique()->userName();
        $password = fake()->password();

        $someone = User::factory()->create([
            'username' => $username,
            'password' => $password,
        ]);

        $deleteAccountResponse = $this->postJson('/api/delete-user', [
            'userid' => $someone->id,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertOk();

        $this->assertDatabaseMissing('users', [
            'id' => $someone->id,
        ]);
    }

    public function test_admin_cannot_delete_user_with_invalid_id()
    {
        $userId = 0;
        do {
            $userId = rand(0, 100);
        } while ($userId === $this->adminUser->id || $userId === $this->ownerUser->id);

        $deleteAccountResponse = $this->postJson('/api/delete-user', [
            'userid' => $userId,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertUnprocessable();
    }

    public function test_admin_cannot_delete_user_without_user_id()
    {
        $deleteAccountResponse = $this->postJson('/api/delete-user', [], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertUnprocessable();
    }

    public function test_cannot_delete_self()
    {
        $userId = $this->adminUser->id;

        $deleteAccountResponse = $this->postJson('/api/delete-user', [
            'userid' => $userId,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'id' => $userId,
        ]);
    }

    public function test_cannot_delete_account_if_user_is_not_admin()
    {
        $someone = User::factory()->create([
            'username' => fake()->unique()->userName(),
        ]);

        $deleteAccountResponse = $this->postJson('/api/delete-user', [
            'userid' => $someone->id,
        ], [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);
        $deleteAccountResponse->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $someone->id,
        ]);
    }
}
