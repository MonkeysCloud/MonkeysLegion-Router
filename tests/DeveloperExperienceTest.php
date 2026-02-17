<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Tests;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Router\RouteCollection;
use MonkeysLegion\Router\RouteDebugger;
use MonkeysLegion\Router\RouteRegistrar;
use MonkeysLegion\Router\Router;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DeveloperExperienceTest extends TestCase
{
    private function createRouter(): Router
    {
        return new Router(new RouteCollection());
    }

    // ─── Resource routing ────────────────────────────────────────────

    public function testResourceRegistersAllCrudRoutes(): void
    {
        $controller = new class {
            public function index(ServerRequestInterface $r): ResponseInterface
            { return new Response(Stream::createFromString('index'), 200); }
            public function create(ServerRequestInterface $r): ResponseInterface
            { return new Response(Stream::createFromString('create'), 200); }
            public function store(ServerRequestInterface $r): ResponseInterface
            { return new Response(Stream::createFromString('store'), 200); }
            public function show(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('show:' . $id), 200); }
            public function edit(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('edit:' . $id), 200); }
            public function update(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('update:' . $id), 200); }
            public function destroy(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('destroy:' . $id), 200); }
        };

        $router = $this->createRouter();
        $router->resource('/photos', $controller);

        // Test index
        $req = new ServerRequest('GET', new Uri('http://localhost/photos'), Stream::createFromString(''));
        $this->assertEquals('index', (string)$router->dispatch($req)->getBody());

        // Test show
        $req = new ServerRequest('GET', new Uri('http://localhost/photos/42'), Stream::createFromString(''));
        $this->assertEquals('show:42', (string)$router->dispatch($req)->getBody());

        // Test store
        $req = new ServerRequest('POST', new Uri('http://localhost/photos'), Stream::createFromString(''));
        $this->assertEquals('store', (string)$router->dispatch($req)->getBody());

        // Test update
        $req = new ServerRequest('PUT', new Uri('http://localhost/photos/42'), Stream::createFromString(''));
        $this->assertEquals('update:42', (string)$router->dispatch($req)->getBody());

        // Test destroy
        $req = new ServerRequest('DELETE', new Uri('http://localhost/photos/42'), Stream::createFromString(''));
        $this->assertEquals('destroy:42', (string)$router->dispatch($req)->getBody());
    }

    public function testApiResourceExcludesCreateAndEdit(): void
    {
        $controller = new class {
            public function index(ServerRequestInterface $r): ResponseInterface
            { return new Response(Stream::createFromString('index'), 200); }
            public function store(ServerRequestInterface $r): ResponseInterface
            { return new Response(Stream::createFromString('store'), 200); }
            public function show(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('show:' . $id), 200); }
            public function update(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('update:' . $id), 200); }
            public function destroy(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('destroy:' . $id), 200); }
        };

        $router = $this->createRouter();
        $router->apiResource('/photos', $controller);

        // API routes should work
        $req = new ServerRequest('GET', new Uri('http://localhost/photos'), Stream::createFromString(''));
        $this->assertEquals(200, $router->dispatch($req)->getStatusCode());

        // /photos/create matches the show route with id=create (no separate create route in API)
        $req = new ServerRequest('GET', new Uri('http://localhost/photos/create'), Stream::createFromString(''));
        $this->assertEquals(200, $router->dispatch($req)->getStatusCode());
    }

    // ─── Route debugger ──────────────────────────────────────────────

    public function testRouteDebuggerListReturnsRoutes(): void
    {
        $router = $this->createRouter();
        $router->get('/users', fn($r) => new Response(Stream::createFromString('ok'), 200), 'users.index');
        $router->post('/users', fn($r) => new Response(Stream::createFromString('ok'), 200), 'users.store');

        $debugger = new RouteDebugger($router);
        $list = $debugger->list();

        $this->assertCount(2, $list);
        $this->assertEquals('GET', $list[0]['method']);
        $this->assertEquals('/users', $list[0]['uri']);
        $this->assertEquals('users.index', $list[0]['name']);
    }

    public function testRouteDebuggerRenderOutputsTable(): void
    {
        $router = $this->createRouter();
        $router->get('/', fn($r) => new Response(Stream::createFromString('ok'), 200), 'home');

        $debugger = new RouteDebugger($router);
        $table = $debugger->render();

        $this->assertStringContainsString('Method', $table);
        $this->assertStringContainsString('URI', $table);
        $this->assertStringContainsString('GET', $table);
        $this->assertStringContainsString('home', $table);
    }

    public function testRouteDebuggerFilterByMethod(): void
    {
        $router = $this->createRouter();
        $router->get('/a', fn($r) => new Response(Stream::createFromString('ok'), 200));
        $router->post('/b', fn($r) => new Response(Stream::createFromString('ok'), 200));

        $debugger = new RouteDebugger($router);
        $filtered = $debugger->filter(method: 'POST');

        $this->assertCount(1, $filtered);
        $this->assertEquals('POST', $filtered[0]['method']);
    }

    public function testRouteDebuggerFilterByPath(): void
    {
        $router = $this->createRouter();
        $router->get('/api/users', fn($r) => new Response(Stream::createFromString('ok'), 200));
        $router->get('/web/home', fn($r) => new Response(Stream::createFromString('ok'), 200));

        $debugger = new RouteDebugger($router);
        $filtered = $debugger->filter(pathContains: '/api');

        $this->assertCount(1, $filtered);
        $this->assertEquals('/api/users', $filtered[0]['uri']);
    }

    public function testRouteDebuggerEmptyList(): void
    {
        $router = $this->createRouter();
        $debugger = new RouteDebugger($router);

        $this->assertEquals("No routes registered.\n", $debugger->render());
    }

    // ─── Named resource routes ───────────────────────────────────────

    public function testResourceRoutesAreNamed(): void
    {
        $controller = new class {
            public function index(ServerRequestInterface $r): ResponseInterface
            { return new Response(Stream::createFromString('ok'), 200); }
            public function show(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('ok'), 200); }
            public function store(ServerRequestInterface $r): ResponseInterface
            { return new Response(Stream::createFromString('ok'), 200); }
            public function update(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('ok'), 200); }
            public function destroy(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('ok'), 200); }
            public function create(ServerRequestInterface $r): ResponseInterface
            { return new Response(Stream::createFromString('ok'), 200); }
            public function edit(ServerRequestInterface $r, string $id): ResponseInterface
            { return new Response(Stream::createFromString('ok'), 200); }
        };

        $router = $this->createRouter();
        $router->resource('/photos', $controller);

        // Named routes should be registered
        $url = $router->url('photos.index');
        $this->assertEquals('/photos', $url);

        $url = $router->url('photos.show', ['id' => '42']);
        $this->assertEquals('/photos/42', $url);
    }
}
