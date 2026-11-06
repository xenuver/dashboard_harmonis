<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserInformationTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_USERNAME = 'admin';

    private const ADMIN_EXPECTED_ROLE = 'it_staff';

    private const OWNER_USERNAME = 'owner';

    private const OWNER_EXPECTED_ROLE = 'owner';

    private const TEST_IT_STAFF_USER_INFO = 0;

    private const TEST_OWNER_USER_INFO = 1;

    protected User $adminUser;

    protected User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('username', self::ADMIN_USERNAME)->exists()) {
            User::where('username', self::ADMIN_USERNAME)->delete();
        }
        $this->adminUser = User::factory()->create([
            'username' => self::ADMIN_USERNAME,
            'role' => self::ADMIN_EXPECTED_ROLE,
        ]);

        $this->ownerUser = User::factory()->create([
            'username' => self::OWNER_USERNAME,
            'role' => self::OWNER_EXPECTED_ROLE,
        ]);
    }

    public static function userInformationProvider(): array
    {
        return [
            'akun staf it' => [
                self::TEST_IT_STAFF_USER_INFO,
            ],
            'akun owner' => [
                self::TEST_OWNER_USER_INFO,
            ],
        ];
    }

    #[DataProvider('userInformationProvider')]
    public function test_can_get_matching_login_and_whoami_value(int $userRole): void
    {
        $loginUser = null;
        $expectedUsername = null;
        $expectedRole = null;

        if ($userRole === self::TEST_IT_STAFF_USER_INFO) {
            $loginUser = $this->adminUser;
            $expectedUsername = self::ADMIN_USERNAME;
            $expectedRole = self::ADMIN_EXPECTED_ROLE;
        } elseif ($userRole === self::TEST_OWNER_USER_INFO) {
            $loginUser = $this->ownerUser;
            $expectedUsername = self::OWNER_USERNAME;
            $expectedRole = self::OWNER_EXPECTED_ROLE;
        } else {
            $this->assertTrue(false);
        }

        $userToken = $loginUser->createToken('auth_token')->plainTextToken;

        $userInfo = $this->getJson('/api/whoami', [
            'Authorization' => 'Bearer '.$userToken,
        ]);
        $userInfo->assertOk();

        // informasi pengguna ada di respon login
        $userInfo->assertJsonStructure([
            'id',
            'username',
            'role',
        ]);

        $userInfo->assertJsonPath('username', $expectedUsername);
        $userInfo->assertJsonPath('role', $expectedRole);
    }

    public function test_cannot_whoami_while_logged_out(): void
    {
        $userInfo = $this->getJson('/api/whoami');
        $userInfo->assertUnauthorized();
    }
}
