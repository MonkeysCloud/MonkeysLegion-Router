<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Tests;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\Router\Router;
use MonkeysLegion\Router\RouteCollection;
use MonkeysLegion\Router\UrlGenerator;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;

/**
 * Example test suite for MonkeysLegion Router
 */
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(new RouteCollection());
    }

    public function testBasicGetRoute(): void
    {
        $this->router->get('/users', function ($request) {
            return new Response(Stream::createFromString('Users list'));
        });

        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/users'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Users list', (string) $response->getBody());
    }

    public function testRouteWithParameter(): void
    {
        $this->router->get('/users/{id}', function ($request, $id) {
            return new Response(Stream::createFromString("User: {$id}"));
        });

        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/users/123'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('User: 123', (string) $response->getBody());
    }

    public function testRouteConstraint(): void
    {
        $this->router->get('/users/{id:\d+}', function ($request, $id) {
            return new Response(Stream::createFromString("User: {$id}"));
        });

        // Valid integer
        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/users/123'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());

        // Invalid (non-integer) should return 404
        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/users/abc'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testOptionalParameter(): void
    {
        $this->router->get('/posts/{page?}', function ($request, $page = '1') {
            return new Response(Stream::createFromString("Page: {$page}"));
        });

        // Without optional parameter
        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/posts'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Page: 1', (string) $response->getBody());

        // With optional parameter
        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/posts/5'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Page: 5', (string) $response->getBody());
    }

    public function testNamedRouteUrlGeneration(): void
    {
        $this->router->get('/users/{id}', function ($request, $id) {
            return new Response(Stream::createFromString("User: {$id}"));
        }, 'users.show');

        $url = $this->router->url('users.show', ['id' => 123]);
        $this->assertEquals('/users/123', $url);
    }

    public function testNamedRouteWithQueryParams(): void
    {
        $this->router->get('/posts/{slug}', function ($request, $slug) {
            return new Response(Stream::createFromString("Post: {$slug}"));
        }, 'posts.show');

        $url = $this->router->url('posts.show', [
            'slug' => 'hello-world',
            'preview' => 1,
            'ref' => 'twitter'
        ]);

        $this->assertEquals('/posts/hello-world?preview=1&ref=twitter', $url);
    }

    public function testAbsoluteUrlGeneration(): void
    {
        $this->router->get('/users/{id}', fn($r, $id) => new Response(), 'users.show');

        $urlGen = $this->router->getUrlGenerator();
        $urlGen->setBaseUrl('https://example.com');

        $url = $this->router->url('users.show', ['id' => 123], true);
        $this->assertEquals('https://example.com/users/123', $url);
    }

    public function testMethodNotAllowed(): void
    {
        $this->router->get('/users', function ($request) {
            return new Response(Stream::createFromString('Users'));
        });

        // POST to a GET-only route
        $request = new ServerRequest(
            'POST',
            new Uri('http://example.com/users'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Allow'));
        // v2.2: HEAD is auto-included when GET is registered (per RFC 7231)
        $allow = $response->getHeaderLine('Allow');
        $this->assertStringContainsString('GET', $allow);
        $this->assertStringContainsString('HEAD', $allow);
    }

    public function testNotFound(): void
    {
        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/nonexistent'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCustomNotFoundHandler(): void
    {
        $this->router->setNotFoundHandler(function ($request) {
            return new Response(
                Stream::createFromString('Custom 404'),
                404
            );
        });

        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/nonexistent'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Custom 404', (string) $response->getBody());
    }

    public function testRequestAttributesFromParameters(): void
    {
        $this->router->get('/users/{id}/posts/{postId}', function ($request) {
            $userId = $request->getAttribute('id');
            $postId = $request->getAttribute('postId');

            return new Response(
                Stream::createFromString("User: {$userId}, Post: {$postId}")
            );
        });

        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/users/123/posts/456'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);

        $this->assertEquals('User: 123, Post: 456', (string) $response->getBody());
    }

    public function testMultipleHttpMethods(): void
    {
        $handler = function ($request) {
            return new Response(Stream::createFromString('OK'));
        };

        $this->router->match(['GET', 'POST'], '/api/users', $handler);

        // Test GET
        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/api/users'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());

        // Test POST
        $request = new ServerRequest(
            'POST',
            new Uri('http://example.com/api/users'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());

        // Test PUT (should fail)
        $request = new ServerRequest(
            'PUT',
            new Uri('http://example.com/api/users'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function testRouteSpecificity(): void
    {
        // More specific route should match first
        $this->router->get('/users/admin', function ($request) {
            return new Response(Stream::createFromString('Admin'));
        });

        $this->router->get('/users/{id}', function ($request, $id) {
            return new Response(Stream::createFromString("User: {$id}"));
        });

        // Should match specific route
        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/users/admin'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);
        $this->assertEquals('Admin', (string) $response->getBody());

        // Should match parameterized route
        $request = new ServerRequest(
            'GET',
            new Uri('http://example.com/users/123'),
            Stream::createFromString('')
        );
        $response = $this->router->dispatch($request);
        $this->assertEquals('User: 123', (string) $response->getBody());
    }
}

/**
 * Example test for UrlGenerator
 */
class UrlGeneratorTest extends TestCase
{
    private UrlGenerator $urlGen;

    protected function setUp(): void
    {
        $this->urlGen = new UrlGenerator();
    }

    public function testSimpleUrlGeneration(): void
    {
        $this->urlGen->register('home', '/', ['GET'], []);
        $url = $this->urlGen->generate('home');
        $this->assertEquals('/', $url);
    }

    public function testUrlWithParameters(): void
    {
        $this->urlGen->register('user.profile', '/users/{id}/profile', ['GET'], ['id']);
        $url = $this->urlGen->generate('user.profile', ['id' => 123]);
        $this->assertEquals('/users/123/profile', $url);
    }

    public function testMissingRequiredParameter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameter: id');

        $this->urlGen->register('user.show', '/users/{id}', ['GET'], ['id']);
        $this->urlGen->generate('user.show'); // Missing 'id' parameter
    }

    public function testNonExistentRoute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Route 'nonexistent' not found");

        $this->urlGen->generate('nonexistent');
    }

    public function testOptionalParameterOmitted(): void
    {
        $this->urlGen->register('posts', '/posts/{page?}', ['GET'], ['page']);
        $url = $this->urlGen->generate('posts');
        $this->assertEquals('/posts', $url);
    }

    public function testOptionalParameterProvided(): void
    {
        $this->urlGen->register('posts', '/posts/{page?}', ['GET'], ['page']);
        $url = $this->urlGen->generate('posts', ['page' => 2]);
        $this->assertEquals('/posts/2', $url);
    }
}

/**
 * Example test for RouteCollection
 */
class RouteCollectionTest extends TestCase
{
    private RouteCollection $collection;

    protected function setUp(): void
    {
        $this->collection = new RouteCollection();
    }

    public function testAddRoute(): void
    {
        $handler = fn() => null;
        $this->collection->add('GET', '/users', $handler);

        $routes = $this->collection->all();
        $this->assertCount(1, $routes);
        $this->assertEquals('GET', $routes[0]['method']);
        $this->assertEquals('/users', $routes[0]['path']);
    }

    public function testNamedRoute(): void
    {
        $handler = fn() => null;
        $this->collection->add('GET', '/users', $handler, 'users.index');

        $this->assertTrue($this->collection->hasName('users.index'));
        $route = $this->collection->getByName('users.index');
        $this->assertNotNull($route);
        $this->assertEquals('/users', $route['path']);
    }

    public function testDuplicateRouteName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Route name 'users.index' is already registered");

        $handler = fn() => null;
        $this->collection->add('GET', '/users', $handler, 'users.index');
        $this->collection->add('GET', '/admin/users', $handler, 'users.index'); // Duplicate
    }

    public function testRouteWithConstraints(): void
    {
        $handler = fn() => null;
        $this->collection->add('GET', '/users/{id}', $handler, '', [], ['id' => '\d+']);

        $routes = $this->collection->all();
        $this->assertEquals(['id' => '\d+'], $routes[0]['constraints']);
    }

    public function testInlineConstraints(): void
    {
        $handler = fn() => null;
        $this->collection->add('GET', '/users/{id:\d+}', $handler);

        $routes = $this->collection->all();
        $this->assertStringContainsString('\d+', $routes[0]['regex']);
    }

    public function testExportImport(): void
    {
        $handler = fn() => null;
        $this->collection->add('GET', '/users', $handler, 'users.index');
        $this->collection->add('POST', '/users', $handler, 'users.create');

        $exported = $this->collection->export();

        $newCollection = new RouteCollection();
        $newCollection->import($exported);

        $this->assertCount(2, $newCollection->all());
        $this->assertTrue($newCollection->hasName('users.index'));
        $this->assertTrue($newCollection->hasName('users.create'));
    }
}