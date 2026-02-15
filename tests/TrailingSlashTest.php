<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Tests;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\Router\Router;
use MonkeysLegion\Router\RouteCollection;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;

// ── Stub controllers used by the tests ──────────────────────────────────────

#[RoutePrefix('/api/v1/verifications')]
class EmptyPathController
{
    #[Route('POST', '')]
    public function store($request): Response
    {
        return new Response(Stream::createFromString('created'));
    }
}

#[RoutePrefix('/api/v1/verifications')]
class SlashPathController
{
    #[Route('GET', '/')]
    public function index($request): Response
    {
        return new Response(Stream::createFromString('list'));
    }
}

#[RoutePrefix('/api/v1/verifications')]
class ParamPathController
{
    #[Route('GET', '/{id}')]
    public function show($request, string $id): Response
    {
        return new Response(Stream::createFromString("detail:{$id}"));
    }
}

// ── Test cases ──────────────────────────────────────────────────────────────

class TrailingSlashTest extends TestCase
{
    /**
     * #[Route('POST', '')] with a prefix must match the prefix path
     * without a trailing slash.
     */
    public function testControllerWithEmptyPath(): void
    {
        $router = new Router(new RouteCollection());
        $router->registerController(new EmptyPathController());

        $request = new ServerRequest(
            'POST',
            new Uri('http://localhost/api/v1/verifications'),
            Stream::createFromString('')
        );

        $response = $router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('created', (string) $response->getBody());
    }

    /**
     * #[Route('GET', '/')] with a prefix must also match the prefix path
     * without a trailing slash.
     */
    public function testControllerWithSlashPath(): void
    {
        $router = new Router(new RouteCollection());
        $router->registerController(new SlashPathController());

        $request = new ServerRequest(
            'GET',
            new Uri('http://localhost/api/v1/verifications'),
            Stream::createFromString('')
        );

        $response = $router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('list', (string) $response->getBody());
    }

    /**
     * #[Route('GET', '/{id}')] with a prefix must match the prefix + param.
     */
    public function testControllerWithParamPath(): void
    {
        $router = new Router(new RouteCollection());
        $router->registerController(new ParamPathController());

        $request = new ServerRequest(
            'GET',
            new Uri('http://localhost/api/v1/verifications/42'),
            Stream::createFromString('')
        );

        $response = $router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('detail:42', (string) $response->getBody());
    }

    /**
     * A request WITH a trailing slash should still match a route without one.
     */
    public function testTrailingSlashInRequestIsNormalized(): void
    {
        $router = new Router(new RouteCollection());
        $router->registerController(new EmptyPathController());

        $request = new ServerRequest(
            'POST',
            new Uri('http://localhost/api/v1/verifications/'),
            Stream::createFromString('')
        );

        $response = $router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('created', (string) $response->getBody());
    }
}
