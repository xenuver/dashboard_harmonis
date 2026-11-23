<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT_USERNAME = 'admin';

    private const ACCOUNT_PASSWORD = 'admin';

    private const DISABLED_ACCOUNT_USERNAME = 'locked';

    private const DISABLED_ACCOUNT_PASSWORD = 'locked';

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create([
            'username' => self::ACCOUNT_USERNAME,
            'password' => Hash::make(self::ACCOUNT_PASSWORD),
            'enabled' => true,
            'role' => 'it_staff',
        ]);

        User::factory()->create([
            'username' => self::DISABLED_ACCOUNT_USERNAME,
            'password' => Hash::make(self::DISABLED_ACCOUNT_PASSWORD),
            'enabled' => false,
            'role' => 'it_staff',
        ]);
    }

    public static function invalidCredentialsProvider(): array
    {
        return [
            'invalid password' => [
                self::ACCOUNT_USERNAME,
                self::ACCOUNT_PASSWORD.'1',
            ],
            'unknown username' => [
                self::ACCOUNT_USERNAME.'1',
                self::ACCOUNT_PASSWORD,
            ],
        ];
    }

    public static function invalidValidationProvider(): array
    {
        return [
            'username missing' => [
                null,
                self::ACCOUNT_PASSWORD,
                ['username'],
            ],
            'password missing' => [
                self::ACCOUNT_USERNAME,
                null,
                ['password'],
            ],
            'both missing' => [
                null,
                null,
                ['username', 'password'],
            ],
            'username is empty string' => [
                '',
                self::ACCOUNT_PASSWORD,
                ['username'],
            ],
            'password is empty string' => [
                self::ACCOUNT_USERNAME,
                '',
                ['password'],
            ],
            'username is array' => [
                ['admin'],
                self::ACCOUNT_PASSWORD,
                ['username'],
            ],
            'password is array' => [
                self::ACCOUNT_USERNAME,
                ['admin'],
                ['password'],
            ],
        ];
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'username' => self::ACCOUNT_USERNAME,
            'password' => self::ACCOUNT_PASSWORD,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'user' => [
                'id',
                'username',
                'role',
            ],
            'token',
        ]);

        $response->assertJsonPath('user.username', self::ACCOUNT_USERNAME);
        $response->assertJsonPath('user.role', 'it_staff');
        $response->assertJsonMissing(['password']);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // Verify the token actually authenticates to protected endpoints
        $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('username', self::ACCOUNT_USERNAME);

        // Verify token in database has 1-hour expiration
        $personalAccessToken = PersonalAccessToken::findToken($token);
        $this->assertNotNull($personalAccessToken);
        $this->assertTrue($personalAccessToken->expires_at->isFuture());
        $this->assertEquals(60, (int) round(now()->diffInMinutes($personalAccessToken->expires_at)));
    }

    #[DataProvider('invalidCredentialsProvider')]
    public function test_cannot_login_with_invalid_credentials(string $username, string $password): void
    {
        $response = $this->postJson('/api/login', [
            'username' => $username,
            'password' => $password,
        ]);

        $response->assertUnauthorized();
        $response->assertJson([
            'message' => 'Username atau password salah',
        ]);
    }

    #[DataProvider('invalidValidationProvider')]
    public function test_login_validation_fails_with_invalid_input(mixed $username, mixed $password, array $expectedErrors): void
    {
        $body = [];

        if ($username !== null) {
            $body['username'] = $username;
        }

        if ($password !== null) {
            $body['password'] = $password;
        }

        $response = $this->postJson('/api/login', $body);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors($expectedErrors);
    }

    public function test_cannot_login_if_user_disabled(): void
    {
        $response = $this->postJson('/api/login', [
            'username' => self::DISABLED_ACCOUNT_USERNAME,
            'password' => self::DISABLED_ACCOUNT_PASSWORD,
        ]);

        $response->assertUnauthorized();
        $response->assertJson([
            'message' => 'Username atau password salah',
        ]);
    }

    public function test_login_token_expires_after_one_hour(): void
    {
        $loginResponse = $this->postJson('/api/login', [
            'username' => self::ACCOUNT_USERNAME,
            'password' => self::ACCOUNT_PASSWORD,
        ]);

        $loginResponse->assertOk();
        $token = $loginResponse->json('token');

        // 50 minutes later: still valid
        $this->travel(50)->minutes();
        $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        // 12 more minutes later (62 minutes total): expired
        $this->travel(12)->minutes();
        Auth::forgetGuards();

        $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ])->assertUnauthorized();
    }
}
