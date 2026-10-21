<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class IdentitySecurityTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_TOKEN = 'identity-security-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sanctum.stateful' => ['localhost:3000'],
        ]);

        $middleware = config(
            'sanctum.middleware.validate_csrf_token',
            ValidateCsrfToken::class,
        );

        // Chạy kiểm tra CSRF thật trong môi trường test.
        $this->app->bind($middleware, function ($app) {
            return new class($app, $app['encrypter']) extends ValidateCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });

        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'Sec-Fetch-Site' => 'same-site',
        ]);
    }

    public function test_spa_login_without_csrf_token_is_rejected(): void
    {
        $user = $this->loginUser();

        $this->withSession(['_token' => self::CSRF_TOKEN])
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'Password123!',
            ])
            ->assertStatus(419);

        $this->assertGuest('web');
    }

    public function test_spa_login_with_wrong_csrf_token_is_rejected(): void
    {
        $user = $this->loginUser();

        $this->withSession(['_token' => self::CSRF_TOKEN])
            ->withHeader('X-CSRF-TOKEN', 'incorrect-token')
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'Password123!',
            ])
            ->assertStatus(419);

        $this->assertGuest('web');
    }

    public function test_spa_login_with_valid_csrf_token_succeeds(): void
    {
        $user = $this->loginUser();

        $this->withSession(['_token' => self::CSRF_TOKEN])
            ->withHeader('X-CSRF-TOKEN', self::CSRF_TOKEN)
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'Password123!',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_tampered_verification_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $url = preg_replace(
            '/([?&]signature=)[^&]+/',
            '${1}'.str_repeat('0', 64),
            $url,
        );

        $this->getJson($this->relativeUrl($url))
            ->assertForbidden();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_expired_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($this->relativeUrl($url))
            ->assertForbidden();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_signed_link_with_wrong_email_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        // Chữ ký hợp lệ nhưng hash email không đúng.
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            [
                'id' => $user->id,
                'hash' => sha1('different-email@example.test'),
            ],
        );

        $this->getJson($this->relativeUrl($url))
            ->assertForbidden();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    private function loginUser(): User
    {
        return User::factory()->create([
            'password_hash' => 'Password123!',
        ]);
    }

    private function relativeUrl(string $url): string
    {
        return parse_url($url, PHP_URL_PATH)
            .'?'.parse_url($url, PHP_URL_QUERY);
    }
}
