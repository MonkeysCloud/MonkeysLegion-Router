# MonkeysLegion Router

A comprehensive, production-grade HTTP router for PHP 8.4+ with PSR-15 middleware, attribute-based routing, resource CRUD shortcuts, and advanced dispatch features.

## Features

### Core Routing
✅ **Attribute-Based Routing** — PHP 8 attributes on controller methods  
✅ **Named Routes** — URL generation from route names  
✅ **Route Constraints** — Built-in + custom parameter validation  
✅ **Route Groups** — Shared prefixes, middleware, and domain constraints  
✅ **Optional Parameters** — Optional route segments  
✅ **Catch-All / Wildcard Routes** — `{path+}` greedy parameter capture  
✅ **Method Handlers** — `get()`, `post()`, `put()`, `delete()`, `patch()`, `options()`  

### Middleware (PSR-15)
✅ **PSR-15 Compatible** — `MiddlewareInterface` + `RequestHandlerInterface`  
✅ **Priority-Based Ordering** — Middleware runs in priority order  
✅ **Legacy Adapter** — v2.0 callable-style middleware auto-adapted  
✅ **Parameterized Middleware** — `throttle:60,1` parsed automatically  
✅ **DI Container Support** — Lazy middleware resolution via PSR-11  
✅ **CORS Middleware** — Configurable origins, methods, headers, credentials  

### Dispatch Engine
✅ **HEAD Auto-Delegation** — HEAD automatically delegates to GET, strips body  
✅ **OPTIONS Auto-Response** — Returns `Allow` header listing available methods  
✅ **Trailing-Slash Strategy** — Configurable: `STRIP`, `REDIRECT_301`, `ALLOW_BOTH`  
✅ **Domain Constraints** — Host/subdomain enforcement with pattern capture  
✅ **Fallback Handler** — Catch-all for unmatched routes  
✅ **Redirect Routes** — Convenience `redirect()` method  

### Developer Experience
✅ **Resource Routes** — `resource()` / `apiResource()` CRUD shortcuts  
✅ **Route Debugger** — ASCII table listing with filtering  
✅ **Signed URLs** — HMAC-signed URLs with optional expiration  
✅ **Custom Error Handlers** — Customizable 404 and 405 responses  
✅ **Route Caching** — Compiled routes for production performance  
✅ **PSR-7 Compatible** — Full PSR-7 HTTP message support

## Installation

```bash
composer require monkeyscloud/monkeyslegion-router
```

## Quick Start

### Basic Routes

```php
use MonkeysLegion\Router\Router;
use MonkeysLegion\Router\RouteCollection;
use Psr\Http\Message\ServerRequestInterface;

$router = new Router(new RouteCollection());

// Simple GET route
$router->get('/users', function (ServerRequestInterface $request) {
    return new Response(
        Stream::createFromString(json_encode(['users' => []])),
        200,
        ['Content-Type' => 'application/json']
    );
});

// Route with parameter
$router->get('/users/{id}', function (ServerRequestInterface $request, string $id) {
    return new Response(
        Stream::createFromString("User ID: {$id}")
    );
});

// POST route
$router->post('/users', function (ServerRequestInterface $request) {
    // Handle user creation
});
```

### Attribute-Based Controllers

```php
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Router\Attributes\Middleware;

#[RoutePrefix('/api/users')]
#[Middleware(['cors', 'throttle'])]
class UserController
{
    #[Route('GET', '/', name: 'users.index')]
    public function index(ServerRequestInterface $request): Response
    {
        // List users
    }

    #[Route('GET', '/{id:\d+}', name: 'users.show')]
    public function show(ServerRequestInterface $request, string $id): Response
    {
        // Show user
    }

    #[Route('POST', '/', name: 'users.create')]
    #[Middleware('auth')]
    public function create(ServerRequestInterface $request): Response
    {
        // Create user
    }
}

// Register controller
$router->registerController(new UserController());
```

## Route Constraints

### Built-in Constraints

```php
// Integer constraint
$router->get('/users/{id:\d+}', $handler);
$router->get('/users/{id:int}', $handler);

// Slug constraint
$router->get('/posts/{slug:[a-z0-9-]+}', $handler);
$router->get('/posts/{slug:slug}', $handler);

// UUID constraint
$router->get('/items/{uuid:uuid}', $handler);

// Email constraint
$router->get('/verify/{email:email}', $handler);

// Numeric constraint
$router->get('/price/{amount:numeric}', $handler);

// Alphabetic constraint
$router->get('/category/{name:alpha}', $handler);

// Alphanumeric constraint
$router->get('/code/{code:alphanum}', $handler);
```

### Custom Constraints

```php
use MonkeysLegion\Router\Constraints\RouteConstraintInterface;

class DateConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    public function getPattern(): string
    {
        return '\d{4}-\d{2}-\d{2}';
    }
}

// Use custom regex directly
$router->get('/archive/{date:\d{4}-\d{2}-\d{2}}', $handler);
```

## Optional Parameters

```php
// Optional page parameter
$router->get('/posts/{page?}', function (ServerRequestInterface $request, ?string $page = '1') {
    // Handle pagination
});

// Multiple optional parameters
$router->get('/archive/{year}/{month?}/{day?}', $handler);

// With constraints
$router->get('/posts/{category}/{page:\d+?}', $handler);
```

## Middleware

### PSR-15 Middleware (v2.2+)

```php
use MonkeysLegion\Router\Middleware\MiddlewareInterface;
use MonkeysLegion\Router\Middleware\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isAuthenticated($request)) {
            return new Response(Stream::createFromString('Unauthorized'), 401);
        }
        
        return $handler->handle($request);
    }
}

// Register by name
$router->registerMiddleware('auth', AuthMiddleware::class);
```

### Legacy Middleware (v2.0 — still supported)

Existing v2.0 middleware using `callable $next` is automatically adapted:

```php
// This still works — auto-wrapped by LegacyMiddlewareAdapter
class OldMiddleware
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request);
    }
}
```

### Middleware Priority

```php
use MonkeysLegion\Router\Middleware\MiddlewarePipeline;

$pipeline = new MiddlewarePipeline();
$pipeline->pipe($corsMiddleware, 100);   // Runs first (highest priority)
$pipeline->pipe($authMiddleware, 50);
$pipeline->pipe($loggingMiddleware, 10); // Runs last
```

### Parameterized Middleware

```php
// Middleware string with parameters: "throttle:60,1"
// Parsed automatically — 60 requests per 1 minute
$router->add('GET', '/api/data', $handler, middleware: ['throttle:60,1']);
```

### DI Container Integration

```php
// Set a PSR-11 container for lazy middleware resolution
$router->setContainer($container);

// Middleware registered as class-string is resolved via container
$router->registerMiddleware('auth', AuthMiddleware::class);
```

### Middleware Groups

```php
$router->registerMiddlewareGroup('api', ['cors', 'throttle', 'json']);
$router->registerMiddlewareGroup('web', ['cors', 'csrf']);

$router->add('GET', '/api/users', $handler, 'users.index', ['api']);
```

### Global Middleware

```php
$router->addGlobalMiddleware('cors');
$router->addGlobalMiddleware('logging');
```

### Route Middleware

```php
$router->add('GET', '/admin', $handler, 'admin.dashboard', ['auth']);
$router->add('POST', '/api/posts', $handler, 'posts.create', ['auth', 'throttle']);

// With attributes
#[Route('GET', '/admin', middleware: ['auth', 'admin'])]
public function dashboard() { }
```

## Route Groups

```php
// Group with prefix
$router->group(function (Router $router) {
    $router->get('/users', $usersHandler);
    $router->get('/posts', $postsHandler);
})
->prefix('/api/v1')
->middleware(['cors', 'throttle'])
->group(fn() => null);

// Nested groups
$router->group(function (Router $router) {
    $router->group(function (Router $router) {
        $router->get('/dashboard', $dashboardHandler);
        $router->get('/settings', $settingsHandler);
    })
    ->middleware(['admin'])
    ->group(fn() => null);
})
->prefix('/admin')
->middleware(['auth'])
->group(fn() => null);

// Routes will be: /admin/dashboard, /admin/settings
// Middleware stack: ['auth', 'admin']
```

## Named Routes & URL Generation

```php
// Define named routes
$router->get('/users', $handler, 'users.index');
$router->get('/users/{id}', $handler, 'users.show');
$router->get('/posts/{slug}', $handler, 'posts.show');

// Generate URLs
$urlGen = $router->getUrlGenerator();
$urlGen->setBaseUrl('https://example.com');

echo $router->url('users.index');
// Output: /users

echo $router->url('users.show', ['id' => 123]);
// Output: /users/123

echo $router->url('users.show', ['id' => 123], true);
// Output: https://example.com/users/123

// Extra parameters become query string
echo $router->url('posts.show', ['slug' => 'hello', 'preview' => 1]);
// Output: /posts/hello?preview=1
```

## Route Caching

```php
use MonkeysLegion\Router\RouteCache;

$cache = new RouteCache(__DIR__ . '/cache');
$collection = new RouteCollection();

// Load from cache if available
if ($cache->has()) {
    $data = $cache->load();
    $collection->import($data);
} else {
    // Register all routes
    $router = new Router($collection);
    // ... register routes ...
    
    // Save to cache
    $exported = $collection->export();
    $cache->save($exported['routes'], $exported['namedRoutes']);
}

// Clear cache
$cache->clear();

// Check cache stats
$stats = $cache->getStats();
```

## Custom Error Handlers

```php
// Custom 404 handler
$router->setNotFoundHandler(function (ServerRequestInterface $request) {
    return new Response(
        Stream::createFromString(json_encode(['error' => 'Not Found'])),
        404,
        ['Content-Type' => 'application/json']
    );
});

// Custom 405 handler
$router->setMethodNotAllowedHandler(
    function (ServerRequestInterface $request, array $allowedMethods) {
        return new Response(
            Stream::createFromString(json_encode([
                'error' => 'Method Not Allowed',
                'allowed' => $allowedMethods
            ])),
            405,
            [
                'Content-Type' => 'application/json',
                'Allow' => implode(', ', $allowedMethods)
            ]
        );
    }
);
```

## Built-in Middleware

### CORS Middleware

```php
use MonkeysLegion\Router\Middleware\CorsMiddleware;

$cors = new CorsMiddleware([
    'allowed_origins' => ['https://example.com', 'https://app.example.com'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'exposed_headers' => ['X-Total-Count'],
    'max_age' => 86400,
    'credentials' => true,
]);

$router->registerMiddleware('cors', $cors);
```

### Throttle Middleware

```php
use MonkeysLegion\Router\Middleware\ThrottleMiddleware;

// 60 requests per minute
$throttle = new ThrottleMiddleware(60, 1);
$router->registerMiddleware('throttle', $throttle);
```

## Advanced Features

### All HTTP Methods

```php
$router->get($path, $handler);      // GET
$router->post($path, $handler);     // POST
$router->put($path, $handler);      // PUT
$router->patch($path, $handler);    // PATCH
$router->delete($path, $handler);   // DELETE
$router->options($path, $handler);  // OPTIONS

// Multiple methods
$router->match(['GET', 'POST'], $path, $handler);

// Any method
$router->any($path, $handler);
```

### HEAD & OPTIONS Auto-Handling

```php
// HEAD requests automatically delegate to the matching GET handler,
// with the response body stripped (per RFC 7231)
$router->get('/data', $handler);
// HEAD /data → 200, empty body, same headers as GET

// OPTIONS requests automatically return an Allow header
// listing all methods registered for that path
// OPTIONS /data → Allow: GET, HEAD, OPTIONS
```

### Trailing-Slash Strategy

```php
use MonkeysLegion\Router\TrailingSlashStrategy;

// Default: strip trailing slashes (matches /users and /users/)
$router->setTrailingSlashStrategy(TrailingSlashStrategy::STRIP);

// 301 redirect from /users/ → /users
$router->setTrailingSlashStrategy(TrailingSlashStrategy::REDIRECT_301);

// Match both /users and /users/ without redirect
$router->setTrailingSlashStrategy(TrailingSlashStrategy::ALLOW_BOTH);
```

### Catch-All / Wildcard Routes

```php
// {param+} captures everything including slashes
$router->get('/files/{path+}', function ($request, string $path) {
    // GET /files/docs/readme.md → $path = "docs/readme.md"
    return serveFile($path);
});
```

### Domain / Host Constraints

```php
// Literal domain
$router->add('GET', '/dashboard', $handler, domain: 'admin.example.com');

// Pattern with parameter capture
$router->add('GET', '/home', $handler, domain: '{tenant}.app.com');
// Matches: acme.app.com/home, corp.app.com/home, etc.
```

### Fallback Handler

```php
// Catch all unmatched routes (custom 404)
$router->fallback(function ($request) {
    return new Response(Stream::createFromString('Page not found'), 404);
});
```

### Redirect Routes

```php
// Convenience redirect
$router->redirect('/old-page', '/new-page', 301);
$router->redirect('/legacy', '/modern');  // 302 by default
```

### Resource / CRUD Routes

```php
// Full resource: index, create, store, show, edit, update, destroy
$router->resource('/photos', new PhotoController());

// API-only: index, store, show, update, destroy (no create/edit forms)
$router->apiResource('/photos', new PhotoController());

// Filter actions
$router->resource('/photos', $ctrl)->only(['index', 'show']);
$router->resource('/photos', $ctrl)->except(['destroy']);
```

Registered routes are automatically named: `photos.index`, `photos.show`, `photos.store`, etc.

### Route Debugger

```php
use MonkeysLegion\Router\RouteDebugger;

$debugger = new RouteDebugger($router);

// ASCII table output
echo $debugger->render();
// +--------+-------------------+----------------------+------------+--------+
// | Method | URI               | Name                 | Middleware | Domain |
// +--------+-------------------+----------------------+------------+--------+
// | GET    | /                 | home                 |            |        |
// | GET    | /users/{id}       | users.show           | auth       |        |
// | POST   | /api/users        | api.users.store      | auth, cors |        |
// +--------+-------------------+----------------------+------------+--------+

// Filter routes
$debugger->filter(method: 'POST');               // Only POST routes
$debugger->filter(pathContains: '/api');          // Routes containing /api
$debugger->filter(name: 'users');                 // Routes named *users*

// Structured data
$routes = $debugger->list();                     // Array of route maps
```

### Signed URLs

```php
use MonkeysLegion\Router\SignedUrlGenerator;

$signed = new SignedUrlGenerator($router->getUrlGenerator(), 'your-secret-key-here');

// Generate a signed URL (never expires)
$url = $signed->generate('verify-email', ['id' => 42]);
// → /verify-email/42?signature=abc123…

// With expiration (1 hour)
$url = $signed->generate('download', ['file' => 'report'], expiration: 3600);
// → /download/report?expires=1700003600&signature=def456…

// Validate
$isValid = $signed->validate($urlFromRequest);  // true/false
```

### Dispatching Requests

```php
use Psr\Http\Message\ServerRequestInterface;

$request = /* PSR-7 ServerRequest */;
$response = $router->dispatch($request);

// Send response
header('HTTP/1.1 ' . $response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

### Route Metadata

```php
#[Route(
    'GET',
    '/users',
    name: 'users.index',
    summary: 'List all users',
    description: 'Returns a paginated list of users',
    tags: ['Users', 'API'],
    meta: ['version' => '1.0', 'deprecated' => false]
)]
public function index() { }
```

## Best Practices

1. **Use Route Caching in Production** — Significantly improves performance
2. **Group Related Routes** — Keep your routing organized
3. **Use Named Routes** — Makes URL generation easier and refactoring safer
4. **Use PSR-15 Middleware** — New code should use `MiddlewareInterface` with `RequestHandlerInterface`
5. **Use Constraints** — Validate parameters early in the request lifecycle
6. **Use Resource Routes** — `resource()` / `apiResource()` reduce boilerplate
7. **Set a Trailing-Slash Strategy** — Choose `STRIP`, `REDIRECT_301`, or `ALLOW_BOTH` globally
8. **Set Base URL** — Configure the URL generator for absolute URL generation

## Performance Tips

- Enable route caching in production environments
- Use specific HTTP methods instead of `any()`
- Order routes from most specific to least specific (done automatically)
- Use constraints to reduce regex complexity
- Minimize global middleware
- Use middleware priority to ensure expensive middleware runs only when needed

## Requirements

- PHP 8.4 or higher
- PSR-7 HTTP Message implementation
- MonkeysLegion HTTP package

## Testing

```bash
composer test
# 53 tests, 102 assertions
```

## License

MIT License. See LICENSE file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues, questions, or contributions, please visit:
https://github.com/MonkeysCloud/MonkeysLegion-Skeleton