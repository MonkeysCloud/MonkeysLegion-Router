<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Tests;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Router\RouteCollection;
use MonkeysLegion\Router\Router;
use MonkeysLegion\Router\TrailingSlashStrategy;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DispatchHardeningTest extends TestCase
{
    private function createRouter(): Router
    {
        $routes = new RouteCollection();
        return new Router($routes);
    }

    private function makeRequest(string $method = 'GET', string $path = '/', string $host = 'localhost'): ServerRequestInterface
    {
        return new ServerRequest($method, new Uri('http://' . $host . $path), Stream::createFromString(''));
    }

    private function okHandler(): callable
    {
        return fn(ServerRequestInterface $req): ResponseInterface =>
            new Response(Stream::createFromString('ok'), 200);
    }

    // ─── HEAD auto-delegation ────────────────────────────────────────

    public function testHeadDelegatesFromGetRoute(): void
    {
        $router = $this->createRouter();
        $router->get('/hello', fn($req) => new Response(Stream::createFromString('Hello World'), 200));

        $response = $router->dispatch($this->makeRequest('HEAD', '/hello'));

        $this->assertEquals(200, $response->getStatusCode());
        // Body should be stripped for HEAD
        $this->assertEquals('', (string)$response->getBody());
    }

    public function testHeadWithExplicitHeadRoute(): void
    {
        $router = $this->createRouter();
        $router->add('HEAD', '/ping', fn($req) => new Response(Stream::createFromString('pong'), 200));

        $response = $router->dispatch($this->makeRequest('HEAD', '/ping'));

        $this->assertEquals(200, $response->getStatusCode());
        // Explicit HEAD route: body NOT stripped
        $this->assertEquals('pong', (string)$response->getBody());
    }

    // ─── OPTIONS auto-response ───────────────────────────────────────

    public function testOptionsAutoResponse(): void
    {
        $router = $this->createRouter();
        $router->get('/items', $this->okHandler());
        $router->post('/items', $this->okHandler());
        $router->delete('/items', $this->okHandler());

        $response = $router->dispatch($this->makeRequest('OPTIONS', '/items'));

        $this->assertEquals(200, $response->getStatusCode());
        $allow = $response->getHeaderLine('Allow');
        $this->assertStringContainsString('GET', $allow);
        $this->assertStringContainsString('POST', $allow);
        $this->assertStringContainsString('DELETE', $allow);
        $this->assertStringContainsString('OPTIONS', $allow);
    }

    public function testOptionsReturnsEmptyBody(): void
    {
        $router = $this->createRouter();
        $router->get('/test', $this->okHandler());

        $response = $router->dispatch($this->makeRequest('OPTIONS', '/test'));

        $this->assertEquals('', (string)$response->getBody());
        $this->assertEquals('0', $response->getHeaderLine('Content-Length'));
    }

    // ─── Trailing slash strategy ─────────────────────────────────────

    public function testTrailingSlashStripIsDefault(): void
    {
        $router = $this->createRouter();
        $router->get('/items', $this->okHandler(), 'items');

        // With trailing slash → should still match
        $response = $router->dispatch($this->makeRequest('GET', '/items/'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTrailingSlashRedirectStrategy(): void
    {
        $router = $this->createRouter();
        $router->setTrailingSlashStrategy(TrailingSlashStrategy::REDIRECT_301);
        $router->get('/items', $this->okHandler());

        $response = $router->dispatch($this->makeRequest('GET', '/items/'));

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/items', $response->getHeaderLine('Location'));
    }

    public function testTrailingSlashRedirectNoOpWithoutSlash(): void
    {
        $router = $this->createRouter();
        $router->setTrailingSlashStrategy(TrailingSlashStrategy::REDIRECT_301);
        $router->get('/items', $this->okHandler());

        // Without trailing slash → should match normally, no redirect
        $response = $router->dispatch($this->makeRequest('GET', '/items'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTrailingSlashAllowBothStrategy(): void
    {
        $router = $this->createRouter();
        $router->setTrailingSlashStrategy(TrailingSlashStrategy::ALLOW_BOTH);
        $router->get('/items', $this->okHandler());

        // Without trailing slash → should match (exact regex match)
        $response = $router->dispatch($this->makeRequest('GET', '/items'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRootPathUnaffectedByTrailingSlashStrategy(): void
    {
        $router = $this->createRouter();
        $router->setTrailingSlashStrategy(TrailingSlashStrategy::REDIRECT_301);
        $router->get('/', $this->okHandler());

        $response = $router->dispatch($this->makeRequest('GET', '/'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ─── Wildcard / catch-all routes ─────────────────────────────────

    public function testWildcardCatchAllRoute(): void
    {
        $router = $this->createRouter();
        $router->get('/files/{path+}', fn($req, $path) =>
            new Response(Stream::createFromString('path:' . $path), 200)
        );

        $response = $router->dispatch($this->makeRequest('GET', '/files/docs/readme.md'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('path:docs/readme.md', (string)$response->getBody());
    }

    // ─── Domain constraint enforcement ───────────────────────────────

    public function testDomainConstraintMatches(): void
    {
        $router = $this->createRouter();
        $router->add('GET', '/dashboard', $this->okHandler(), domain: 'admin.example.com');

        $response = $router->dispatch($this->makeRequest('GET', '/dashboard', 'admin.example.com'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDomainConstraintRejects(): void
    {
        $router = $this->createRouter();
        $router->add('GET', '/dashboard', $this->okHandler(), domain: 'admin.example.com');

        $response = $router->dispatch($this->makeRequest('GET', '/dashboard', 'other.example.com'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDomainConstraintWithPattern(): void
    {
        $router = $this->createRouter();
        $router->add('GET', '/home', $this->okHandler(), domain: '{tenant}.app.com');

        $response = $router->dispatch($this->makeRequest('GET', '/home', 'acme.app.com'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ─── Fallback handler ────────────────────────────────────────────

    public function testFallbackHandler(): void
    {
        $router = $this->createRouter();
        $router->fallback(fn($req) => new Response(Stream::createFromString('fallback'), 200));

        $response = $router->dispatch($this->makeRequest('GET', '/nonexistent'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('fallback', (string)$response->getBody());
    }

    public function testFallbackIsNotCalledWhenRouteMatches(): void
    {
        $router = $this->createRouter();
        $router->get('/exists', fn($req) => new Response(Stream::createFromString('found'), 200));
        $router->fallback(fn($req) => new Response(Stream::createFromString('fallback'), 200));

        $response = $router->dispatch($this->makeRequest('GET', '/exists'));
        $this->assertEquals('found', (string)$response->getBody());
    }

    // ─── Redirect convenience method ─────────────────────────────────

    public function testRedirectRoute(): void
    {
        $router = $this->createRouter();
        $router->redirect('/old-page', '/new-page', 301);

        $response = $router->dispatch($this->makeRequest('GET', '/old-page'));

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/new-page', $response->getHeaderLine('Location'));
    }

    // ─── Method not allowed includes HEAD when GET is present ────────

    public function testMethodNotAllowedIncludesHead(): void
    {
        $router = $this->createRouter();
        $router->get('/resource', $this->okHandler());

        $response = $router->dispatch($this->makeRequest('DELETE', '/resource'));

        $this->assertEquals(405, $response->getStatusCode());
        $allow = $response->getHeaderLine('Allow');
        $this->assertStringContainsString('GET', $allow);
        $this->assertStringContainsString('HEAD', $allow);
    }
}
