<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserInformationTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_USERNAME = 'admin';
    private const ADMIN_PASSWORD = 'admin';
    private const ADMIN_EXPECTED_ROLE = 'it_staff';

    private const OWNER_USERNAME = 'owner';
    private const OWNER_PASSWORD = 'owner';
    private const OWNER_EXPECTED_ROLE = 'owner';

    private const TEST_IT_STAFF_USER_INFO = 0;
    private const TEST_OWNER_USER_INFO = 1;

    protected User $adminAccount;

    public function setUp(): void
    {
        parent::setUp();
        if (! User::where('username', self::ADMIN_USERNAME)->exists()) {
            User::where('username', self::ADMIN_USERNAME)->delete();
        }
        User::factory()->create([
            'username' => self::ADMIN_USERNAME,
            'password' => Hash::make(self::ADMIN_PASSWORD),
            'enabled' => true,
            'role' => self::ADMIN_EXPECTED_ROLE,
        ]);
        User::factory()->create([
            'username' => self::OWNER_USERNAME,
            'password' => Hash::make(self::OWNER_PASSWORD),
            'enabled' => true,
            'role' => self::OWNER_EXPECTED_ROLE,
        ]);
    }

    public static function userInformationProvider(): array
    {
        return [
            'akun staf it' => [
                self::TEST_IT_STAFF_USER_INFO
            ],
            'akun owner' => [
                self::TEST_OWNER_USER_INFO
            ],
        ];
    }

    private function whoAmI(string $token): TestResponse
    {
        return $this->get('/api/whoami', [
            'Authorization' => 'Bearer '.$token,
        ]);
    }

    #[DataProvider('userInformationProvider')]
    public function test_can_get_matching_login_and_whoami_value(int $userRole): void
    {
        $loginUsername = null;
        $loginPassword = null;

        if($userRole === self::TEST_IT_STAFF_USER_INFO) {
            $loginUsername = self::ADMIN_USERNAME;
            $loginPassword = self::ADMIN_PASSWORD;
        } else if($userRole === self::TEST_OWNER_USER_INFO) {
            $loginUsername = self::OWNER_USERNAME;
            $loginPassword = self::OWNER_PASSWORD;
        } else {
            $this->assertTrue(false);
        }

        $loginResponse = $this->post('/api/login', [
            'username' => $loginUsername,
            'password' => $loginPassword,
        ]);
        $loginResponse->assertOk();
        $loginResponse->assertHeader('Content-Type', 'application/json');
        $loginResponseJson = $loginResponse->json();

        // informasi pengguna ada di respon login
        $loginResponse->assertJsonStructure([
            'user' => [
                'id',
                'username',
                'role'
            ],
            'token',
        ]);

        // username input === respon username login
        $loginResponse->assertJsonPath(
            'user.username',
            $loginUsername,
        );

        $userInfo = $this->whoAmI($loginResponseJson['token']);
        $userInfo->assertOk();
        $userInfo->assertHeader('Content-Type', 'application/json');

        // informasi pengguna ada di respon login
        $userInfo->assertJsonStructure([
            'id',
            'username',
            'role'
        ]);

        // data endpoint login === data endpoint whoami
        $userInfo->assertJsonPath(
            'id',
            $loginResponseJson['user']['id'],
        );

        $userInfo->assertJsonPath(
            'username',
            $loginResponseJson['user']['username'],
        );

        $userInfo->assertJsonPath(
            'role',
            $loginResponseJson['user']['role'],
        );
    }

    public function test_cannot_whoami_while_logged_out(): void
    {
        $userInfo = $this->get('/api/whoami');
        $userInfo->assertUnauthorized();
    }
}
