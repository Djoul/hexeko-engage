<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Enums\Security\AuthorizationMode;
use App\Http\Middleware\TenantGuard;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[Group('middleware')]
class TenantGuardTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootDatabase = false;
        parent::setUp();
    }

    #[Test]
    public function it_allows_request_when_scopes_are_present(): void
    {
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            ['financer-1'],
            ['division-1'],
            []
        );

        $middleware = new TenantGuard;
        $request = $this->requestWithRouteName('test.route');

        $response = $middleware->handle($request, fn () => response()->json(['status' => 'ok']));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_blocks_request_when_scope_is_missing(): void
    {
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [],
            [],
            []
        );

        $middleware = new TenantGuard;
        $request = $this->requestWithRouteName('test.route');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Tenant guard missing scopes: financer, division');

        $middleware->handle($request, fn () => response()->json());
    }

    private function requestWithRouteName(string $routeName): Request
    {
        $request = Request::create('/test', 'GET');
        $request->setRouteResolver(function () use ($routeName): object {
            return new class($routeName)
            {
                public function __construct(private readonly string $name) {}

                public function getName(): string
                {
                    return $this->name;
                }
            };
        });

        return $request;
    }
}
