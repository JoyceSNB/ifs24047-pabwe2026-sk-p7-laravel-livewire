<?php

namespace Tests\Unit\Middleware;

use App\Features\Stock\Models\Stock;
use App\Http\Middleware\EnsureStockOwnership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureStockOwnershipMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(?User $user, string $method, string $param, mixed $value): Request
    {
        $request = Request::create('/test', $method);
        $request->setUserResolver(fn () => $user);

        $route = new Route($method, '/test/{'.$param.'}', []);
        $route->bind($request);
        $route->setParameter($param, $value);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function assertForbidden(Request $request): void
    {
        $middleware = new EnsureStockOwnership();

        try {
            $middleware->handle($request, fn () => new Response('OK'));
            $this->fail('Expected HttpException 403');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function test_blocks_unauthenticated_request(): void
    {
        $middleware = new EnsureStockOwnership();
        $request = Request::create('/stocks', 'GET');

        $this->expectException(HttpException::class);
        $middleware->handle($request, fn () => new Response('OK'));
    }

    public function test_route_user_parameter_ownership(): void
    {
        $middleware = new EnsureStockOwnership();
        $member1 = User::factory()->member()->create();
        $member2 = User::factory()->member()->create();
        $admin = User::factory()->admin()->create();

        // Member accessing own
        $res1 = $middleware->handle(
            $this->makeRequest($member1, 'GET', 'user', $member1),
            fn () => new Response('OK')
        );
        $this->assertEquals(200, $res1->getStatusCode());

        // Member accessing other user -> 403
        $this->assertForbidden($this->makeRequest($member1, 'GET', 'user', $member2));

        // Member accessing other user by numeric id -> 403
        $this->assertForbidden($this->makeRequest($member1, 'GET', 'user', $member2->id));

        // Admin accessing other user -> allowed
        $res3 = $middleware->handle(
            $this->makeRequest($admin, 'GET', 'user', $member2),
            fn () => new Response('OK')
        );
        $this->assertEquals(200, $res3->getStatusCode());
    }

    public function test_route_stock_parameter_ownership(): void
    {
        $middleware = new EnsureStockOwnership();
        $member1 = User::factory()->member()->create();
        $member2 = User::factory()->member()->create();
        $admin = User::factory()->admin()->create();

        $stock1 = Stock::factory()->create(['user_id' => $member1->id]);

        // Member1 accessing own stock GET
        $res1 = $middleware->handle(
            $this->makeRequest($member1, 'GET', 'stock', $stock1),
            fn () => new Response('OK')
        );
        $this->assertEquals(200, $res1->getStatusCode());

        // Member1 accessing own stock by numeric id
        $res2 = $middleware->handle(
            $this->makeRequest($member1, 'GET', 'stock', $stock1->id),
            fn () => new Response('OK')
        );
        $this->assertEquals(200, $res2->getStatusCode());

        // Member2 accessing Member1's stock GET -> 403
        $this->assertForbidden($this->makeRequest($member2, 'GET', 'stock', $stock1));

        // Admin accessing Member1's stock GET -> allowed
        $res3 = $middleware->handle(
            $this->makeRequest($admin, 'GET', 'stock', $stock1),
            fn () => new Response('OK')
        );
        $this->assertEquals(200, $res3->getStatusCode());

        // Admin attempting to MODIFY Member1's stock (DELETE) -> 403
        $this->assertForbidden($this->makeRequest($admin, 'DELETE', 'stock', $stock1));
    }
}