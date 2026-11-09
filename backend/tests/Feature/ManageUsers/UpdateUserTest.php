<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private string $ownerToken;

    private const OUTCOME_SUCCESS = 0;

    private const OUTCOME_INVALID_INPUT = 1;

    private const BASE_PROVIDER = [ /// case null dan string kosong bisa ada di body
        'literal null (rejected)' => [
            null,
            self::OUTCOME_INVALID_INPUT,
        ],
        'empty string (rejected)' => [
            '',
            self::OUTCOME_INVALID_INPUT,
        ]
    ];

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

    public static function updateAccountOnlyOneFieldProvider(): array
    {
        return [
            'username' => ['username', fake()->unique()->regexify('[a-zA-Z0-9_-]{4,8}')],
            'password' => ['password', fake()->password()],
            'role' => ['role', 'it_staff'],
            'enabled' => ['enabled', false],
        ];
    }

    public static function updateUsernameProvider() 
    {
        // username tidak diacak supaya bisa bisa diprediksi
        $acceptedInputCases = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '_', '-'];
        $invalidInputCases = ['.', ',', '/', '\\', '!', '~', '#', '+', '|', '$', '?', '&', ':', ';', '{', '}', '(', ')', '[', ']', '\"', '\''];

        $cases = array_merge(self::BASE_PROVIDER, [
            'only lowercase letters (accepted)' => [
                'johnpork',
                self::OUTCOME_SUCCESS,
            ],
            'only uppercase letters (accepted)' => [
                'JOHNPORK',
                self::OUTCOME_SUCCESS,
            ],
            'mixed letters (accepted)' => [
                'JOHNPORK',
                self::OUTCOME_SUCCESS,
            ],
            'has whitespace (rejected)' => [
                'john'.' '.'pork',
                self::OUTCOME_INVALID_INPUT,
            ],
            'has yet another whitespace (rejected)' => [
                'john'.' '.'pork',
                self::OUTCOME_INVALID_INPUT,
            ],
        ]);

        foreach($acceptedInputCases as $value) {
            $cases["has '{$value}' symbol (accepted)"] = [
                'john'.($value).'pork',
                self::OUTCOME_SUCCESS,
            ];
        }

        foreach($invalidInputCases as $value) {
            $cases["has '{$value}' symbol (rejected)"] = [
                'john'.($value).'pork',
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        return $cases;
    }

    public static function updatePasswordProvider() 
    {
        return array_merge(self::BASE_PROVIDER, [
            'valid password (>= 4 symbols, accepted)' => [
                fake()->password(4, 8),
                self::OUTCOME_SUCCESS,
            ],
            'long password (>= 2 symbols, accepted)' => [
                fake()->password(20, 30),
                self::OUTCOME_SUCCESS,
            ],
            'too short password (< 4 symbols, rejected)' => [
                fake()->password(1, 3),
                self::OUTCOME_INVALID_INPUT,
            ],
        ]);
    }

    public static function updateRoleProvider() 
    {
        return array_merge(self::BASE_PROVIDER, [
            'set to it_staff (accepted)' => [
                'it_staff',
                self::OUTCOME_SUCCESS,
            ],
            'set to owner (accepted)' => [
                'owner',
                self::OUTCOME_SUCCESS,
            ],
            'set to invalid role (rejected)' => [
                'guest',
                self::OUTCOME_INVALID_INPUT,
            ],
        ]);
    }

    public static function updateAccountEnabledProvider() 
    {
        $acceptedInputCasesLiteral = [true, false];
        $acceptedInputCases = [0, 1];
        $invalidInputCases = [2, 43, 100, -1, -6];

        $cases = array_merge(self::BASE_PROVIDER, []);

        foreach(array_merge($acceptedInputCasesLiteral, $acceptedInputCases) as $value) {
            $cases["set to '".(json_encode($value))."' (accepted)"] = [
                $value,
                self::OUTCOME_SUCCESS,
            ];
        }

        foreach($acceptedInputCases as $value) {
            $cases["set to '".(json_encode($value))."' string (accepted)"] = [
                strval($value),
                self::OUTCOME_SUCCESS,
            ];
        }

        foreach($invalidInputCases as $value) {
            $cases["set to '".(json_encode($value))."' (rejected)"] = [
                $value,
                self::OUTCOME_INVALID_INPUT,
            ];
            $cases["set to '".(json_encode($value))."' string (rejected)"] = [
                strval($value),
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        return $cases;
    }

    #[DataProvider('updateAccountOnlyOneFieldProvider')]
    public function test_update_account_only(string $field, mixed $fieldValue)
    {
        $oldUsername = fake()->unique()->regexify('[a-zA-Z0-9_-]{4,8}');
        $oldPassword = fake()->password();
        $oldRole = 'owner';
        $oldAccountEnabled = true;

        $someone = User::factory()->create([
            'username' => $oldUsername,
            'password' => $oldPassword,
            'role' => $oldRole,
            'enabled' => $oldAccountEnabled,
        ]);

        $original = $someone->getAttributes();

        $updateAccountResponse = $this->postJson('/api/update-user', [
            'userid' => $someone->id,
            $field => $fieldValue,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $updateAccountResponse->assertOk();

        $someone->refresh();

        if($field !== 'password') {
            $this->assertEquals($fieldValue, $someone->$field);
        }

        foreach ($original as $key => $value) {
            if ($field === 'password' && $key === $field) {
                continue;
            }
            if ($key === $field) {
                continue;
            }

            $this->assertEquals($value, $someone->$key);
        }

        if($field === 'password') {
            $this->assertFalse(Hash::check($oldPassword, $someone->password));
            $this->assertTrue(Hash::check($fieldValue, $someone->password));
        } else {
            $this->assertTrue(Hash::check($oldPassword, $someone->password));
        }
    }

    #[DataProvider('updateUsernameProvider')]
    public function test_update_username(mixed $value, int $expectedOutcome)
    {
        $oldUsername = fake()->unique()->regexify('[a-zA-Z0-9_-]{4,8}');

        $someone = User::factory()->create([
            'username' => $oldUsername,
        ]);

        $updateAccountResponse = $this->postJson('/api/update-user', [
            'userid' => $someone->id,
            'username' => $value,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if($expectedOutcome === self::OUTCOME_SUCCESS) {
            $updateAccountResponse->assertOk();

            $this->assertDatabaseHas('users', [
                'id' => $someone->id,
                'username' => $value,
            ]);
        } else if ($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $updateAccountResponse->assertUnprocessable();
            $updateAccountResponse->assertJsonValidationErrors(['username']);

            $this->assertDatabaseHas('users', [
                'id' => $someone->id,
                'username' => $oldUsername,
            ]);
        } else {
            $this->assertTrue(false);
        }
    }

    #[DataProvider('updatePasswordProvider')]
    public function test_update_password(mixed $value, int $expectedOutcome)
    {
        $oldPassword = fake()->password();

        $someone = User::factory()->create([
            'password' => $oldPassword,
        ]);

        $updateAccountResponse = $this->postJson('/api/update-user', [
            'userid' => $someone->id,
            'password' => $value,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if($expectedOutcome === self::OUTCOME_SUCCESS) {
            $updateAccountResponse->assertOk();

            $someone->refresh();
            $this->assertFalse(Hash::check($oldPassword, $someone->password));
            $this->assertTrue(Hash::check($value, $someone->password));
        } else if ($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $updateAccountResponse->assertUnprocessable();
            $updateAccountResponse->assertJsonValidationErrors(['password']);

            $this->assertDatabaseHas('users', [
                'id' => $someone->id,
                'password' => $someone->password,
            ]);

            $someone->refresh();
            $this->assertFalse(Hash::check($value, $someone->password));
            $this->assertTrue(Hash::check($oldPassword, $someone->password));
        } else {
            $this->assertTrue(false);
        }
    }

    #[DataProvider('updateRoleProvider')]
    public function test_update_role(mixed $value, int $expectedOutcome)
    {
        $someone = User::factory()->create([
            'role' => fake()->randomElement(['owner', 'it_staff']),
        ]);

        $updateAccountResponse = $this->postJson('/api/update-user', [
            'userid' => $someone->id,
            'role' => $value,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if($expectedOutcome === self::OUTCOME_SUCCESS) {
            $updateAccountResponse->assertOk();

            $this->assertDatabaseHas('users', [
                'id' => $someone->id,
                'role' => $value,
            ]);
        } else if ($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $updateAccountResponse->assertUnprocessable();
            $updateAccountResponse->assertJsonValidationErrors(['role']);

            $this->assertDatabaseHas('users', [
                'id' => $someone->id,
                'role' => $someone->role,
            ]);
        } else {
            $this->assertTrue(false);
        }
    }

    #[DataProvider('updateAccountEnabledProvider')]
    public function test_update_account_enabled_disabled(mixed $value, int $expectedOutcome)
    {
        $someone = User::factory()->create([
            'enabled' => fake()->randomElement([true, false]),
        ]);

        $updateAccountResponse = $this->postJson('/api/update-user', [
            'userid' => $someone->id,
            'enabled' => $value,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if($expectedOutcome === self::OUTCOME_SUCCESS) {
            $updateAccountResponse->assertOk();

            $this->assertDatabaseHas('users', [
                'id' => $someone->id,
                'enabled' => $value,
            ]);
        } else if ($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $updateAccountResponse->assertUnprocessable();
            $updateAccountResponse->assertJsonValidationErrors(['enabled']);

            $this->assertDatabaseHas('users', [
                'id' => $someone->id,
                'enabled' => $someone->enabled,
            ]);
        } else {
            $this->assertTrue(false);
        }
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
