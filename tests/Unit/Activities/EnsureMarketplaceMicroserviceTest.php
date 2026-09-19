<?php

namespace Tests\Unit\Activities;

use App\Http\Middleware\EnsureMarketplaceMicroservice;
use App\Models\MarketplaceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * The activities-module wall: only a marketplace with the microservice active
 * reaches the module's controllers. No app boot, no database: the client is
 * a mock.
 */
class EnsureMarketplaceMicroserviceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function requestFor(?MarketplaceClient $client): Request
    {
        $request = Request::create('/api/marketplace-client/activities-module/status', 'GET');
        if ($client) {
            $request->attributes->set('marketplace_client', $client);
        }
        return $request;
    }

    private function client(array $active): MarketplaceClient
    {
        $client = Mockery::mock(MarketplaceClient::class)->makePartial();
        $client->shouldReceive('hasMicroservice')->andReturnUsing(fn (string $slug) => in_array($slug, $active, true));
        return $client;
    }

    private function callMiddleware(Request $request, string ...$slugs)
    {
        return (new EnsureMarketplaceMicroservice())->handle(
            $request,
            fn () => new JsonResponse(['passed' => true]),
            ...$slugs
        );
    }

    public function test_marketplace_with_the_module_passes(): void
    {
        $response = $this->callMiddleware($this->requestFor($this->client(['activities-module'])), 'activities-module');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['passed']);
    }

    public function test_marketplace_without_the_module_is_refused(): void
    {
        $response = $this->callMiddleware($this->requestFor($this->client(['netopia'])), 'activities-module');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertArrayNotHasKey('passed', $response->getData(true));
    }

    public function test_every_listed_module_is_required(): void
    {
        $response = $this->callMiddleware(
            $this->requestFor($this->client(['activities-module'])),
            'activities-module',
            'discovery-module'
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_request_without_a_marketplace_client_is_refused(): void
    {
        $response = $this->callMiddleware($this->requestFor(null), 'activities-module');

        $this->assertSame(401, $response->getStatusCode());
    }
}
