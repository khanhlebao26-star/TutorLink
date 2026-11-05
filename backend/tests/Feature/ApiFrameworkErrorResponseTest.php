<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiFrameworkErrorResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->group(function (): void {
            Route::get('/api/v1/test/framework-error/403', fn () => abort(403));
            Route::get('/api/v1/test/framework-error/409', fn () => abort(409));
            Route::get('/api/v1/test/framework-error/419', fn () => abort(419));
            Route::get('/api/v1/test/framework-error/429', fn () => abort(429, 'Too many requests.', ['Retry-After' => '17']));
            Route::post('/api/v1/test/framework-error/422', function (Request $request) {
                $request->validate(['name' => ['required', 'string']]);

                return response()->noContent();
            });
        });
    }

    public function test_api_framework_errors_share_the_safe_envelope_and_keep_retry_after(): void
    {
        $cases = [
            ['/api/v1/test/framework-error/403', 403, 'permission_denied'],
            ['/api/v1/test/framework-error/409', 409, 'conflict'],
            ['/api/v1/test/framework-error/419', 419, 'csrf_token_mismatch'],
            ['/api/v1/test/framework-error/422', 422, 'validation_failed'],
            ['/api/v1/test/framework-error/429', 429, 'rate_limited'],
            ['/api/v1/test/framework-error/not-found', 404, 'resource_not_found'],
        ];

        foreach ($cases as [$path, $status, $code]) {
            $response = $status === 422
                ? $this->postJson($path, [])
                : $this->getJson($path);

            $response->assertStatus($status)
                ->assertJsonPath('code', $code)
                ->assertJsonStructure(['code', 'message', 'request_id', 'errors'])
                ->assertHeader('X-Request-ID', $response->json('request_id'))
                ->assertHeader('Cache-Control', 'no-store, private');

            if ($status === 429) {
                $response->assertHeader('Retry-After', '17');
            }
        }
    }
}
