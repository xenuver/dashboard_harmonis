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
            'role' => 'owner'
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

        $deleteAccountResponse = $this->post('/api/delete-user', [
            'userid' => $someone->id,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertOk();

        $this->assertDatabaseMissing('users', [
            'id' => $someone->id,
        ]);
    }

    public function test_cannot_delete_self()
    {
        $userId = $this->adminUser->id;

        $deleteAccountResponse = $this->post('/api/delete-user', [
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

        $deleteAccountResponse = $this->post('/api/delete-user', [
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
