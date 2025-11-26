# MonkeysLegion Router

A comprehensive, modern HTTP router for PHP 8.4+ with attribute-based routing, middleware support, named routes, route constraints, and more.

## Features

✅ **Attribute-Based Routing** - Use PHP 8 attributes to define routes on controller methods  
✅ **Middleware Support** - Route-level, controller-level, and global middleware  
✅ **Named Routes** - Generate URLs from route names  
✅ **Route Constraints** - Validate parameters with built-in or custom constraints  
✅ **Route Groups** - Organize routes with shared prefixes and middleware  
✅ **Optional Parameters** - Support for optional route segments  
✅ **Route Caching** - Cache compiled routes for production performance  
✅ **CORS Support** - Built-in CORS middleware  
✅ **Method Handlers** - Convenient methods: `get()`, `post()`, `put()`, `delete()`, `patch()`  
✅ **Custom Error Handlers** - Customizable 404 and 405 responses  
✅ **PSR-7 Compatible** - Full PSR-7 HTTP message support

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

### Registering Middleware

```php
use MonkeysLegion\Router\Middleware\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Check authentication
        if (!$this->isAuthenticated($request)) {
            return new Response(Stream::createFromString('Unauthorized'), 401);
        }
        
        return $next($request);
    }
}

// Register by name
$router->registerMiddleware('auth', AuthMiddleware::class);
$router->registerMiddleware('cors', new CorsMiddleware());
```

### Middleware Groups

```php
// Define middleware groups
$router->registerMiddlewareGroup('api', ['cors', 'throttle', 'json']);
$router->registerMiddlewareGroup('web', ['cors', 'csrf']);

// Use in routes
$router->add('GET', '/api/users', $handler, 'users.index', ['api']);
```

### Global Middleware

```php
// Applied to all routes
$router->addGlobalMiddleware('cors');
$router->addGlobalMiddleware('logging');
```

### Route Middleware

```php
// Single middleware
$router->add('GET', '/admin', $handler, 'admin.dashboard', ['auth']);

// Multiple middleware
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

1. **Use Route Caching in Production** - Significantly improves performance
2. **Group Related Routes** - Keep your routing organized
3. **Use Named Routes** - Makes URL generation easier and refactoring safer
4. **Leverage Middleware** - Keep your handlers focused on business logic
5. **Use Constraints** - Validate parameters early in the request lifecycle
6. **Set Base URL** - Configure the URL generator for absolute URL generation

## Performance Tips

- Enable route caching in production environments
- Use specific HTTP methods instead of `any()`
- Order routes from most specific to least specific (done automatically)
- Use constraints to reduce regex complexity
- Minimize global middleware

## Requirements

- PHP 8.4 or higher
- PSR-7 HTTP Message implementation
- MonkeysLegion HTTP package

## Testing

```bash
composer test
```

## License

MIT License. See LICENSE file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues, questions, or contributions, please visit:
https://github.com/MonkeysCloud/MonkeysLegion-Skeleton