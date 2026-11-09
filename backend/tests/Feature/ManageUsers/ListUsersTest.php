<?php

namespace Tests\Feature\ManageUsers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListUsersTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private string $ownerToken;

    private int $fakeUsersCount = 200;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->count($this->fakeUsersCount)->sequence(fn ($sequence) => [
            'username' => 'user_'.($sequence->index + 1),
            'role' => fake()->randomElement(['owner', 'it_staff']),
        ])->create();

        $this->adminToken = User::factory()->create([
            'role' => 'it_staff',
        ])->createToken('auth_token')->plainTextToken;
        $this->ownerToken = User::factory()->create([
            'role' => 'owner',
        ])->createToken('auth_token')->plainTextToken;
    }

    public static function usersPerPageParamsProvider(): array
    {
        return [
            '-1 user (rejected)' => [-1, false],
            '10 users (accepted)' => [10, true],
            '20 users (accepted)' => [20, true],
            '40 users (rejected)' => [40, false],
            '50 users (accepted)' => [50, true],
            '2000000000 users (rejected)' => [2000000000, false],
        ];
    }

    public static function usersPageParamsProvider(): array
    {

        return [
            'page -1 (rejected)' => [-1, false],
            'page 1 (accepted)' => [1, true],
            'page 5 (accepted)' => [5, true],
            'page 999999 (accepted)' => [999999, true],
        ];
    }

    public static function searchUsersParamsProvider(): array
    {
        // asumsi ada 200 pengguna
        return [
            'user_ (200 hasil)' => ['user_', 200],
            'user_1 (111 hasil)' => ['user_1', 111],
            'user_456 (0 hasil)' => ['user_456', 0],
            'ser_200 (1 hasil)' => ['ser_200', 1],
        ];
    }

    public static function filterRoleParamsProvider(): array
    {
        // asumsi ada 200 pengguna
        return [
            'it_staff (accepted)' => ['it_staff', true],
            'staf_it (rejected)' => ['staf_it', false],
            'owner (accepted)' => ['owner', true],
            'guest (rejected)' => ['guest', false],
        ];
    }

    public function test_list_accounts()
    {
        $readAccountsResponse = $this->getJson('/api/list-users', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $readAccountsResponse->assertOk();
    }

    #[DataProvider('usersPerPageParamsProvider')]
    public function test_list_accounts_per_page(int $perPage, bool $shouldSuccess)
    {
        $listAccountsResponse = $this->getJson('/api/list-users?per_page='.($perPage), [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if ($shouldSuccess) {
            $listAccountsResponse->assertOk()->assertJsonCount($perPage, 'data');
        } else {
            $listAccountsResponse->assertUnprocessable();
        }
    }

    #[DataProvider('usersPageParamsProvider')]
    public function test_list_accounts_page(int $page, bool $shouldSuccess)
    {
        $listAccountsResponse = $this->getJson('/api/list-users?page='.($page), [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if ($shouldSuccess) {
            $listAccountsResponse->assertOk();
        } else {
            $listAccountsResponse->assertUnprocessable();
        }
    }

    #[DataProvider('searchUsersParamsProvider')]
    public function test_search_accounts(string $keyword, int $expectedResultsCount)
    {
        $listAccountsResponse = $this->getJson('/api/list-users?usernamelike='.($keyword), [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $listAccountsResponse->assertOk()->assertJsonPath('total', $expectedResultsCount);
    }

    #[DataProvider('filterRoleParamsProvider')]
    public function test_filter_account_roles(string $role, bool $shouldSuccess)
    {
        $listAccountsResponse = $this->getJson('/api/list-users?role='.($role), [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if ($shouldSuccess) {
            $listAccountsResponse->assertOk();
        } else {
            $listAccountsResponse->assertUnprocessable();
        }
    }

    public function test_cannot_list_accounts_if_user_is_not_admin()
    {
        $readAccountsResponse = $this->getJson('/api/list-users', [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);
        $readAccountsResponse->assertForbidden();
    }

    public function test_cannot_list_accounts_if_user_is_not_logged_in()
    {
        $readAccountsResponse = $this->getJson('/api/list-users');
        $readAccountsResponse->assertUnauthorized();
    }
}
