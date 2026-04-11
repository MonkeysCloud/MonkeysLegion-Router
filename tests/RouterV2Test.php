<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Tests;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Router\Attributes\ApiResource;
use MonkeysLegion\Router\Attributes\Middleware;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Router\Attributes\Throttle;
use MonkeysLegion\Router\Attributes\WithoutMiddleware;
use MonkeysLegion\Router\CompiledRoutes;
use MonkeysLegion\Router\GroupContext;
use MonkeysLegion\Router\MatchResult;
use MonkeysLegion\Router\Middleware\CallableHandlerAdapter;
use MonkeysLegion\Router\Middleware\MiddlewarePipeline;
use MonkeysLegion\Router\RouteCollection;
use MonkeysLegion\Router\RouteCompiler;
use MonkeysLegion\Router\RouteDebugger;
use MonkeysLegion\Router\RouteDefinition;
use MonkeysLegion\Router\Router;
use MonkeysLegion\Router\SignedUrlGenerator;
use MonkeysLegion\Router\TrailingSlashStrategy;
use MonkeysLegion\Router\UrlGenerator;
use MonkeysLegion\Router\Constraints\RouteConstraints;
use MonkeysLegion\Router\Constraints\IntegerConstraint;
use MonkeysLegion\Router\Constraints\UuidConstraint;
use MonkeysLegion\Router\Constraints\SlugConstraint;
use MonkeysLegion\Router\Constraints\UlidConstraint;
use MonkeysLegion\Router\Constraints\DateConstraint;
use MonkeysLegion\Router\Constraints\IpConstraint;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use PHPUnit\Framework\TestCase;

/**
 * Comprehensive test suite for MonkeysLegion Router v2.
 */
class RouterV2Test extends TestCase
{
    // ── Helpers ────────────────────────────────────────────────

    private function makeRequest(string $method, string $path, string $host = 'localhost'): ServerRequestInterface
    {
        return new ServerRequest($method, new Uri("http://{$host}{$path}"), Stream::createFromString(''));
    }

    private function jsonHandler(string $body = '{"ok":true}'): \Closure
    {
        return fn(ServerRequestInterface $req) => new Response(
            Stream::createFromString($body),
            200,
            ['Content-Type' => 'application/json'],
        );
    }

    private function makeRouter(): Router
    {
        return new Router(new RouteCollection());
    }

    // ── RouteDefinition ───────────────────────────────────────

    public function testRouteDefinitionIsReadonly(): void
    {
        $def = new RouteDefinition(
            method: 'GET',
            path: '/users/{id}',
            regex: '#^/users/(?P<id>\d+)$#',
            handler: [self::class, 'testRouteDefinitionIsReadonly'],
            paramNames: ['id'],
            name: 'users.show',
        );

        $this->assertSame('GET', $def->method);
        $this->assertSame('/users/{id}', $def->path);
        $this->assertSame('users.show', $def->name);
        $this->assertSame(['id'], $def->paramNames);
    }

    public function testRouteDefinitionHandlerPair(): void
    {
        $def = new RouteDefinition(
            method: 'GET', path: '/', regex: '#^/$#',
            handler: ['App\\Controller', 'index'],
        );
        $this->assertSame(['App\\Controller', 'index'], $def->handlerPair());
    }

    public function testRouteDefinitionHandlerPairFromMeta(): void
    {
        $def = new RouteDefinition(
            method: 'GET', path: '/', regex: '#^/$#',
            handler: fn() => null,
            meta: ['handler' => ['App\\Ctrl', 'action']],
        );
        $this->assertSame(['App\\Ctrl', 'action'], $def->handlerPair());
    }

    public function testRouteDefinitionToArray(): void
    {
        $def = new RouteDefinition(method: 'GET', path: '/', regex: '#^/$#', handler: fn() => null);
        $arr = $def->toArray();
        $this->assertSame('GET', $arr['method']);
        $this->assertSame('/', $arr['path']);
    }

    // ── MatchResult ───────────────────────────────────────────

    public function testMatchResultAccessors(): void
    {
        $def = new RouteDefinition(
            method: 'GET', path: '/users/{id}', regex: '#^/users/(?P<id>\d+)$#',
            handler: [self::class, 'testMatchResultAccessors'],
            paramNames: ['id'],
            name: 'users.show',
            middleware: ['auth'],
            defaults: ['format' => 'json'],
        );

        $result = new MatchResult($def, ['id' => '42']);

        $this->assertSame('42', $result->param('id'));
        $this->assertSame('json', $result->param('format'));
        $this->assertNull($result->param('missing'));
        $this->assertSame('fallback', $result->param('missing', 'fallback'));
        $this->assertSame('users.show', $result->name());
        $this->assertSame(['auth'], $result->middleware());
    }

    // ── GroupContext ───────────────────────────────────────────

    public function testGroupContextImmutability(): void
    {
        $ctx = new GroupContext();
        $with = $ctx->withPrefix('/api');
        $this->assertSame('', $ctx->prefix);
        $this->assertSame('/api', $with->prefix);
    }

    public function testGroupContextChaining(): void
    {
        $ctx = (new GroupContext())
            ->withPrefix('/api')
            ->withMiddleware(['auth'])
            ->withConstraints(['id' => '\\d+'])
            ->withDomain('api.example.com');

        $this->assertSame('/api', $ctx->prefix);
        $this->assertSame(['auth'], $ctx->middleware);
        $this->assertSame(['id' => '\\d+'], $ctx->constraints);
        $this->assertSame('api.example.com', $ctx->domain);
    }

    public function testGroupContextApplyPath(): void
    {
        $ctx = (new GroupContext())->withPrefix('/api/v2');
        $this->assertSame('/api/v2/users', $ctx->applyPath('/users'));
    }

    // ── RouteCollection ───────────────────────────────────────

    public function testRouteCollectionAdd(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/users', fn() => null, 'users.index');
        $this->assertSame(1, $rc->count());
        $this->assertTrue($rc->hasName('users.index'));
    }

    public function testRouteCollectionAcceptsArrayHandler(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/test', [self::class, 'testRouteCollectionAcceptsArrayHandler']);
        $routes = $rc->all();
        $this->assertCount(1, $routes);
        $this->assertSame([self::class, 'testRouteCollectionAcceptsArrayHandler'], $routes[0]->meta['handler']);
    }

    public function testRouteCollectionDuplicateNameThrows(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/a', fn() => null, 'test');
        $this->expectException(\InvalidArgumentException::class);
        $rc->add('GET', '/b', fn() => null, 'test');
    }

    public function testRouteCollectionFreeze(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/', fn() => null);
        $rc->freeze();
        $this->assertTrue($rc->isFrozen());
        $this->expectException(\LogicException::class);
        $rc->add('GET', '/new', fn() => null);
    }

    public function testRouteCollectionGetByMethod(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/a', fn() => null);
        $rc->add('POST', '/b', fn() => null);
        $rc->add('GET', '/c', fn() => null);

        $getRoutes = $rc->getByMethod('GET');
        $this->assertCount(2, $getRoutes);
    }

    public function testRouteCollectionGetByName(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/users', fn() => null, 'users.index');
        $route = $rc->getByName('users.index');
        $this->assertNotNull($route);
        $this->assertSame('/users', $route->path);
    }

    public function testRouteCollectionMethodsForPath(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/users', fn() => null);
        $rc->add('POST', '/users', fn() => null);
        $rc->add('DELETE', '/other', fn() => null);

        $methods = $rc->getMethodsForPath('/users');
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertNotContains('DELETE', $methods);
    }

    public function testRouteCollectionExportImport(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/users', fn() => null, 'users.index');
        $exported = $rc->export();

        $rc2 = new RouteCollection();
        $rc2->import($exported);
        $this->assertSame(1, $rc2->count());
    }

    public function testRouteCollectionInlineConstraints(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/users/{id:\\d+}', fn() => null);
        $routes = $rc->all();
        $this->assertMatchesRegularExpression($routes[0]->regex, '/users/42');
        $this->assertDoesNotMatchRegularExpression($routes[0]->regex, '/users/abc');
    }

    public function testRouteCollectionOptionalParams(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/posts/{slug?}', fn() => null);
        $routes = $rc->all();
        $this->assertMatchesRegularExpression($routes[0]->regex, '/posts/hello-world');
        $this->assertMatchesRegularExpression($routes[0]->regex, '/posts');
    }

    // ── RouteCompiler + CompiledRoutes ─────────────────────────

    public function testCompiledStaticMatch(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/health', fn() => null, 'health');

        $compiled = (new RouteCompiler())->compile($rc);
        $result = $compiled->match('GET', '/health');

        $this->assertNotNull($result);
        $this->assertSame('health', $result->name());
    }

    public function testCompiledDynamicMatch(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/users/{id:\\d+}', fn() => null, 'users.show');

        $compiled = (new RouteCompiler())->compile($rc);
        $result = $compiled->match('GET', '/users/42');

        $this->assertNotNull($result);
        $this->assertSame('42', $result->param('id'));
        $this->assertSame('users.show', $result->name());
    }

    public function testCompiledNoMatch(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/users', fn() => null);

        $compiled = (new RouteCompiler())->compile($rc);
        $this->assertNull($compiled->match('GET', '/posts'));
    }

    public function testCompiledAllowedMethods(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/resource', fn() => null);
        $rc->add('POST', '/resource', fn() => null);

        $compiled = (new RouteCompiler())->compile($rc);
        $allowed = $compiled->getAllowedMethods('/resource');

        $this->assertContains('GET', $allowed);
        $this->assertContains('POST', $allowed);
    }

    public function testCompiledExportImport(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/static', ['StaticController', 'index'], 'test');
        $rc->add('GET', '/dynamic/{id}', ['DynamicController', 'show']);

        $compiled = (new RouteCompiler())->compile($rc);
        $exported = $compiled->export();

        $imported = CompiledRoutes::import($exported);
        $this->assertNotNull($imported->match('GET', '/static'));
        $this->assertNotNull($imported->match('GET', '/dynamic/42'));
    }

    public function testCompiledExportClosureThrows(): void
    {
        $rc = new RouteCollection();
        $rc->add('GET', '/closure', fn() => null);

        $compiled = (new RouteCompiler())->compile($rc);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cache-exportable');
        $compiled->export();
    }

    // ── MiddlewarePipeline ────────────────────────────────────

    public function testPipelineProcessesMiddleware(): void
    {
        $mw = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);
                return $response->withHeader('X-Middleware', 'applied');
            }
        };

        $pipeline = new MiddlewarePipeline([$mw]);
        $handler  = new CallableHandlerAdapter(fn() => new Response(Stream::createFromString('ok'), 200));

        $response = $pipeline->process($this->makeRequest('GET', '/'), $handler);
        $this->assertSame('applied', $response->getHeaderLine('X-Middleware'));
    }

    public function testPipelinePriority(): void
    {
        $order = [];

        $mwA = new class($order) implements MiddlewareInterface {
            public function __construct(private array &$order) {}
            public function process(ServerRequestInterface $r, RequestHandlerInterface $h): ResponseInterface
            {
                $this->order[] = 'A';
                return $h->handle($r);
            }
        };

        $mwB = new class($order) implements MiddlewareInterface {
            public function __construct(private array &$order) {}
            public function process(ServerRequestInterface $r, RequestHandlerInterface $h): ResponseInterface
            {
                $this->order[] = 'B';
                return $h->handle($r);
            }
        };

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($mwA, 0);   // Lower priority
        $pipeline->pipe($mwB, 10);  // Higher priority → runs first

        $handler = new CallableHandlerAdapter(fn() => new Response(Stream::createFromString('ok'), 200));
        $pipeline->process($this->makeRequest('GET', '/'), $handler);

        $this->assertSame(['B', 'A'], $order);
    }

    public function testPipelineLockPreventsAdding(): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->lock();

        $this->expectException(\LogicException::class);
        $pipeline->pipe(new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $r, RequestHandlerInterface $h): ResponseInterface
            {
                return $h->handle($r);
            }
        });
    }

    public function testPipelineEmptyPassesThrough(): void
    {
        $pipeline = new MiddlewarePipeline();
        $handler  = new CallableHandlerAdapter(fn() => new Response(Stream::createFromString('direct'), 200));

        $response = $pipeline->process($this->makeRequest('GET', '/'), $handler);
        $this->assertSame(200, $response->getStatusCode());
    }

    // ── Router dispatch ───────────────────────────────────────

    public function testBasicDispatch(): void
    {
        $router = $this->makeRouter();
        $router->get('/hello', $this->jsonHandler('{"hello":"world"}'));

        $response = $router->dispatch($this->makeRequest('GET', '/hello'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('hello', (string) $response->getBody());
    }

    public function testDispatchWithParams(): void
    {
        $router = $this->makeRouter();
        $router->get('/users/{id:\\d+}', function (ServerRequestInterface $req, string $id) {
            return new Response(
                Stream::createFromString("{\"id\":{$id}}"),
                200,
                ['Content-Type' => 'application/json'],
            );
        });

        $response = $router->dispatch($this->makeRequest('GET', '/users/42'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('42', (string) $response->getBody());
    }

    public function testDispatch404(): void
    {
        $router = $this->makeRouter();
        $router->get('/exists', $this->jsonHandler());

        $response = $router->dispatch($this->makeRequest('GET', '/nonexistent'));
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDispatch405(): void
    {
        $router = $this->makeRouter();
        $router->get('/resource', $this->jsonHandler());

        $response = $router->dispatch($this->makeRequest('POST', '/resource'));
        $this->assertSame(405, $response->getStatusCode());
        $this->assertStringContainsString('GET', $response->getHeaderLine('Allow'));
    }

    public function testHeadAutoDelegation(): void
    {
        $router = $this->makeRouter();
        $router->get('/page', fn() => new Response(
            Stream::createFromString('body content'),
            200,
            ['Content-Type' => 'text/html'],
        ));

        $response = $router->dispatch($this->makeRequest('HEAD', '/page'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    public function testOptionsAutoResponse(): void
    {
        $router = $this->makeRouter();
        $router->get('/api', $this->jsonHandler());
        $router->post('/api', $this->jsonHandler());

        $response = $router->dispatch($this->makeRequest('OPTIONS', '/api'));
        $this->assertSame(200, $response->getStatusCode());

        $allow = $response->getHeaderLine('Allow');
        $this->assertStringContainsString('GET', $allow);
        $this->assertStringContainsString('POST', $allow);
    }

    public function testCustomNotFoundHandler(): void
    {
        $router = $this->makeRouter();
        $router->setNotFoundHandler(fn() => new Response(
            Stream::createFromString('custom 404'),
            404,
        ));

        $response = $router->dispatch($this->makeRequest('GET', '/nope'));
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('custom 404', (string) $response->getBody());
    }

    public function testFallbackHandler(): void
    {
        $router = $this->makeRouter();
        $router->fallback(fn() => new Response(
            Stream::createFromString('fallback'),
            200,
        ));

        $response = $router->dispatch($this->makeRequest('GET', '/any'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('fallback', (string) $response->getBody());
    }

    public function testTrailingSlashStrip(): void
    {
        $router = $this->makeRouter();
        $router->trailingSlashStrategy = TrailingSlashStrategy::STRIP;
        $router->get('/users', $this->jsonHandler());

        $response = $router->dispatch($this->makeRequest('GET', '/users/'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testTrailingSlashRedirect(): void
    {
        $router = $this->makeRouter();
        $router->trailingSlashStrategy = TrailingSlashStrategy::REDIRECT_301;
        $router->get('/users', $this->jsonHandler());

        $response = $router->dispatch($this->makeRequest('GET', '/users/'));
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/users', $response->getHeaderLine('Location'));
    }

    public function testRouteRedirect(): void
    {
        $router = $this->makeRouter();
        $router->redirect('/old', '/new', 301);

        $response = $router->dispatch($this->makeRequest('GET', '/old'));
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/new', $response->getHeaderLine('Location'));
    }

    public function testRequestAttributes(): void
    {
        $router = $this->makeRouter();
        $router->get('/items/{id}/{slug}', function (ServerRequestInterface $req) {
            return new Response(
                Stream::createFromString(json_encode([
                    'id' => $req->getAttribute('id'),
                    'slug' => $req->getAttribute('slug'),
                ])),
                200,
            );
        });

        $response = $router->dispatch($this->makeRequest('GET', '/items/5/hello'));
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('5', $data['id']);
        $this->assertSame('hello', $data['slug']);
    }

    public function testMultipleHttpMethods(): void
    {
        $router = $this->makeRouter();
        $router->match(['GET', 'POST'], '/multi', $this->jsonHandler());

        $this->assertSame(200, $router->dispatch($this->makeRequest('GET', '/multi'))->getStatusCode());
        $this->assertSame(200, $router->dispatch($this->makeRequest('POST', '/multi'))->getStatusCode());
        $this->assertSame(405, $router->dispatch($this->makeRequest('DELETE', '/multi'))->getStatusCode());
    }

    // ── Route groups ──────────────────────────────────────────

    public function testRouteGroup(): void
    {
        $router = $this->makeRouter();
        $router->group()
            ->prefix('/api')
            ->middleware('auth')
            ->group(function (Router $r) {
                $r->get('/users', fn(ServerRequestInterface $req) => new Response(
                    Stream::createFromString('users'),
                    200,
                ));
            });

        $response = $router->dispatch($this->makeRequest('GET', '/api/users'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNestedGroups(): void
    {
        $router = $this->makeRouter();
        $router->group()
            ->prefix('/api')
            ->group(function (Router $r) {
                $r->group()
                    ->prefix('/v2')
                    ->group(function (Router $r) {
                        $r->get('/data', fn(ServerRequestInterface $req) => new Response(
                            Stream::createFromString('nested'),
                            200,
                        ));
                    });
            });

        $response = $router->dispatch($this->makeRequest('GET', '/api/v2/data'));
        $this->assertSame(200, $response->getStatusCode());
    }

    // ── Domain constraints ────────────────────────────────────

    public function testDomainConstraint(): void
    {
        $router = $this->makeRouter();
        $router->add('GET', '/panel', $this->jsonHandler(), domain: 'admin.example.com');

        $response = $router->dispatch($this->makeRequest('GET', '/panel', 'admin.example.com'));
        $this->assertSame(200, $response->getStatusCode());

        // Wrong domain: route path matches but domain constraint rejects — returns 405 or 404
        $response = $router->dispatch($this->makeRequest('GET', '/panel', 'other.example.com'));
        $this->assertNotSame(200, $response->getStatusCode());
    }

    // ── URL Generator ─────────────────────────────────────────

    public function testUrlGeneration(): void
    {
        $router = $this->makeRouter();
        $router->get('/users/{id}', $this->jsonHandler(), 'users.show');

        $url = $router->url('users.show', ['id' => 42]);
        $this->assertSame('/users/42', $url);
    }

    public function testUrlGenerationAbsolute(): void
    {
        $router = $this->makeRouter();
        $router->get('/users', $this->jsonHandler(), 'users.index');
        $router->getUrlGenerator()->baseUrl = 'https://api.example.com';

        $url = $router->url('users.index', absolute: true);
        $this->assertSame('https://api.example.com/users', $url);
    }

    public function testUrlGenerationQueryParams(): void
    {
        $router = $this->makeRouter();
        $router->get('/search', $this->jsonHandler(), 'search');

        $url = $router->url('search', ['q' => 'hello', 'page' => 2]);
        $this->assertStringContainsString('?', $url);
        $this->assertStringContainsString('q=hello', $url);
    }

    public function testUrlGenerationMissingParamThrows(): void
    {
        $gen = new UrlGenerator();
        $gen->register('test', '/items/{id}', ['GET'], ['id']);

        $this->expectException(\InvalidArgumentException::class);
        $gen->generate('test');
    }

    public function testUrlGeneratorModelBinding(): void
    {
        $gen = new UrlGenerator();
        $gen->register('users.show', '/users/{user}', ['GET'], ['user']);

        $user = new class { public int $id = 42; };
        $url = $gen->generate('users.show', ['user' => $user]);
        $this->assertSame('/users/42', $url);
    }

    public function testUrlGeneratorEnumBinding(): void
    {
        $gen = new UrlGenerator();
        $gen->register('filter', '/items/{status}', ['GET'], ['status']);

        $url = $gen->generate('filter', ['status' => TestStatus::Active]);
        $this->assertSame('/items/active', $url);
    }

    // ── SignedUrlGenerator ─────────────────────────────────────

    public function testSignedUrlGenerateAndValidate(): void
    {
        $gen = new UrlGenerator();
        $gen->register('verify', '/verify/{id}', ['GET'], ['id']);

        $signed = new SignedUrlGenerator($gen, 'this-is-a-secret-key-1234');
        $url = $signed->generate('verify', ['id' => 42]);

        $this->assertStringContainsString('signature=', $url);
        $this->assertTrue($signed->validate($url));
    }

    public function testSignedUrlExpired(): void
    {
        $gen = new UrlGenerator();
        $gen->register('link', '/link', ['GET'], []);

        $signed = new SignedUrlGenerator($gen, 'this-is-a-secret-key-1234');
        $url = $signed->generate('link', expiration: -1); // Already expired

        $this->assertFalse($signed->validate($url));
    }

    public function testSignedUrlTampered(): void
    {
        $gen = new UrlGenerator();
        $gen->register('safe', '/safe', ['GET'], []);

        $signed = new SignedUrlGenerator($gen, 'this-is-a-secret-key-1234');
        $url = $signed->generate('safe');

        $this->assertFalse($signed->validate($url . '&tampered=1'));
    }

    public function testTemporarySignedRoute(): void
    {
        $gen = new UrlGenerator();
        $gen->register('temp', '/temp', ['GET'], []);

        $signed = new SignedUrlGenerator($gen, 'this-is-a-secret-key-1234');
        $url = $signed->temporarySignedRoute('temp', 3600);

        $this->assertStringContainsString('expires=', $url);
        $this->assertTrue($signed->validate($url));
    }

    public function testSignedUrlSecretTooShortThrows(): void
    {
        $gen = new UrlGenerator();
        $this->expectException(\InvalidArgumentException::class);
        new SignedUrlGenerator($gen, 'short');
    }

    // ── Constraints ───────────────────────────────────────────

    public function testIntegerConstraint(): void
    {
        $c = new IntegerConstraint();
        $this->assertTrue($c->matches('123'));
        $this->assertFalse($c->matches('abc'));
    }

    public function testUuidConstraint(): void
    {
        $c = new UuidConstraint();
        $this->assertTrue($c->matches('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertFalse($c->matches('not-a-uuid'));
    }

    public function testSlugConstraint(): void
    {
        $c = new SlugConstraint();
        $this->assertTrue($c->matches('hello-world'));
        $this->assertFalse($c->matches('Hello World'));
    }

    public function testUlidConstraint(): void
    {
        $c = new UlidConstraint();
        $this->assertTrue($c->matches('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertFalse($c->matches('short'));
    }

    public function testDateConstraint(): void
    {
        $c = new DateConstraint();
        $this->assertTrue($c->matches('2026-04-10'));
        $this->assertFalse($c->matches('not-a-date'));
    }

    public function testIpConstraint(): void
    {
        $c = new IpConstraint();
        $this->assertTrue($c->matches('192.168.1.1'));
        $this->assertTrue($c->matches('::1'));
        $this->assertFalse($c->matches('not-ip'));
    }

    public function testConstraintFactory(): void
    {
        $this->assertInstanceOf(IntegerConstraint::class, RouteConstraints::get('int'));
        $this->assertInstanceOf(UuidConstraint::class, RouteConstraints::get('uuid'));
        $this->assertInstanceOf(UlidConstraint::class, RouteConstraints::get('ulid'));
        $this->assertInstanceOf(DateConstraint::class, RouteConstraints::get('date'));
        $this->assertInstanceOf(IpConstraint::class, RouteConstraints::get('ip'));
    }

    // ── Attributes ────────────────────────────────────────────

    public function testRouteAttribute(): void
    {
        $attr = new Route('GET', '/test', name: 'test_route', tags: ['API']);
        $this->assertSame(['GET'], $attr->methods);
        $this->assertSame('/test', $attr->path);
        $this->assertSame('test_route', $attr->name);
        $this->assertSame(['API'], $attr->tags);
    }

    public function testRouteAttributeMultipleMethods(): void
    {
        $attr = new Route(['get', 'post'], '/dual');
        $this->assertSame(['GET', 'POST'], $attr->methods);
    }

    public function testMiddlewareAttributeAppliesToAll(): void
    {
        $attr = new Middleware('auth');
        $this->assertTrue($attr->appliesTo('index'));
        $this->assertTrue($attr->appliesTo('show'));
    }

    public function testMiddlewareAttributeOnly(): void
    {
        $attr = new Middleware('log', only: ['index']);
        $this->assertTrue($attr->appliesTo('index'));
        $this->assertFalse($attr->appliesTo('show'));
    }

    public function testMiddlewareAttributeExcept(): void
    {
        $attr = new Middleware('auth', except: ['login']);
        $this->assertFalse($attr->appliesTo('login'));
        $this->assertTrue($attr->appliesTo('dashboard'));
    }

    public function testThrottleAttribute(): void
    {
        $attr = new Throttle(max: 100, per: 3600, by: 'user');
        $this->assertSame(100, $attr->max);
        $this->assertSame(3600, $attr->per);
        $this->assertSame('user', $attr->by);
    }

    public function testWithoutMiddlewareAttribute(): void
    {
        $attr = new WithoutMiddleware(['auth', 'cors']);
        $this->assertSame(['auth', 'cors'], $attr->excluded);
    }

    public function testApiResourceAttribute(): void
    {
        $attr = new ApiResource(prefix: '/users', parameter: 'user');
        $this->assertSame('/users', $attr->prefix);
        $this->assertSame('user', $attr->parameter);
        $this->assertSame(['index', 'show', 'store', 'update', 'destroy'], $attr->actions);
    }

    public function testApiResourceAttributeOnly(): void
    {
        $attr = new ApiResource(only: ['index', 'show']);
        $this->assertSame(['index', 'show'], $attr->actions);
    }

    public function testApiResourceAttributeExcept(): void
    {
        $attr = new ApiResource(except: ['destroy']);
        $this->assertNotContains('destroy', $attr->actions);
        $this->assertContains('index', $attr->actions);
    }

    // ── RouteDebugger ─────────────────────────────────────────

    public function testDebuggerList(): void
    {
        $router = $this->makeRouter();
        $router->get('/users', $this->jsonHandler(), 'users.index');
        $router->post('/users', $this->jsonHandler(), 'users.store');

        $debugger = new RouteDebugger($router);
        $list = $debugger->list();

        $this->assertCount(2, $list);
    }

    public function testDebuggerRender(): void
    {
        $router = $this->makeRouter();
        $router->get('/users', $this->jsonHandler(), 'users.index');

        $debugger = new RouteDebugger($router);
        $output = $debugger->render();

        $this->assertStringContainsString('GET', $output);
        $this->assertStringContainsString('/users', $output);
        $this->assertStringContainsString('users.index', $output);
    }

    public function testDebuggerMatch(): void
    {
        $router = $this->makeRouter();
        $router->get('/items/{id:\\d+}', $this->jsonHandler(), 'items.show');
        $router->compile();

        $debugger = new RouteDebugger($router);
        $result = $debugger->match('GET', '/items/99');

        $this->assertTrue($result['matched']);
        $this->assertSame('items.show', $result['route']['name']);
        $this->assertSame('99', $result['params']['id']);
    }

    public function testDebuggerMatchNotFound(): void
    {
        $router = $this->makeRouter();
        $router->get('/only', $this->jsonHandler());
        $router->compile();

        $debugger = new RouteDebugger($router);
        $result = $debugger->match('GET', '/missing');

        $this->assertFalse($result['matched']);
    }

    public function testDebuggerFilter(): void
    {
        $router = $this->makeRouter();
        $router->get('/users', $this->jsonHandler(), 'users.index');
        $router->post('/users', $this->jsonHandler(), 'users.store');
        $router->get('/items', $this->jsonHandler(), 'items.index');

        $debugger = new RouteDebugger($router);

        $filtered = $debugger->filter(method: 'GET');
        $this->assertCount(2, $filtered);

        $filtered = $debugger->filter(pathContains: 'users');
        $this->assertCount(2, $filtered);

        $filtered = $debugger->filter(name: 'items');
        $this->assertCount(1, $filtered);
    }

    // ── Middleware with Router ─────────────────────────────────

    public function testRouterMiddlewareExecution(): void
    {
        $router = $this->makeRouter();

        $mw = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $r, RequestHandlerInterface $h): ResponseInterface
            {
                $response = $h->handle($r);
                return $response->withHeader('X-Custom', 'true');
            }
        };

        $router->registerMiddleware('custom', $mw);
        $router->add('GET', '/test', $this->jsonHandler(), middleware: ['custom']);

        $response = $router->dispatch($this->makeRequest('GET', '/test'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('true', $response->getHeaderLine('X-Custom'));
    }

    public function testRouterMiddlewareGroup(): void
    {
        $router = $this->makeRouter();

        $mw = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $r, RequestHandlerInterface $h): ResponseInterface
            {
                return $h->handle($r)->withHeader('X-Group', 'yes');
            }
        };

        $router->registerMiddleware('cors', $mw);
        $router->registerMiddlewareGroup('api', ['cors']);
        $router->add('GET', '/api/test', $this->jsonHandler(), middleware: ['api']);

        $response = $router->dispatch($this->makeRequest('GET', '/api/test'));
        $this->assertSame('yes', $response->getHeaderLine('X-Group'));
    }

    public function testRouterGlobalMiddleware(): void
    {
        $router = $this->makeRouter();

        $mw = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $r, RequestHandlerInterface $h): ResponseInterface
            {
                return $h->handle($r)->withHeader('X-Global', '1');
            }
        };

        $router->registerMiddleware('global-mw', $mw);
        $router->addGlobalMiddleware('global-mw');
        $router->get('/any', $this->jsonHandler());

        $response = $router->dispatch($this->makeRequest('GET', '/any'));
        $this->assertSame('1', $response->getHeaderLine('X-Global'));
    }

    // ── TrailingSlashStrategy ─────────────────────────────────

    public function testTrailingSlashEnum(): void
    {
        $this->assertSame('strip', TrailingSlashStrategy::STRIP->value);
        $this->assertSame('redirect', TrailingSlashStrategy::REDIRECT_301->value);
        $this->assertSame('both', TrailingSlashStrategy::ALLOW_BOTH->value);
    }

    // ── Logging ───────────────────────────────────────────────

    public function testLoggerNotFound(): void
    {
        $logMessages = [];
        $logger = new class($logMessages) implements \Psr\Log\LoggerInterface {
            public function __construct(private array &$messages) {}
            public function emergency(\Stringable|string $message, array $context = []): void {}
            public function alert(\Stringable|string $message, array $context = []): void {}
            public function critical(\Stringable|string $message, array $context = []): void {}
            public function error(\Stringable|string $message, array $context = []): void {}
            public function warning(\Stringable|string $message, array $context = []): void {}
            public function notice(\Stringable|string $message, array $context = []): void
            {
                $this->messages[] = ['level' => 'notice', 'message' => $message, 'context' => $context];
            }
            public function info(\Stringable|string $message, array $context = []): void {}
            public function debug(\Stringable|string $message, array $context = []): void {}
            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $router = $this->makeRouter();
        $router->setLogger($logger);
        $router->dispatch($this->makeRequest('GET', '/nonexistent'));

        $this->assertCount(1, $logMessages);
        $this->assertSame('notice', $logMessages[0]['level']);
    }
}

// Test enum for URL generator binding
enum TestStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
}
