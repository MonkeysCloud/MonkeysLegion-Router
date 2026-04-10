# MonkeysLegion Router v2

High-performance, attribute-driven HTTP router for the MonkeysLegion framework.

**PHP 8.4+ required** • PSR-7/15 compliant • Compiled trie matching

## Features

| Feature | Description |
|---|---|
| **Compiled Trie Matching** | O(1) static path lookup, O(k) regex for parametric routes, method-indexed |
| **PSR-15 Middleware** | Pure `Psr\Http\Server\MiddlewareInterface` pipeline, priority-based, cursor dispatch |
| **Attribute-Driven** | `#[Route]`, `#[RoutePrefix]`, `#[Middleware]`, `#[ApiResource]`, `#[Throttle]`, `#[WithoutMiddleware]` |
| **Controller Auto-Scanning** | Zero-config directory scanning for annotated controllers |
| **Auto-CRUD** | `#[ApiResource]` generates 5 RESTful routes automatically |
| **Route Model Binding** | Objects with `id`, `getRouteKey()`, or `BackedEnum` auto-resolved in URL generation |
| **Signed URLs** | HMAC-SHA256 signed URLs with expiration support |
| **Route Cache** | Compiled routes cached as PHP files with OPcache warm-up |
| **Rate Limiting** | Per-route `#[Throttle]` attribute with IP/user/route strategies |
| **HEAD/OPTIONS** | Automatic HEAD delegation and OPTIONS responses |
| **Domain Constraints** | Per-route or per-group domain restrictions |
| **Route Debugger** | CLI-friendly route listing, filtering, and `match()` testing |

## Installation

```bash
composer require monkeyscloud/monkeyslegion-router "^2.0"
```

## Quick Start

```php
<?php
declare(strict_types=1);

use MonkeysLegion\Router\Router;
use MonkeysLegion\Router\RouteCollection;

$router = new Router(new RouteCollection());

$router->get('/users', fn($req) => new Response(
    Stream::createFromString(json_encode(['users' => []])),
    200,
    ['Content-Type' => 'application/json'],
), 'users.index');

$router->get('/users/{id:\d+}', fn($req, $id) => new Response(
    Stream::createFromString(json_encode(['id' => $id])),
    200,
), 'users.show');

$response = $router->dispatch($request);
```

## Attribute-Driven Controllers

```php
#[RoutePrefix('/api/v2/users')]
#[Middleware(['cors', 'throttle:60,1'])]
final class UserController
{
    #[Route('GET', '/', name: 'users.index', summary: 'List users', tags: ['Users'])]
    public function index(ServerRequestInterface $request): Response
    {
        return UserResource::collection($this->users->paginate())->toResponse();
    }

    #[Route('GET', '/{id:\d+}', name: 'users.show')]
    public function show(ServerRequestInterface $request, string $id): Response
    {
        return UserResource::make($this->users->findOrFail((int) $id))->toResponse();
    }

    #[Route('POST', '/', name: 'users.store')]
    #[Throttle(max: 10, per: 60)]
    public function store(CreateUserRequest $dto): Response
    {
        return UserResource::make($this->users->create($dto))->toResponse(status: 201);
    }
}
```

## #[ApiResource] Auto-CRUD

```php
#[ApiResource(prefix: '/photos', parameter: 'photo', only: ['index', 'show', 'store', 'update', 'destroy'])]
final class PhotoController
{
    public function index(ServerRequestInterface $req): Response { /* ... */ }
    public function show(ServerRequestInterface $req, string $photo): Response { /* ... */ }
    public function store(ServerRequestInterface $req): Response { /* ... */ }
    public function update(ServerRequestInterface $req, string $photo): Response { /* ... */ }
    public function destroy(ServerRequestInterface $req, string $photo): Response { /* ... */ }
}
```

Generates:
- `GET /photos` → `photos.index`
- `GET /photos/{photo:\d+}` → `photos.show`
- `POST /photos` → `photos.store`
- `PUT /photos/{photo:\d+}` → `photos.update`
- `DELETE /photos/{photo:\d+}` → `photos.destroy`

## Controller Auto-Scanning

```php
$scanner = new ControllerScanner($router);
$scanner->scan(__DIR__ . '/app/Controller', 'App\\Controller');
```

All classes with `#[Route]` or `#[ApiResource]` attributes are automatically registered.

## Route Groups

```php
$router->group()
    ->prefix('/api/v2')
    ->middleware('auth')
    ->domain('api.example.com')
    ->group(function (Router $r) {
        $r->get('/users', $handler, 'api.users.index');
        $r->post('/users', $handler, 'api.users.store');
    });
```

## Middleware

```php
// Register named middleware
$router->registerMiddleware('auth', new AuthMiddleware(), priority: 100);
$router->registerMiddleware('cors', new CorsMiddleware(), priority: 200);

// Middleware groups
$router->registerMiddlewareGroup('api', ['cors', 'auth']);

// Global middleware (runs on every request)
$router->addGlobalMiddleware('timing');

// Per-method exclusion (unique to MonkeysLegion):
#[Middleware('auth')]
class AdminController
{
    #[Route('GET', '/login')]
    #[WithoutMiddleware('auth')]  // Login doesn't need auth
    public function login(): Response { /* ... */ }
}
```

## URL Generation

```php
$router->url('users.show', ['id' => 42]);
// → /users/42

// Model binding
$router->url('users.show', ['id' => $userEntity]);
// → /users/{entity->id}

// Absolute URLs
$router->getUrlGenerator()->baseUrl = 'https://api.example.com';
$router->url('users.index', absolute: true);
// → https://api.example.com/users
```

## Signed URLs

```php
$signed = new SignedUrlGenerator($router->getUrlGenerator(), $secret);

$url = $signed->generate('verify-email', ['id' => 42], expiration: 3600);
$signed->validate($url); // true

$url = $signed->temporarySignedRoute('download', 300, ['file' => 'report.pdf']);
```

## Route Constraints

Built-in constraints: `int`, `uuid`, `ulid`, `slug`, `alpha`, `alphanumeric`, `date`, `ip`, `email`, `numeric`.

```php
$router->get('/users/{id:int}', $handler);
$router->get('/posts/{uuid:uuid}', $handler);
$router->get('/articles/{slug:slug}', $handler);
$router->get('/events/{date:date}', $handler);
$router->get('/records/{ulid:ulid}', $handler);
```

## Route Cache

```php
$cache = new RouteCache('/var/cache/routes');

if ($cache->has() && !$cache->isStale($sourceFiles)) {
    $compiled = $cache->load();
    $router->loadCompiled($compiled);
} else {
    // Register routes...
    $router->compile();
    $cache->save($router->getCompiledRoutes());
}
```

## Route Debugging

```php
$debugger = new RouteDebugger($router);

// ASCII table output
echo $debugger->render();

// Test a specific request (like Symfony's router:match)
$result = $debugger->match('GET', '/users/42');
// ['matched' => true, 'route' => [...], 'params' => ['id' => '42']]

// Filter routes
$getRoutes = $debugger->filter(method: 'GET');
$apiRoutes = $debugger->filter(pathContains: '/api');
```

## Per-Route Rate Limiting

```php
#[Route('POST', '/login')]
#[Throttle(max: 5, per: 300, by: 'ip')]  // 5 attempts per 5 minutes per IP
public function login(): Response { /* ... */ }
```

The `RouteRateLimiter` middleware reads `#[Throttle]` and returns `429 Too Many Requests` with `Retry-After` and rate limit headers.

## Architecture

```
Router (dispatch)
  ├── RouteCollection (registration, regex compilation)
  ├── RouteCompiler (splits static/dynamic, method-indexes)
  ├── CompiledRoutes (O(1) static + O(k) dynamic matching)
  ├── MiddlewarePipeline (PSR-15, cursor-based, priority-sorted)
  ├── ControllerScanner (directory auto-discovery)
  ├── UrlGenerator (named routes, model binding)
  ├── SignedUrlGenerator (HMAC signed URLs)
  ├── RouteCache (OPcache-warm compiled routes)
  └── RouteDebugger (listing, matching, filtering)
```

## Testing

```bash
composer test
# 81 tests, 156 assertions
```

## License

MIT © MonkeysCloud Team