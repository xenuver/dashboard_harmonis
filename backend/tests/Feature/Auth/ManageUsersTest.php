<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManageUsersTest extends TestCase
{
    use RefreshDatabase;

    private const CREATE_ACCOUNT_USERNAME = 'account';
    private const CREATE_ACCOUNT_PASSWORD = 'account';

    private string $adminToken;
    private string $ownerToken;

    private const OUTCOME_SUCCESS = 0;
    private const OUTCOME_INVALID_INPUT = 1;

    public function setUp(): void
    {
        parent::setUp();

        $this->adminToken = User::factory()->create([
            'role' => 'it_staff',
        ])->createToken('auth_token')->plainTextToken;
        $this->ownerToken = User::factory()->create([
            'role' => 'owner',
        ])->createToken('auth_token')->plainTextToken;
    }

    public static function createAccountInfoProvider(): array
    {
        return [
            'both correct (accepted)' => [
                self::CREATE_ACCOUNT_USERNAME,
                self::CREATE_ACCOUNT_PASSWORD,
                self::OUTCOME_SUCCESS,
            ],
            'invalid username (rejected)' => [
                self::CREATE_ACCOUNT_USERNAME.'#!', // alpha_dash
                self::CREATE_ACCOUNT_PASSWORD,
                self::OUTCOME_INVALID_INPUT,
            ],
            'password with symbol (accepted)' => [
                self::CREATE_ACCOUNT_USERNAME, // alpha_dash
                self::CREATE_ACCOUNT_PASSWORD.'#!',
                self::OUTCOME_SUCCESS,
            ],
            'username empty (rejected)' => [
                null,
                self::CREATE_ACCOUNT_PASSWORD,
                self::OUTCOME_INVALID_INPUT,
            ],
            'password empty (rejected)' => [
                self::CREATE_ACCOUNT_USERNAME,
                null,
                self::OUTCOME_INVALID_INPUT,
            ],
            'both empty (rejected)' => [
                null,
                null,
                self::OUTCOME_INVALID_INPUT,
            ],
        ];
    }

    protected function login(?string $username, ?string $password): TestResponse
    {
        $body = [];

        if($username) {
            $body['username'] = $username;
        }

        if($password) {
            $body['password'] = $password;
        }

        return $this->post('/api/login', $body);
    }

    #[DataProvider('createAccountInfoProvider')]
    public function test_create_login_and_delete_account_if(?string $username, ?string $password, int $expectedOutcome)
    {
        // buat akun baru
        $createAccountResponse = $this->post('/api/add-user', [
            'username' => $username,
            'password' => $password,
            'role' => 'owner'
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        Auth::forgetGuards(); // breaks second login if not calling this?

        try {
            if($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
                $createAccountResponse->assertUnprocessable();
                return;
            }
            $createAccountResponse->assertOk();
            $createAccountResponse->assertHeader('Content-Type', 'application/json');
            $createAccountResponse->assertJsonStructure([
                'user' => [
                    'id',
                    'username',
                    'role',
                ]
            ]);
            $createAccountResponseJson = $createAccountResponse->json();

            $createAccountResponse->assertJsonPath(
                'user.username',
                $username,
            );

            $createAccountResponse->assertJsonPath(
                'user.role',
                'owner',
            );

            // login akun baru
            $newUserLoginResponse = $this->login($username, $password);
            $newUserLoginResponse->assertOk();

            // cek info pengguna
            $newUserInfoResponse = $this->get('/api/whoami', [
                'Authorization' => 'Bearer '.$newUserLoginResponse->json(['token']),
            ]);
            $newUserInfoResponse->assertOk();
            $userInfoResponseJson = $newUserInfoResponse->json();

            $newUserInfoResponse->assertJsonPath(
                'id',
                $createAccountResponseJson['user']['id'],
            );

            $newUserInfoResponse->assertJsonPath(
                'username',
                $createAccountResponseJson['user']['username'],
            );

            $newUserInfoResponse->assertJsonPath(
                'role',
                $createAccountResponseJson['user']['role'],
            );
        } catch (AssertionFailedError $e) {
            $createdUser = User::where('username', $username);
            if($createdUser) {
                $createdUser->delete();
            }
            throw $e;
        }
    }

    public function test_cannot_add_account_if_user_is_not_admin() {
        $createAccountResponse = $this->post('/api/add-user', [
            'username' => self::CREATE_ACCOUNT_USERNAME,
            'password' => self::CREATE_ACCOUNT_PASSWORD,
            'role' => 'it_staff'
        ], [
            'Authorization' => 'Bearer '.$this->ownerToken
        ]);
        $createAccountResponse->assertForbidden();
    }

    public function test_cannot_list_accounts_if_user_is_not_admin() {
        $readAccountsResponse = $this->get('/api/list-users', [
            'Authorization' => 'Bearer '.$this->ownerToken
        ]);
        $readAccountsResponse->assertForbidden();
    }

    public function test_cannot_update_account_if_user_is_not_admin() {
        $someone = User::factory()->create([
            'username' => fake()->unique()->userName(),
            'role' => 'owner',
        ]);

        $updateAccountResponse = $this->post('/api/update-user', [
            'id' => $someone->id,
            'password' => fake()->password(),
        ], [
            'Authorization' => 'Bearer '.$this->ownerToken
        ]);
        $updateAccountResponse->assertForbidden();
    }
}