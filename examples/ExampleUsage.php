<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Examples;

use MonkeysLegion\Router\Router;
use MonkeysLegion\Router\RouteCollection;
use MonkeysLegion\Router\RouteCache;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Router\Attributes\Middleware;
use MonkeysLegion\Router\Middleware\CorsMiddleware;
use MonkeysLegion\Router\Middleware\AuthMiddleware;
use MonkeysLegion\Router\Middleware\ThrottleMiddleware;
use MonkeysLegion\Router\Middleware\LoggingMiddleware;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use Psr\Http\Message\ServerRequestInterface;

/**
 * EXAMPLE 1: Basic Route Registration
 */
function basicRoutes(): Router
{
    $router = new Router(new RouteCollection());

    // Simple GET route
    $router->get('/users', function (ServerRequestInterface $request) {
        return new Response(
            Stream::createFromString(json_encode(['users' => []])),
            200,
            ['Content-Type' => 'application/json']
        );
    }, 'user.list');

    // Route with parameter
    $router->get('/users/{id}', function (ServerRequestInterface $request, string $id) {
        return new Response(
            Stream::createFromString(json_encode(['user' => ['id' => $id]])),
            200,
            ['Content-Type' => 'application/json']
        );
    }, 'user.show');

    // POST route
    $router->post('/users', function (ServerRequestInterface $request) {
        return new Response(
            Stream::createFromString(json_encode(['created' => true])),
            201,
            ['Content-Type' => 'application/json']
        );
    }, 'user.create');

    return $router;
}

/**
 * EXAMPLE 2: Route Constraints
 */
function routeConstraints(): Router
{
    $router = new Router(new RouteCollection());

    // Integer constraint (inline)
    $router->get('/users/{id:\d+}', function (ServerRequestInterface $request, string $id) {
        return new Response(Stream::createFromString("User ID: {$id}"));
    });

    // Named constraint
    $router->add('GET', '/users/{id}',
        function (ServerRequestInterface $request, string $id) {
            return new Response(Stream::createFromString("User ID: {$id}"));
        },
        'user.show',
        [],
        ['id' => 'int'] // Constraint
    );

    // Slug constraint
    $router->get('/posts/{slug:[a-z0-9-]+}', function (ServerRequestInterface $request, string $slug) {
        return new Response(Stream::createFromString("Post: {$slug}"));
    });

    // UUID constraint
    $router->get('/items/{uuid:uuid}', function (ServerRequestInterface $request, string $uuid) {
        return new Response(Stream::createFromString("Item: {$uuid}"));
    });

    return $router;
}

/**
 * EXAMPLE 3: Optional Parameters
 */
function optionalParameters(): Router
{
    $router = new Router(new RouteCollection());

    // Optional page parameter
    $router->get('/posts/{page?}', function (ServerRequestInterface $request, ?string $page = '1') {
        return new Response(Stream::createFromString("Page: {$page}"));
    });

    // Multiple optional parameters
    $router->get('/archive/{year}/{month?}/{day?}',
        function (ServerRequestInterface $request, string $year, ?string $month = null, ?string $day = null) {
            $parts = array_filter([$year, $month, $day]);
            return new Response(Stream::createFromString("Archive: " . implode('/', $parts)));
        }
    );

    return $router;
}

/**
 * EXAMPLE 4: Middleware
 */
function middlewareExample(): Router
{
    $router = new Router(new RouteCollection());

    // Register middleware
    $router->registerMiddleware('auth', AuthMiddleware::class);
    $router->registerMiddleware('throttle', new ThrottleMiddleware(60, 1));
    $router->registerMiddleware('cors', new CorsMiddleware());

    // Create middleware groups
    $router->registerMiddlewareGroup('api', ['cors', 'throttle']);
    $router->registerMiddlewareGroup('web', ['cors']);

    // Global middleware (applied to all routes)
    $router->addGlobalMiddleware('cors');

    // Route with middleware
    $router->add('GET', '/admin/dashboard',
        function (ServerRequestInterface $request) {
            return new Response(Stream::createFromString('Admin Dashboard'));
        },
        'admin.dashboard',
        ['auth'] // Middleware
    );

    // Route with multiple middleware
    $router->add('POST', '/api/posts',
        function (ServerRequestInterface $request) {
            return new Response(Stream::createFromString('Created'));
        },
        'api.posts.create',
        ['auth', 'throttle']
    );

    return $router;
}

/**
 * EXAMPLE 5: Route Groups
 */
function routeGroups(): Router
{
    $router = new Router(new RouteCollection());

    // API v1 group
    $router->group(function (Router $router) {
        $router->get('/users', function (ServerRequestInterface $request) {
            return new Response(Stream::createFromString('Users'));
        });

        $router->get('/posts', function (ServerRequestInterface $request) {
            return new Response(Stream::createFromString('Posts'));
        });
    })
        ->prefix('/api/v1')
        ->middleware(['cors', 'throttle'])
        ->group(fn() => null);

    // Admin group with auth
    $router->group(function (Router $router) {
        $router->get('/dashboard', function (ServerRequestInterface $request) {
            return new Response(Stream::createFromString('Dashboard'));
        });

        $router->get('/users', function (ServerRequestInterface $request) {
            return new Response(Stream::createFromString('Manage Users'));
        });
    })
        ->prefix('/admin')
        ->middleware(['auth'])
        ->group(fn() => null);

    return $router;
}

/**
 * EXAMPLE 6: Attribute-Based Controllers
 */
#[RoutePrefix('/api/users')]
#[Middleware(['cors', 'throttle'])]
class UserController
{
    #[Route('GET', '/', name: 'users.index', summary: 'List all users', tags: ['Users'])]
    public function index(ServerRequestInterface $request): Response
    {
        return new Response(
            Stream::createFromString(json_encode(['users' => []])),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    #[Route('GET', '/{id:\d+}', name: 'users.show', summary: 'Get a user', tags: ['Users'])]
    public function show(ServerRequestInterface $request, string $id): Response
    {
        return new Response(
            Stream::createFromString(json_encode(['user' => ['id' => $id]])),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    #[Route('POST', '/', name: 'users.create', summary: 'Create a user', tags: ['Users'])]
    #[Middleware('auth')]
    public function create(ServerRequestInterface $request): Response
    {
        return new Response(
            Stream::createFromString(json_encode(['created' => true])),
            201,
            ['Content-Type' => 'application/json']
        );
    }

    #[Route(['PUT', 'PATCH'], '/{id:\d+}', name: 'users.update', summary: 'Update a user', tags: ['Users'])]
    #[Middleware('auth')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        return new Response(
            Stream::createFromString(json_encode(['updated' => true])),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    #[Route('DELETE', '/{id:\d+}', name: 'users.delete', summary: 'Delete a user', tags: ['Users'])]
    #[Middleware('auth')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        return new Response(
            Stream::createFromString(''),
            204
        );
    }
}

function controllerExample(): Router
{
    $router = new Router(new RouteCollection());
    $router->registerController(new UserController());
    return $router;
}

/**
 * EXAMPLE 7: Named Routes & URL Generation
 */
function urlGeneration(): void
{
    $router = new Router(new RouteCollection());

    $router->get('/users', fn($req) => new Response(Stream::createFromString('Users')), 'users.index');
    $router->get('/users/{id}', fn($req, $id) => new Response(Stream::createFromString("User: {$id}")), 'users.show');
    $router->get('/posts/{slug}', fn($req, $slug) => new Response(Stream::createFromString("Post: {$slug}")), 'posts.show');

    // Generate URLs
    $urlGen = $router->getUrlGenerator();
    $urlGen->setBaseUrl('https://example.com');

    echo $urlGen->generate('users.index') . PHP_EOL;
    // Output: /users

    echo $urlGen->generate('users.show', ['id' => 123]) . PHP_EOL;
    // Output: /users/123

    echo $urlGen->generate('users.show', ['id' => 123], true) . PHP_EOL;
    // Output: https://example.com/users/123

    echo $urlGen->generate('posts.show', ['slug' => 'hello-world', 'preview' => 1]) . PHP_EOL;
    // Output: /posts/hello-world?preview=1

    // Using router shorthand
    echo $router->url('users.show', ['id' => 456]) . PHP_EOL;
    // Output: /users/456
}

/**
 * EXAMPLE 8: Route Caching
 */
function routeCaching(): void
{
    $cache = new RouteCache(__DIR__ . '/../cache');
    $collection = new RouteCollection();

    // Check if cache exists
    if ($cache->has()) {
        $data = $cache->load();
        if ($data) {
            $collection->import($data);
            echo "Routes loaded from cache" . PHP_EOL;
        }
    } else {
        // Register routes normally
        $collection->add('GET', '/users', fn() => null);
        $collection->add('GET', '/users/{id}', fn() => null);
        // ... more routes

        // Save to cache
        $exported = $collection->export();
        $cache->save($exported['routes'], $exported['namedRoutes']);
        echo "Routes cached" . PHP_EOL;
    }

    // Clear cache
    $cache->clear();
    echo "Cache cleared" . PHP_EOL;

    // Get cache stats
    $stats = $cache->getStats();
    print_r($stats);
}

/**
 * EXAMPLE 9: Custom Error Handlers
 */
function errorHandlers(): Router
{
    $router = new Router(new RouteCollection());

    // Custom 404 handler
    $router->setNotFoundHandler(function (ServerRequestInterface $request) {
        return new Response(
            Stream::createFromString(json_encode([
                'error' => 'Not Found',
                'path' => $request->getUri()->getPath(),
            ])),
            404,
            ['Content-Type' => 'application/json']
        );
    });

    // Custom 405 handler
    $router->setMethodNotAllowedHandler(function (ServerRequestInterface $request, array $allowed) {
        return new Response(
            Stream::createFromString(json_encode([
                'error' => 'Method Not Allowed',
                'allowed_methods' => $allowed,
            ])),
            405,
            [
                'Content-Type' => 'application/json',
                'Allow' => implode(', ', $allowed),
            ]
        );
    });

    return $router;
}

/**
 * EXAMPLE 10: Complete Application Setup
 */
function completeSetup(): Router
{
    $router = new Router(new RouteCollection());

    // Register middleware
    $router->registerMiddleware('auth', AuthMiddleware::class);
    $router->registerMiddleware('cors', new CorsMiddleware([
        'allowed_origins' => ['https://example.com'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Authorization'],
        'credentials' => true,
    ]));
    $router->registerMiddleware('throttle', new ThrottleMiddleware(100, 1));
    $router->registerMiddleware('log', new LoggingMiddleware());

    // Middleware groups
    $router->registerMiddlewareGroup('api', ['cors', 'throttle', 'log']);
    $router->registerMiddlewareGroup('web', ['cors', 'log']);

    // Global middleware
    $router->addGlobalMiddleware('cors');

    // API routes
    $router->group(function (Router $router) {
        // Public endpoints
        $router->post('/login', fn($req) => new Response(Stream::createFromString('Login')));
        $router->post('/register', fn($req) => new Response(Stream::createFromString('Register')));

        // Protected endpoints
        $router->group(function (Router $router) {
            $router->get('/me', fn($req) => new Response(Stream::createFromString('Profile')));
            $router->put('/me', fn($req) => new Response(Stream::createFromString('Update Profile')));
        })->middleware(['auth'])->group(fn() => null);
    })
        ->prefix('/api/v1')
        ->middleware(['api'])
        ->group(fn() => null);

    // Register controllers
    $router->registerController(new UserController());

    // Error handlers
    $router->setNotFoundHandler(fn($req) => new Response(
        Stream::createFromString('404 - Page not found'),
        404
    ));

    // Configure URL generator
    $router->getUrlGenerator()->setBaseUrl('https://api.example.com');

    return $router;
}