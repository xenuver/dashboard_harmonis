<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AddUserTest extends TestCase
{
    use RefreshDatabase;

    private const CREATE_ACCOUNT_USERNAME = 'account';

    private const CREATE_ACCOUNT_PASSWORD = 'account';

    private string $adminToken;

    private string $ownerToken;

    private const OUTCOME_SUCCESS = 0;

    private const OUTCOME_INVALID_INPUT = 1;

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

    public static function createAccountEnabledInfoProvider(): array
    {
        return [
            'enabled true (accepted)' => [
                true,
                self::OUTCOME_SUCCESS,
            ],
            'enabled false (accepted)' => [
                false,
                self::OUTCOME_SUCCESS,
            ],
            'enabled integer 1 (accepted)' => [
                1,
                self::OUTCOME_SUCCESS,
            ],
            'enabled integer 0 (accepted)' => [
                0,
                self::OUTCOME_SUCCESS,
            ],
            'enabled invalid string (rejected)' => [
                'yes',
                self::OUTCOME_INVALID_INPUT,
            ],
            'enabled null (rejected)' => [
                null,
                self::OUTCOME_INVALID_INPUT,
            ],
        ];
    }

    public static function createAccountRoleInfoProvider(): array
    {
        return [
            'owner role (accepted)' => [
                'owner',
                self::OUTCOME_SUCCESS,
            ],
            'it staff role (accepted)' => [
                'it_staff',
                self::OUTCOME_SUCCESS,
            ],
            'invalid role (rejected)' => [
                'admin',
                self::OUTCOME_INVALID_INPUT,
            ],
            'empty role (rejected)' => [
                null,
                self::OUTCOME_INVALID_INPUT,
            ],
            'uppercase role (rejected)' => [
                'OWNER',
                self::OUTCOME_INVALID_INPUT,
            ],
        ];
    }

    public function test_create_account_with_only_required()
    {
        // buat akun baru
        $createAccountResponse = $this->postJson('/api/add-user', [
            'username' => self::CREATE_ACCOUNT_USERNAME,
            'password' => self::CREATE_ACCOUNT_PASSWORD,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $createAccountResponse->assertOk();
        $createAccountResponse->assertJsonStructure([
            'user' => [
                'id',
                'username',
                'enabled',
                'role',
            ],
        ]);
        $createAccountResponseJson = $createAccountResponse->json();

        $createAccountResponse->assertJsonPath(
            'user.username',
            self::CREATE_ACCOUNT_USERNAME,
        );
        $createAccountResponse->assertJsonPath('user.enabled', function ($value) {
            return is_bool($value);
        });
        $createAccountResponse->assertJsonPath('user.role', function ($value) {
            return in_array($value, ['owner', 'it_staff']);
        });

        $this->assertDatabaseHas('users', [
            'id' => $createAccountResponseJson['user']['id'],
            'username' => $createAccountResponseJson['user']['username'],
            'role' => $createAccountResponseJson['user']['role'],
        ]);

        $newUser = User::where('id', $createAccountResponseJson['user']['id'])->firstOrFail();

        $this->assertTrue(Hash::check(self::CREATE_ACCOUNT_PASSWORD, $newUser->password));

        $this->assertTrue(is_bool($newUser->enabled));
        $this->assertTrue(in_array($newUser->role, ['owner', 'it_staff']));
    }

    #[DataProvider('createAccountInfoProvider')]
    public function test_create_account_with(?string $username, ?string $password, int $expectedOutcome)
    {
        // buat akun baru
        $createAccountResponse = $this->postJson('/api/add-user', [
            'username' => $username,
            'password' => $password,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        if ($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $createAccountResponse->assertUnprocessable();

            return;
        }
        $createAccountResponse->assertOk();
        $createAccountResponse->assertJsonStructure([
            'user' => [
                'id',
                'username',
            ],
        ]);
        $createAccountResponseJson = $createAccountResponse->json();

        $createAccountResponse->assertJsonPath(
            'user.username',
            $username,
        );

        $this->assertDatabaseHas('users', [
            'id' => $createAccountResponseJson['user']['id'],
            'username' => $createAccountResponseJson['user']['username'],
        ]);

        $newUser = User::where('id', $createAccountResponseJson['user']['id'])->firstOrFail();

        $this->assertTrue(Hash::check($password, $newUser->password));
    }

    #[DataProvider('createAccountEnabledInfoProvider')]
    public function test_create_account_enabled_disabled(mixed $enabled, int $expectedOutcome)
    {
        // buat akun baru
        $createAccountResponse = $this->postJson('/api/add-user', [
            'username' => self::CREATE_ACCOUNT_USERNAME,
            'password' => self::CREATE_ACCOUNT_PASSWORD,
            'role' => 'owner',
            'enabled' => $enabled,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        if ($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $createAccountResponse->assertUnprocessable();

            return;
        }
        $createAccountResponse->assertOk();
        $createAccountResponse->assertJsonStructure([
            'user' => [
                'id',
                'enabled',
            ],
        ]);
        $createAccountResponseJson = $createAccountResponse->json();
        if ($enabled == 1 || $enabled == true) {
            $createAccountResponse->assertJsonPath(
                'user.enabled',
                true,
            );
        } else {
            $createAccountResponse->assertJsonPath(
                'user.enabled',
                false,
            );
        }

        $this->assertDatabaseHas('users', [
            'id' => $createAccountResponseJson['user']['id'],
            'enabled' => $createAccountResponseJson['user']['enabled'],
        ]);
    }

    #[DataProvider('createAccountRoleInfoProvider')]
    public function test_create_account_with_role(mixed $role, int $expectedOutcome)
    {
        // buat akun baru
        $createAccountResponse = $this->postJson('/api/add-user', [
            'username' => self::CREATE_ACCOUNT_USERNAME,
            'password' => self::CREATE_ACCOUNT_PASSWORD,
            'role' => $role,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        if ($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $createAccountResponse->assertUnprocessable();

            return;
        }
        $createAccountResponse->assertOk();
        $createAccountResponse->assertJsonStructure([
            'user' => [
                'id',
                'role',
            ],
        ]);
        $createAccountResponseJson = $createAccountResponse->json();
        $createAccountResponse->assertJsonPath(
            'user.role',
            $role,
        );

        $this->assertDatabaseHas('users', [
            'id' => $createAccountResponseJson['user']['id'],
            'role' => $createAccountResponseJson['user']['role'],
        ]);
    }

    public function test_cannot_add_account_if_user_is_not_admin()
    {
        $createAccountResponse = $this->postJson('/api/add-user', [
            'username' => self::CREATE_ACCOUNT_USERNAME,
            'password' => self::CREATE_ACCOUNT_PASSWORD,
        ], [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);
        $createAccountResponse->assertForbidden();
    }
}
