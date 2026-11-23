<?php

namespace Tests\Feature\ManageUsers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function deleteInvalidUserIdProvider()
    {
        $invalidInputCases = ['a', 'admin', 'username', '%', '!', -1];

        $cases = [];

        foreach ($invalidInputCases as $value) {
            $cases['set to '.(json_encode($value))] = [
                $value,
            ];
        }

        return $cases;
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
        $userId = User::max('id') + 9999;

        $deleteAccountResponse = $this->postJson('/api/delete-user', [
            'userid' => $userId,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertUnprocessable();
        $deleteAccountResponse->assertJsonValidationErrors('userid');
    }

    public function test_admin_cannot_delete_user_without_user_id()
    {
        $deleteAccountResponse = $this->postJson('/api/delete-user', [], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertUnprocessable();
    }

    #[DataProvider('deleteInvalidUserIdProvider')]
    public function test_admin_cannot_delete_user_with_user_id(mixed $userId)
    {
        $deleteAccountResponse = $this->postJson('/api/delete-user', [
            'userid' => $userId,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertUnprocessable();
        $deleteAccountResponse->assertJsonValidationErrors('userid');
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

    public function test_cannot_delete_account_if_user_is_not_logged_in()
    {
        $someone = User::factory()->create([
            'username' => fake()->unique()->userName(),
        ]);

        $deleteAccountResponse = $this->postJson('/api/delete-user', [
            'userid' => $someone->id,
        ]);
        $deleteAccountResponse->assertUnauthorized();

        $this->assertDatabaseHas('users', [
            'id' => $someone->id,
        ]);
    }

    public function test_user_tokens_are_revoked_when_user_is_deleted()
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        // Verify user can authenticate initially
        $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        // Forget auth guards so Sanctum authenticates the admin token
        Auth::forgetGuards();

        // Admin deletes user
        $deleteResponse = $this->postJson('/api/delete-user', [
            'userid' => $user->id,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteResponse->assertOk();

        // Forget auth guards so Sanctum re-evaluates the token
        Auth::forgetGuards();

        // Request with the deleted user's token must be rejected
        $response = $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response->assertUnauthorized();
    }
}
