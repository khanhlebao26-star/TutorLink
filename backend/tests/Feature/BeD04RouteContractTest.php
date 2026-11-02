<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class BeD04RouteContractTest extends TestCase
{
    #[DataProvider('endpointCases')]
    public function test_contract_endpoint_is_registered(string $caseId, string $method, string $path): void
    {
        /** @var Router $router */
        $router = app(Router::class);

        try {
            $route = $router->getRoutes()->match(Request::create($path, $method));
        } catch (NotFoundHttpException) {
            $this->fail("{$caseId}: missing route {$method} {$path}");
        }

        $this->assertContains($method, $route->methods(), "{$caseId}: method mismatch for {$path}");
    }

    public static function endpointCases(): array
    {
        return [
            'OV-01 overview read' => ['OV-01', 'GET', '/api/v1/programs/5/overview'],
            'PR-01 list progress' => ['PR-01', 'GET', '/api/v1/programs/5/progress-records'],
            'PR-02 create progress' => ['PR-02', 'POST', '/api/v1/programs/5/progress-records'],
            'PR-03 update progress' => ['PR-03', 'PATCH', '/api/v1/progress-records/1'],
            'AN-01 list announcements' => ['AN-01', 'GET', '/api/v1/programs/5/announcements'],
            'AN-02 read announcement' => ['AN-02', 'GET', '/api/v1/announcements/1'],
            'AN-03 create draft' => ['AN-03', 'POST', '/api/v1/programs/5/announcements'],
            'AN-04 update draft' => ['AN-04', 'PATCH', '/api/v1/announcements/1'],
            'AN-05 publish announcement' => ['AN-05', 'POST', '/api/v1/announcements/1/publish'],
            'FE-01 list fee records' => ['FE-01', 'GET', '/api/v1/programs/5/fee-records'],
            'FE-02 create fee record' => ['FE-02', 'POST', '/api/v1/programs/5/fee-records'],
            'FE-03 read fee record' => ['FE-03', 'GET', '/api/v1/fee-records/1'],
            'FE-04 update fee record' => ['FE-04', 'PATCH', '/api/v1/fee-records/1'],
            'FE-05 mark fee paid' => ['FE-05', 'POST', '/api/v1/fee-records/1/mark-paid'],
            'FE-06 mark fee unpaid' => ['FE-06', 'POST', '/api/v1/fee-records/1/mark-unpaid'],
            'FE-07 void fee record' => ['FE-07', 'POST', '/api/v1/fee-records/1/void'],
        ];
    }
}
