<?php

namespace Tests\Support;

use App\Models\User;
use App\Support\RealtimeSessionChannels;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait InteractsWithRealtimeSessions
{
    protected function configureRealtimeTests(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
            'session.table' => 'sessions',
            'sanctum.stateful' => ['localhost:3000'],
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'test-reverb-key',
                'secret' => 'test-reverb-secret',
                'app_id' => 'test-reverb-app',
                'options' => [
                    'host' => '127.0.0.1',
                    'port' => 8080,
                    'scheme' => 'http',
                    'useTLS' => false,
                ],
                'client_options' => [
                    'connect_timeout' => 2,
                    'timeout' => 5,
                ],
            ],
        ]);

        $this->withCredentials();

        Broadcast::forgetDrivers();

        require base_path('routes/channels.php');
    }

    /** @return array<string,string> */
    protected function loginBrowser(User $user): array
    {
        $user->refresh();

        if (! Hash::check('Password123!', $user->getAuthPassword())) {
            $user->forceFill(['password_hash' => 'Password123!'])->save();
        }

        $cookies = [];

        $this->browserRequest(
            $cookies,
            'POST',
            '/api/v1/auth/login',
            [
                'email' => $user->email,
                'password' => 'Password123!',
            ],
        )->assertOk();

        $this->browserRequest($cookies, 'GET', '/api/v1/me')
            ->assertOk();

        return $cookies;
    }

    protected function browserRequest(
        array &$cookies,
        string $method,
        string $uri,
        array $data = [],
    ): TestResponse {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
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

    /** @return array{channel:string,event:string,expires_at:string} */
    protected function issueChannel(array &$cookies, int $chat): array
    {
        return $this->browserRequest(
            $cookies,
            'POST',
            '/api/v1/conversations/'.$chat.'/realtime',
        )->assertOk()->json('data');
    }

    protected function authorizeChannel(
        array &$cookies,
        string $channel,
        string $socket = '123.456',
    ): TestResponse {
        return $this->browserRequest(
            $cookies,
            'POST',
            '/broadcasting/auth',
            [
                'socket_id' => $socket,
                'channel_name' => 'private-'.$channel,
            ],
        );
    }

    /**
     * Synthetic session fixture for worker tests; not a browser login.
     *
     * @return array{session_id:string,token:string}
     */
    protected function realtimeFixture(User $user): array
    {
        $sessionId = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);

        $grant = DB::transaction(function () use ($user, $sessionId) {
            DB::table('users')
                ->where('id', $user->id)
                ->lockForUpdate()
                ->first();

            return app(RealtimeSessionChannels::class)->grant(
                (int) $user->id,
                $sessionId,
            );
        });

        return [
            'session_id' => $sessionId,
            'token' => $grant->channel_token,
        ];
    }
}
