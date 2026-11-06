<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT_USERNAME = 'admin';

    private const ACCOUNT_PASSWORD = 'admin';

    private const DISABLED_ACCOUNT_USERNAME = 'locked';

    private const DISABLED_ACCOUNT_PASSWORD = 'locked';

    private const LOGIN_OUTCOME_SUCCESS = 0;

    private const LOGIN_OUTCOME_INVALID_CREDS = 1;

    private const LOGIN_OUTCOME_INVALID_INPUT = 2;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('username', self::ACCOUNT_USERNAME)->exists()) {
            User::where('username', self::ACCOUNT_PASSWORD)->delete();
        }
        User::factory()->create([
            'username' => self::ACCOUNT_USERNAME,
            'password' => Hash::make(self::ACCOUNT_PASSWORD),
            'enabled' => true,
            'role' => 'it_staff',
        ]);
        if (! User::where('username', self::DISABLED_ACCOUNT_USERNAME)->exists()) {
            User::where('username', self::DISABLED_ACCOUNT_PASSWORD)->delete();
        }
        User::factory()->create([
            'username' => self::DISABLED_ACCOUNT_USERNAME,
            'password' => Hash::make(self::DISABLED_ACCOUNT_PASSWORD),
            'enabled' => false,
            'role' => 'it_staff',
        ]);
    }

    public static function loginProvider(): array
    {
        return [
            'both correct' => [
                self::ACCOUNT_USERNAME,
                self::ACCOUNT_PASSWORD,
                self::LOGIN_OUTCOME_SUCCESS,
            ],
            'invalid password' => [
                self::ACCOUNT_USERNAME,
                self::ACCOUNT_PASSWORD.'1',
                self::LOGIN_OUTCOME_INVALID_CREDS,
            ],
            'unknown username' => [
                self::ACCOUNT_USERNAME.'1',
                self::ACCOUNT_PASSWORD,
                self::LOGIN_OUTCOME_INVALID_CREDS,
            ],
            'username empty' => [
                null,
                self::ACCOUNT_PASSWORD,
                self::LOGIN_OUTCOME_INVALID_INPUT,
            ],
            'password empty' => [
                self::ACCOUNT_USERNAME,
                null,
                self::LOGIN_OUTCOME_INVALID_INPUT,
            ],
            'both empty' => [
                null,
                null,
                self::LOGIN_OUTCOME_INVALID_INPUT,
            ],
        ];
    }

    protected function login(?string $username, ?string $password): TestResponse
    {
        $body = [];

        if ($username) {
            $body['username'] = $username;
        }

        if ($password) {
            $body['password'] = $password;
        }

        return $this->postJson('/api/login', $body);
    }

    #[DataProvider('loginProvider')]
    public function test_user_can_login_if(?string $username, ?string $password, int $expectedOutcome)
    {
        $response = $this->login($username, $password);

        if ($expectedOutcome === self::LOGIN_OUTCOME_SUCCESS) {
            $response->assertOk();
            $response->assertJsonStructure([
                'user',
                'token',
            ]);
        } else {
            if ($expectedOutcome === self::LOGIN_OUTCOME_INVALID_CREDS) {
                $response->assertUnauthorized();
            } elseif ($expectedOutcome === self::LOGIN_OUTCOME_INVALID_INPUT) {
                $response->assertUnprocessable();
            }
        }
    }

    public function test_cannot_login_if_user_disabled()
    {
        $response = $this->login(self::DISABLED_ACCOUNT_USERNAME, self::DISABLED_ACCOUNT_PASSWORD);
        $response->assertUnauthorized();
    }
}
