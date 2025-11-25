# MonkeysLegion Router - API Reference

Complete API documentation for MonkeysLegion Router v1.1.

## Table of Contents

- [Router](#router)
- [RouteCollection](#routecollection)
- [RouteGroup](#routegroup)
- [UrlGenerator](#urlgenerator)
- [RouteCache](#routecache)
- [Attributes](#attributes)
- [Middleware](#middleware)
- [Constraints](#constraints)

---

## Router

Main router class for registering routes and dispatching requests.

### Constructor

```php
public function __construct(RouteCollection $routes)
```

**Parameters:**
- `$routes` (RouteCollection) - Route collection instance

**Example:**
```php
$router = new Router(new RouteCollection());
```

### HTTP Method Shortcuts

#### get()
```php
public function get(string $path, callable $handler, ?string $name = null): void
```

Register a GET route.

**Parameters:**
- `$path` (string) - URI path with optional parameters
- `$handler` (callable) - Request handler
- `$name` (string|null) - Optional route name

**Example:**
```php
$router->get('/users', function($request) { ... });
$router->get('/users/{id}', $handler, 'users.show');
```

#### post()
```php
public function post(string $path, callable $handler, ?string $name = null): void
```

Register a POST route.

#### put()
```php
public function put(string $path, callable $handler, ?string $name = null): void
```

Register a PUT route.

#### patch()
```php
public function patch(string $path, callable $handler, ?string $name = null): void
```

Register a PATCH route.

#### delete()
```php
public function delete(string $path, callable $handler, ?string $name = null): void
```

Register a DELETE route.

#### options()
```php
public function options(string $path, callable $handler, ?string $name = null): void
```

Register an OPTIONS route.

#### match()
```php
public function match(array $methods, string $path, callable $handler, ?string $name = null): void
```

Register a route for multiple HTTP methods.

**Parameters:**
- `$methods` (array) - HTTP methods: `['GET', 'POST']`
- `$path` (string) - URI path
- `$handler` (callable) - Request handler
- `$name` (string|null) - Optional route name

**Example:**
```php
$router->match(['GET', 'POST'], '/api/users', $handler);
```

#### any()
```php
public function any(string $path, callable $handler, ?string $name = null): void
```

Register a route that responds to any HTTP method.

### Advanced Registration

#### add()
```php
public function add(
    string $method,
    string $path,
    callable $handler,
    ?string $name = null,
    array $middleware = [],
    array $constraints = [],
    array $defaults = [],
    string $domain = '',
    array $meta = []
): void
```

Register a route with full configuration.

**Parameters:**
- `$method` (string) - HTTP method
- `$path` (string) - URI path
- `$handler` (callable) - Request handler
- `$name` (string|null) - Route name
- `$middleware` (array) - Middleware stack
- `$constraints` (array) - Parameter constraints
- `$defaults` (array) - Default parameter values
- `$domain` (string) - Domain constraint
- `$meta` (array) - Additional metadata

**Example:**
```php
$router->add(
    'GET',
    '/users/{id}',
    $handler,
    'users.show',
    ['auth'],
    ['id' => 'int'],
    [],
    '',
    ['version' => '1.0']
);
```

### URL Generation

#### url()
```php
public function url(string $name, array $parameters = [], bool $absolute = false): string
```

Generate URL for named route.

**Parameters:**
- `$name` (string) - Route name
- `$parameters` (array) - Route parameters
- `$absolute` (bool) - Generate absolute URL

**Returns:** Generated URL string

**Example:**
```php
$url = $router->url('users.show', ['id' => 123]);
// Output: /users/123
```

### Request Dispatching

#### dispatch()
```php
public function dispatch(ServerRequestInterface $request): ResponseInterface
```

Dispatch PSR-7 request to matching route.

**Parameters:**
- `$request` (ServerRequestInterface) - PSR-7 request

**Returns:** ResponseInterface

---

## RouteCollection

Manages route storage, sorting, and retrieval.

### add()
```php
public function add(
    string $method,
    string $path,
    callable $handler,
    string $name = '',
    array $middleware = [],
    array $constraints = [],
    array $defaults = [],
    string $domain = '',
    array $meta = []
): void
```

Add a route to the collection.

### all()
```php
public function all(): array
```

Get all routes sorted by specificity.

### getByName()
```php
public function getByName(string $name): ?array
```

Get route by name.

### export()
```php
public function export(): array
```

Export routes for caching.

### import()
```php
public function import(array $data): void
```

Import routes from cache.

---

## UrlGenerator

Generates URLs from named routes.

### register()
```php
public function register(string $name, string $path, array $methods, array $paramNames): void
```

Register a named route.

### generate()
```php
public function generate(string $name, array $parameters = [], bool $absolute = false): string
```

Generate URL for route.

**Throws:** InvalidArgumentException if route not found

---

## RouteCache

Manages route caching for production.

### Constructor
```php
public function __construct(string $cacheDir)
```

### has()
```php
public function has(): bool
```

Check if cache exists.

### load()
```php
public function load(): ?array
```

Load routes from cache.

### save()
```php
public function save(array $routes, array $namedRoutes): bool
```

Save routes to cache.

### clear()
```php
public function clear(): bool
```

Clear cache.

---

## Attributes

### Route

```php
#[Route(
    string|array $methods,
    string $path,
    string $name = '',
    string $summary = '',
    array $tags = [],
    array $middleware = [],
    array $where = [],
    array $defaults = [],
    string $domain = '',
    string $description = '',
    array $meta = []
)]
```

Define a route on a controller method.

### RoutePrefix

```php
#[RoutePrefix(
    string $prefix,
    array $middleware = []
)]
```

Set prefix for all controller routes.

### Middleware

```php
#[Middleware(string|array $middleware)]
```

Apply middleware to controller or method.

---

## Built-in Constraints

- `int` / `integer` - Integer values
- `numeric` - Numeric values with decimals
- `alpha` - Alphabetic characters
- `alphanumeric` / `alphanum` - Alphanumeric characters
- `slug` - URL slugs (lowercase, hyphens)
- `uuid` - UUID format
- `email` - Email format

---

**Version:** 1.1.0
**Last Updated:** November 2025