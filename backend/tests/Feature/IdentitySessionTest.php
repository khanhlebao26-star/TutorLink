<?php

namespace Tests\Feature;

use App\Actions\Auth\RevokeUserSessions;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class IdentitySessionTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'Password123!';

    private const NEW_PASSWORD = 'NewPassword123!';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
            'session.table' => 'sessions',
            'sanctum.stateful' => ['localhost:3000'],
        ]);

        // Cho phép các JSON request gửi cookie trong test.
        $this->withCredentials();
    }

    public function test_login_and_logout_use_real_session_cookies(): void
    {
        $user = $this->createUser();
        $cookies = $this->loginBrowser($user);
        $oldCookies = $cookies;

        $this->browserRequest($cookies, 'GET', '/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->browserRequest(
            $cookies,
            'POST',
            '/api/v1/auth/logout',
        )->assertNoContent();

        $this->browserRequest($cookies, 'GET', '/api/v1/me')
            ->assertUnauthorized();

        $this->browserRequest($oldCookies, 'GET', '/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_password_change_keeps_a_and_revokes_b_and_old_a(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();

        $a = $this->loginBrowser($user, remember: true);
        $b = $this->loginBrowser($user, remember: true);
        $otherCookies = $this->loginBrowser($other);

        $oldA = $a;
        $oldRemember = $this->rememberCookies($b);
        $oldToken = $user->refresh()->remember_token;

        $this->browserRequest(
            $a,
            'PUT',
            '/api/v1/me/password',
            [
                'current_password' => self::OLD_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ],
        )->assertNoContent();

        $this->assertTrue(
            Hash::check(
                self::NEW_PASSWORD,
                $user->refresh()->password_hash,
            ),
        );
        $this->assertNotSame($oldToken, $user->remember_token);

        $this->browserRequest($a, 'GET', '/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->browserRequest($b, 'GET', '/api/v1/me')
            ->assertUnauthorized();

        $this->browserRequest($oldA, 'GET', '/api/v1/me')
            ->assertUnauthorized();

        $this->browserRequest($oldRemember, 'GET', '/api/v1/me')
            ->assertUnauthorized();

        $this->browserRequest($otherCookies, 'GET', '/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $other->id);
    }

    public function test_password_reset_revokes_current_and_other_sessions(): void
    {
        $user = $this->createUser();
        $a = $this->loginBrowser($user, remember: true);
        $b = $this->loginBrowser($user, remember: true);

        $oldA = $a;
        $oldRemember = $this->rememberCookies($b);
        $oldToken = $user->refresh()->remember_token;
        $token = Password::broker()->createToken($user);

        $this->browserRequest(
            $a,
            'POST',
            '/api/v1/auth/reset-password',
            $this->resetPayload($user, $token),
        )->assertOk();

        $this->assertNotSame(
            $oldToken,
            $user->refresh()->remember_token,
        );

        $this->assertDatabaseMissing('sessions', [
            'user_id' => $user->id,
        ]);

        $this->browserRequest($a, 'GET', '/api/v1/me')
            ->assertUnauthorized();

        $this->browserRequest($oldA, 'GET', '/api/v1/me')
            ->assertUnauthorized();

        $this->browserRequest($b, 'GET', '/api/v1/me')
            ->assertUnauthorized();

        $this->browserRequest($oldRemember, 'GET', '/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_resetting_b_does_not_log_out_a(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $a = $this->loginBrowser($userA);
        $b = $this->loginBrowser($userB);
        $token = Password::broker()->createToken($userB);

        $this->browserRequest(
            $a,
            'POST',
            '/api/v1/auth/reset-password',
            $this->resetPayload($userB, $token),
        )->assertOk();

        $this->browserRequest($a, 'GET', '/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $userA->id);

        $this->browserRequest($b, 'GET', '/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_suspend_and_restore_do_not_restore_old_credentials(): void
    {
        $admin = User::factory()->admin()->create([
            'password_hash' => self::OLD_PASSWORD,
        ]);
        $user = $this->createUser();

        $adminCookies = $this->loginBrowser($admin);
        $a = $this->loginBrowser($user, remember: true);
        $b = $this->loginBrowser($user);

        $oldRemember = $this->rememberCookies($a);
        $oldToken = $user->refresh()->remember_token;

        $this->browserRequest(
            $adminCookies,
            'POST',
            "/api/v1/admin/users/{$user->id}/suspend",
            ['reason' => 'Session revocation test.'],
        )->assertOk();

        $this->assertSame('suspended', $user->refresh()->status);
        $this->assertNotSame($oldToken, $user->remember_token);

        $this->assertDatabaseMissing('sessions', [
            'user_id' => $user->id,
        ]);

        // Admin không bị thu hồi phiên của mình.
        $this->browserRequest($adminCookies, 'GET', '/api/v1/me')
            ->assertOk();

        $this->browserRequest(
            $adminCookies,
            'POST',
            "/api/v1/admin/users/{$user->id}/restore",
            ['reason' => 'Restore after session revocation.'],
        )->assertOk();

        $this->assertSame('active', $user->refresh()->status);

        // Kiểm tra sau restore để tránh chỉ bị chặn bởi status.
        $this->browserRequest($a, 'GET', '/api/v1/me')
            ->assertUnauthorized();

        $this->browserRequest($b, 'GET', '/api/v1/me')
            ->assertUnauthorized();

        $this->browserRequest($oldRemember, 'GET', '/api/v1/me')
            ->assertUnauthorized();

        $freshCookies = $this->loginBrowser($user);

        $this->browserRequest($freshCookies, 'GET', '/api/v1/me')
            ->assertOk();
    }

    public function test_revocation_action_only_removes_target_sessions(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();

        foreach ([
            ['id' => 'target-a', 'user_id' => $user->id],
            ['id' => 'target-b', 'user_id' => $user->id],
            ['id' => 'other-a', 'user_id' => $other->id],
        ] as $session) {
            DB::table('sessions')->insert($session + [
                'payload' => base64_encode(serialize([])),
                'last_activity' => time(),
            ]);
        }

        app(RevokeUserSessions::class)->handle($user, 'target-a');

        $this->assertDatabaseHas('sessions', ['id' => 'target-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-b']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-a']);

        app(RevokeUserSessions::class)->handle($user);

        $this->assertDatabaseMissing('sessions', [
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('sessions', ['id' => 'other-a']);
    }

    public function test_audit_failure_rolls_back_suspend_and_revocation(): void
    {
        $admin = User::factory()->admin()->create([
            'password_hash' => self::OLD_PASSWORD,
        ]);
        $user = $this->createUser();

        $adminCookies = $this->loginBrowser($admin);
        $cookies = $this->loginBrowser($user);
        $oldToken = $user->refresh()->remember_token;

        $sessionIds = DB::table('sessions')
            ->where('user_id', $user->id)
            ->pluck('id')
            ->all();

        $this->assertNotEmpty($sessionIds);

        DB::listen(function (QueryExecuted $query): void {
            if (
                preg_match(
                    '/^\s*insert\s+into\s+["`]?audit_logs["`]?/i',
                    $query->sql,
                )
            ) {
                throw new RuntimeException('Synthetic audit failure.');
            }
        });

        $this->browserRequest(
            $adminCookies,
            'POST',
            "/api/v1/admin/users/{$user->id}/suspend",
            ['reason' => 'Rollback test.'],
        )->assertStatus(500);

        $this->assertSame('active', $user->refresh()->status);
        $this->assertSame($oldToken, $user->remember_token);

        foreach ($sessionIds as $id) {
            $this->assertDatabaseHas('sessions', [
                'id' => $id,
                'user_id' => $user->id,
            ]);
        }

        $this->browserRequest($cookies, 'GET', '/api/v1/me')
            ->assertOk();
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'password_hash' => self::OLD_PASSWORD,
        ]);
    }

    private function loginBrowser(
        User $user,
        bool $remember = false,
    ): array {
        $cookies = [];

        $this->browserRequest(
            $cookies,
            'POST',
            '/api/v1/auth/login',
            [
                'email' => $user->email,
                'password' => self::OLD_PASSWORD,
                'remember' => $remember,
            ],
        )->assertOk();

        $this->assertArrayHasKey(config('session.cookie'), $cookies);

        $this->browserRequest($cookies, 'GET', '/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->assertDatabaseHas('sessions', [
            'user_id' => $user->id,
        ]);

        return $cookies;
    }

    private function resetPayload(User $user, string $token): array
    {
        return [
            'email' => $user->email,
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    private function rememberCookies(array $cookies): array
    {
        $remember = array_filter(
            $cookies,
            fn(string $name): bool => str_starts_with(
                $name,
                'remember_web_',
            ),
            ARRAY_FILTER_USE_KEY,
        );

        $this->assertNotEmpty(
            $remember,
            'Login remember=true must issue a remember cookie.',
        );

        return $remember;
    }

    private function browserRequest(
        array &$cookies,
        string $method,
        string $uri,
        array $data = [],
    ): TestResponse {
        // Mỗi request phải xác thực lại từ cookie,
        // không giữ user/session trong bộ nhớ từ request trước.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        // DatabaseSessionHandler dùng singleton này để ghi user_id.
        $this->app->forgetInstance('auth.driver');

        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');

        foreach ($this->app['cookie']->getQueuedCookies() as $cookie) {
            $this->app['cookie']->unqueue(
                $cookie->getName(),
                $cookie->getPath(),
            );
        }

        $this->defaultCookies = [];
        $this->unencryptedCookies = $cookies;

        // Cookie lấy từ response đã mã hóa, không mã hóa thêm lần nữa.
        $response = $this->json($method, $uri, $data, [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ]);

        foreach ($response->headers->getCookies() as $cookie) {
            $expires = $cookie->getExpiresTime();

            if ($expires !== 0 && $expires <= time()) {
                unset($cookies[$cookie->getName()]);
            } else {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $response;
    }
}