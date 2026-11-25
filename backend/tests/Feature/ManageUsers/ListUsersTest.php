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

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = User::factory()->create([
            'username' => 'sys_admin_tester',
            'role' => 'it_staff',
        ])->createToken('auth_token')->plainTextToken;
        $this->ownerToken = User::factory()->create([
            'username' => 'sys_owner_tester',
            'role' => 'owner',
        ])->createToken('auth_token')->plainTextToken;
    }

    private function seedUsers(int $count): void
    {
        User::factory()->count($count)->sequence(fn ($sequence) => [
            'username' => 'user_'.($sequence->index + 1),
            'role' => $sequence->index % 2 === 0 ? 'owner' : 'it_staff',
        ])->create();
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
        // asumsi ada 60 pengguna
        return [
            'user_ (60 hasil)' => ['user_', 60],
            'user_1 (11 hasil)' => ['user_1', 11],
            'user_456 (0 hasil)' => ['user_456', 0],
            'ser_60 (1 hasil)' => ['ser_60', 1],
        ];
    }

    public static function filterRoleParamsProvider(): array
    {
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
        $readAccountsResponse->assertOk()
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'username',
                        'role',
                        'enabled',
                    ],
                ],
                'first_page_url',
                'from',
                'last_page',
                'per_page',
                'to',
                'total',
            ]);

        $responseData = $readAccountsResponse->json('data');
        $this->assertNotEmpty($responseData);
        foreach ($responseData as $userItem) {
            $this->assertArrayNotHasKey('password', $userItem);
            $this->assertArrayNotHasKey('remember_token', $userItem);
        }
    }

    #[DataProvider('usersPerPageParamsProvider')]
    public function test_list_accounts_per_page(int $perPage, bool $shouldSuccess)
    {
        if ($shouldSuccess) {
            $this->seedUsers($perPage);
        }

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
        $this->seedUsers(60);

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
            $data = $listAccountsResponse->json('data');
            $this->assertNotEmpty($data);
            foreach ($data as $item) {
                $this->assertSame($role, $item['role']);
            }
        } else {
            $listAccountsResponse->assertUnprocessable();
        }
    }

    public function test_list_accounts_sorted_by_username_ascending()
    {
        $this->seedUsers(10);

        $response = $this->getJson('/api/list-users?per_page=50', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $response->assertOk();

        $usernames = array_column($response->json('data'), 'username');
        $sortedUsernames = $usernames;
        sort($sortedUsernames);

        $this->assertSame($sortedUsernames, $usernames);
    }

    public function test_list_accounts_with_combined_filters()
    {
        User::factory()->create(['username' => 'user_owner_match', 'role' => 'owner']);
        User::factory()->create(['username' => 'user_staff_nonmatch', 'role' => 'it_staff']);

        $response = $this->getJson('/api/list-users?usernamelike=user_&role=owner', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('user_owner_match', $data[0]['username']);
        $this->assertSame('owner', $data[0]['role']);
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
