<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRoutesTest extends TestCase
{
    public function test_api_routes_are_registered(): void
    {
        $expected = [
            ['GET', 'api/v1/health'],
            ['GET', 'api/v1/metrics'],
            ['GET', 'api/v1/metrics/prometheus'],
            ['POST', 'api/v1/notifications'],
            ['GET', 'api/v1/notifications'],
            ['GET', 'api/v1/notifications/{notificationId}'],
            ['POST', 'api/v1/notifications/{notificationId}/cancel'],
            ['GET', 'api/v1/batches/{batchId}'],
            ['POST', 'api/v1/batches/{batchId}/cancel'],
            ['GET', 'api/v1/dead-letter'],
            ['POST', 'api/v1/dead-letter/requeue'],
            ['GET', 'api/v1/dead-letter/{deadLetterId}'],
            ['POST', 'api/v1/dead-letter/{deadLetterId}/requeue'],
            ['POST', 'api/v1/templates'],
            ['GET', 'api/v1/templates'],
            ['POST', 'api/v1/templates/preview'],
            ['GET', 'api/v1/templates/{templateId}'],
            ['PATCH', 'api/v1/templates/{templateId}'],
            ['DELETE', 'api/v1/templates/{templateId}'],
        ];

        foreach ($expected as [$method, $uri]) {
            $this->assertTrue(
                $this->routeExists($method, $uri),
                "Failed asserting that route {$method} {$uri} is registered."
            );
        }
    }

    private function routeExists(string $method, string $uri): bool
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() !== $uri) {
                continue;
            }

            if (in_array($method, $route->methods(), true)) {
                return true;
            }
        }

        return false;
    }
}
