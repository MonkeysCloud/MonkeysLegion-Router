<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Router\Attributes\Route as RouteAttribute;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Router\Attributes\Middleware as MiddlewareAttribute;
use MonkeysLegion\Router\Middleware\MiddlewareInterface;
use MonkeysLegion\Router\Middleware\MiddlewarePipeline;
use MonkeysLegion\Router\Middleware\LegacyMiddlewareAdapter;
use MonkeysLegion\Router\TrailingSlashStrategy;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;

/**
 * Enhanced HTTP router for MonkeysLegion with middleware, named routes, and route groups.
 *
 * v2.2 additions:
 *  - Optional DI container for lazy middleware resolution
 *  - Parameterized middleware parsing (`'throttle:60,1'`)
 *  - Priority-aware middleware pipeline (PSR-15 aligned)
 *  - HEAD auto-delegation to GET handlers
 *  - OPTIONS auto-response with allowed methods
 *  - Domain/host constraint enforcement
 *  - Configurable trailing-slash strategy
 */
class Router
{
    private UrlGenerator $urlGenerator;

    /**
     * @var array<string, MiddlewareInterface|object> Registered middleware
     */
    private array $middleware = [];

    /**
     * @var array<string, array{priority: int}> Middleware priority map
     */
    private array $middlewarePriority = [];

    /**
     * @var array<string, array<string>> Middleware groups
     */
    private array $middlewareGroups = [];

    /**
     * @var array<string> Global middleware applied to all routes
     */
    private array $globalMiddleware = [];

    // Current group context
    private string $currentPrefix = '';
    private array $currentMiddleware = [];
    private array $currentWhere = [];
    private string $currentDomain = '';

    // Error handlers
    /** @var null|callable */
    private $notFoundHandler = null;

    /** @var null|callable */
    private $methodNotAllowedHandler = null;

    /** @var ContainerInterface|null DI container for lazy middleware resolution */
    private ?ContainerInterface $container = null;

    /** @var TrailingSlashStrategy Trailing-slash handling strategy */
    private TrailingSlashStrategy $trailingSlashStrategy = TrailingSlashStrategy::STRIP;

    /** @var callable|null Fallback handler (catch-all) */
    private $fallbackHandler = null;

    public function __construct(
        private RouteCollection $routes
    ) {
        $this->urlGenerator = new UrlGenerator();
    }

    // ─── Container ──────────────────────────────────────────────────────

    /**
     * Set a PSR-11 container for lazy middleware resolution.
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Set the trailing-slash strategy.
     */
    public function setTrailingSlashStrategy(TrailingSlashStrategy $strategy): void
    {
        $this->trailingSlashStrategy = $strategy;
    }

    /**
     * Get the trailing-slash strategy.
     */
    public function getTrailingSlashStrategy(): TrailingSlashStrategy
    {
        return $this->trailingSlashStrategy;
    }

    /**
     * Register a fallback handler (catch-all for unmatched routes).
     */
    public function fallback(callable $handler): void
    {
        $this->fallbackHandler = $handler;
    }

    /**
     * Register a convenience redirect route.
     */
    public function redirect(string $from, string $to, int $status = 302): void
    {
        $this->get($from, function () use ($to, $status) {
            return new Response(
                Stream::createFromString(''),
                $status,
                ['Location' => $to]
            );
        });
    }

    // ─── Route registration ─────────────────────────────────────────────

    /**
     * Add a new route definition
     */
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
    ): void {
        // Apply current group context
        $path = $this->currentPrefix . '/' . ltrim($path, '/');
        $middleware = array_merge($this->currentMiddleware, $middleware);
        $constraints = array_merge($this->currentWhere, $constraints);
        $domain = $domain ?: $this->currentDomain;

        $this->routes->add(
            $method,
            $path,
            $handler,
            $name ?? '',
            $middleware,
            $constraints,
            $defaults,
            $domain,
            $meta
        );

        // Register with URL generator if named
        if ($name) {
            $paramNames = $this->extractParamNames($path);
            $this->urlGenerator->register($name, $path, [$method], $paramNames);
        }
    }

    /**
     * Add a GET route
     */
    public function get(string $path, callable $handler, ?string $name = null): void
    {
        $this->add('GET', $path, $handler, $name);
    }

    /**
     * Add a POST route
     */
    public function post(string $path, callable $handler, ?string $name = null): void
    {
        $this->add('POST', $path, $handler, $name);
    }

    /**
     * Add a PUT route
     */
    public function put(string $path, callable $handler, ?string $name = null): void
    {
        $this->add('PUT', $path, $handler, $name);
    }

    /**
     * Add a DELETE route
     */
    public function delete(string $path, callable $handler, ?string $name = null): void
    {
        $this->add('DELETE', $path, $handler, $name);
    }

    /**
     * Add a PATCH route
     */
    public function patch(string $path, callable $handler, ?string $name = null): void
    {
        $this->add('PATCH', $path, $handler, $name);
    }

    /**
     * Add an OPTIONS route
     */
    public function options(string $path, callable $handler, ?string $name = null): void
    {
        $this->add('OPTIONS', $path, $handler, $name);
    }

    /**
     * Add a route that responds to any HTTP method
     */
    public function any(string $path, callable $handler, ?string $name = null): void
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $method) {
            $this->add($method, $path, $handler, $name);
        }
    }

    /**
     * Add a route that responds to multiple HTTP methods
     */
    public function match(array $methods, string $path, callable $handler, ?string $name = null): void
    {
        foreach ($methods as $method) {
            $this->add($method, $path, $handler, $name);
        }
    }

    /**
     * Register a full CRUD resource (index, create, store, show, edit, update, destroy).
     *
     * @return RouteRegistrar  Fluent registrar — call `->only()` or `->except()` to filter.
     */
    public function resource(string $prefix, object $controller): RouteRegistrar
    {
        $registrar = new RouteRegistrar($this, $prefix, $controller);
        $registrar->register();
        return $registrar;
    }

    /**
     * Register API-only CRUD resource (index, store, show, update, destroy — no create/edit).
     *
     * @return RouteRegistrar  Fluent registrar — call `->only()` or `->except()` to filter.
     */
    public function apiResource(string $prefix, object $controller): RouteRegistrar
    {
        $registrar = RouteRegistrar::api($this, $prefix, $controller);
        $registrar->register();
        return $registrar;
    }

    /**
     * Create a route group with shared attributes.
     *
     * Usage:
     *   // Fluent style with extra attributes
     *   $router->group()
     *       ->prefix('/api')
     *       ->middleware('auth')
     *       ->group(function (Router $r) {
     *           $r->get('/users', $handler, 'api.users.index');
     *       });
     *
     *   // Simple callback style
     *   $router->group(function (Router $r) {
     *       $r->get('/health', $handler, 'health');
     *   });
     */
    public function group(?callable $callback = null): RouteGroup
    {
        $group = new RouteGroup($this);

        if ($callback !== null) {
            // No extra attributes configured on the group itself;
            // just execute the callback inside the current router context.
            $group->group($callback);
        }

        return $group;
    }

    /**
     * Register a controller with Route attributes
     */
    public function registerController(object $controller): void
    {
        $ref = new \ReflectionClass($controller);

        // Get controller-level prefix
        $controllerPrefix = '';
        $controllerMiddleware = [];

        foreach ($ref->getAttributes(RoutePrefix::class) as $attr) {
            /** @var RoutePrefix $prefix */
            $prefix = $attr->newInstance();
            $controllerPrefix = $prefix->prefix;
            $controllerMiddleware = array_merge($controllerMiddleware, $prefix->middleware);
        }

        // Get controller-level middleware
        foreach ($ref->getAttributes(MiddlewareAttribute::class) as $attr) {
            /** @var MiddlewareAttribute $mw */
            $mw = $attr->newInstance();
            $controllerMiddleware = array_merge($controllerMiddleware, $mw->middleware);
        }

        // Register each method with Route attributes
        foreach ($ref->getMethods() as $method) {
            $methodMiddleware = $controllerMiddleware;

            // Get method-level middleware
            foreach ($method->getAttributes(MiddlewareAttribute::class) as $attr) {
                /** @var MiddlewareAttribute $mw */
                $mw = $attr->newInstance();
                $methodMiddleware = array_merge($methodMiddleware, $mw->middleware);
            }

            // Register each Route attribute
            foreach ($method->getAttributes(RouteAttribute::class) as $attr) {
                /** @var RouteAttribute $meta */
                $meta = $attr->newInstance();

                $fullPath = $controllerPrefix . $meta->path;
                $fullPath = rtrim($fullPath, '/') ?: '/';
                $middleware = array_merge($methodMiddleware, $meta->middleware);

                // Register for each HTTP method
                foreach ($meta->methods as $httpMethod) {
                    $this->routes->add(
                        $httpMethod,
                        $fullPath,
                        [$controller, $method->getName()],
                        $meta->name,
                        $middleware,
                        $meta->where,
                        $meta->defaults,
                        $meta->domain,
                        [
                            'summary' => $meta->summary,
                            'tags' => $meta->tags,
                            'description' => $meta->description,
                            'meta' => $meta->meta,
                        ]
                    );

                    // Register with URL generator
                    if ($meta->name) {
                        $paramNames = $this->extractParamNames($fullPath);
                        $this->urlGenerator->register($meta->name, $fullPath, [$httpMethod], $paramNames);
                    }
                }
            }
        }
    }

    // ─── Middleware registration ─────────────────────────────────────────

    /**
     * Register middleware by name.
     *
     * Accepts both v2.2 PSR-15 MiddlewareInterface and v2.0 legacy
     * middleware (auto-adapted).
     */
    public function registerMiddleware(string $name, object|string $middleware, int $priority = 0): void
    {
        if (is_string($middleware)) {
            if (!class_exists($middleware)) {
                throw new InvalidArgumentException("Middleware class '{$middleware}' does not exist.");
            }
            $middleware = $this->instantiateMiddleware($middleware);
        }

        // Adapt legacy middleware transparently
        if (!$middleware instanceof MiddlewareInterface && is_object($middleware)) {
            if (method_exists($middleware, 'process')) {
                $middleware = new LegacyMiddlewareAdapter($middleware);
            } else {
                throw new InvalidArgumentException("Middleware must implement MiddlewareInterface or have a process() method.");
            }
        }

        $this->middleware[$name] = $middleware;
        $this->middlewarePriority[$name] = ['priority' => $priority];
    }

    /**
     * Register a middleware group
     */
    public function registerMiddlewareGroup(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    /**
     * Add global middleware applied to all routes
     */
    public function addGlobalMiddleware(string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * Resolve middleware by name, with optional parameterized parsing.
     *
     * Supports `'throttle:60,1'` syntax — the name before `:` is the
     * middleware identifier, the part after is comma-separated parameters
     * passed to the middleware if it implements a `setParameters()` method
     * or accepts constructor arguments.
     */
    private function resolveMiddleware(string $name): ?MiddlewareInterface
    {
        // Parse parameters: 'throttle:60,1' → name = 'throttle', params = ['60', '1']
        $params = [];
        if (str_contains($name, ':')) {
            [$name, $paramStr] = explode(':', $name, 2);
            $params = explode(',', $paramStr);
        }

        // Check if it's a registered middleware
        if (isset($this->middleware[$name])) {
            $mw = $this->middleware[$name];
            $this->applyParameters($mw, $params);
            return $mw;
        }

        // Check if it's a middleware group
        if (isset($this->middlewareGroups[$name])) {
            return null;
        }

        // Try resolving from DI container
        if ($this->container !== null && $this->container->has($name)) {
            $instance = $this->container->get($name);
            if ($instance instanceof MiddlewareInterface) {
                $this->applyParameters($instance, $params);
                return $instance;
            }
            if (is_object($instance) && method_exists($instance, 'process')) {
                $adapted = new LegacyMiddlewareAdapter($instance);
                return $adapted;
            }
        }

        // Try to instantiate as class name
        if (class_exists($name)) {
            $instance = $this->instantiateMiddleware($name, $params);
            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }
            if (is_object($instance) && method_exists($instance, 'process')) {
                return new LegacyMiddlewareAdapter($instance);
            }
        }

        return null;
    }

    /**
     * Expand middleware groups into individual middleware
     */
    private function expandMiddleware(array $middlewareList): array
    {
        $expanded = [];

        foreach ($middlewareList as $middleware) {
            // Strip parameters for group lookup
            $baseName = str_contains($middleware, ':')
                ? explode(':', $middleware, 2)[0]
                : $middleware;

            if (isset($this->middlewareGroups[$baseName])) {
                $expanded = array_merge($expanded, $this->expandMiddleware($this->middlewareGroups[$baseName]));
            } else {
                $expanded[] = $middleware;
            }
        }

        return $expanded;
    }

    // ─── Dispatch ───────────────────────────────────────────────────────

    /**
     * Dispatch a PSR-7 request to the matching route.
     *
     * v2.2 enhancements:
     *  - Configurable trailing-slash strategy
     *  - HEAD auto-delegation to GET (body stripped)
     *  - OPTIONS auto-response with allowed methods
     *  - Domain constraint enforcement
     *  - Fallback handler support
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $rawPath = $request->getUri()->getPath();

        // ── Trailing-slash strategy ──────────────────────────────────
        $path = $this->normalizeTrailingSlash($rawPath, $method);
        if ($path instanceof ResponseInterface) {
            return $path; // REDIRECT_301 was triggered
        }

        $host = $request->getUri()->getHost();
        $isHead = ($method === 'HEAD');
        $allowedMethods = [];

        // If HEAD, try to match HEAD first, then fall back to GET
        $methodsToTry = $isHead ? ['HEAD', 'GET'] : [$method];

        foreach ($methodsToTry as $tryMethod) {
            foreach ($this->routes->all() as $route) {
                $routeMethod = strtoupper($route['method']);

                // ── Domain constraint enforcement ───────────────────
                if (!empty($route['domain']) && !$this->matchesDomain($route['domain'], $host)) {
                    continue;
                }

                // Check method match
                if ($routeMethod !== $tryMethod) {
                    // Track allowed methods for this path
                    if (preg_match($route['regex'], $path)) {
                        $allowedMethods[] = $routeMethod;
                    }
                    continue;
                }

                // Check path match
                if (!preg_match($route['regex'], $path, $matches)) {
                    continue;
                }

                // Attach named parameters to request attributes
                foreach ($route['paramNames'] as $name) {
                    $value = $matches[$name] ?? ($route['defaults'][$name] ?? null);
                    if ($value !== null) {
                        $request = $request->withAttribute($name, $value);
                    }
                }

                // Prepare middleware pipeline
                $middlewareList = array_merge(
                    $this->globalMiddleware,
                    $this->expandMiddleware($route['middleware'])
                );

                $pipeline = new MiddlewarePipeline();

                foreach ($middlewareList as $mwName) {
                    $mwInstance = $this->resolveMiddleware($mwName);
                    if ($mwInstance !== null) {
                        $priority = $this->getMiddlewarePriority($mwName);
                        $pipeline->pipe($mwInstance, $priority);
                    }
                }

                // Create the final handler
                $finalHandler = function ($req) use ($route, $matches) {
                    $params = [];
                    foreach ($route['paramNames'] as $name) {
                        if (isset($matches[$name])) {
                            $params[] = $matches[$name];
                        } elseif (isset($route['defaults'][$name])) {
                            $params[] = $route['defaults'][$name];
                        } elseif (in_array($name, $route['optionalParams'], true)) {
                            continue;
                        } else {
                            $params[] = null;
                        }
                    }
                    return call_user_func_array($route['handler'], array_merge([$req], $params));
                };

                $response = $pipeline->process($request, $finalHandler);

                // ── HEAD: strip response body ───────────────────────
                if ($isHead && $tryMethod === 'GET') {
                    $response = $response->withBody(Stream::createFromString(''));
                }

                return $response;
            }
        }

        // ── OPTIONS auto-response ────────────────────────────────────
        if ($method === 'OPTIONS' && !empty($allowedMethods)) {
            $allowedMethods[] = 'OPTIONS';
            $uniqueMethods = array_unique($allowedMethods);
            sort($uniqueMethods);

            return new Response(
                Stream::createFromString(''),
                200,
                [
                    'Allow' => implode(', ', $uniqueMethods),
                    'Content-Length' => '0',
                ]
            );
        }

        // No route matched
        if (!empty($allowedMethods)) {
            $uniqueMethods = array_unique($allowedMethods);
            // HEAD should always be included if GET is allowed
            if (in_array('GET', $uniqueMethods, true) && !in_array('HEAD', $uniqueMethods, true)) {
                $uniqueMethods[] = 'HEAD';
            }
            return $this->handleMethodNotAllowed($request, $uniqueMethods);
        }

        // Fallback handler
        if ($this->fallbackHandler) {
            return call_user_func($this->fallbackHandler, $request);
        }

        // Path not found
        return $this->handleNotFound($request);
    }

    /**
     * Normalize the trailing slash based on the configured strategy.
     *
     * @return string|ResponseInterface  Normalized path or a redirect response.
     */
    private function normalizeTrailingSlash(string $path, string $method): string|ResponseInterface
    {
        if ($path === '/') {
            return $path;
        }

        return match ($this->trailingSlashStrategy) {
            TrailingSlashStrategy::STRIP => rtrim($path, '/'),

            TrailingSlashStrategy::REDIRECT_301 => str_ends_with($path, '/')
                ? new Response(
                    Stream::createFromString(''),
                    301,
                    ['Location' => rtrim($path, '/')]
                )
                : $path,

            TrailingSlashStrategy::ALLOW_BOTH => $path,
        };
    }

    /**
     * Check if a host matches a domain constraint pattern.
     *
     * Supports `{subdomain}.example.com` parameter capture.
     */
    private function matchesDomain(string $pattern, string $host): bool
    {
        // Simple literal match
        if ($pattern === $host) {
            return true;
        }

        // Replace parameter placeholders BEFORE quoting so the [^.]+ isn't escaped
        $withPlaceholders = preg_replace('/\{[^}]+\}/', '__DOMAIN_PARAM__', $pattern);
        $quoted = preg_quote($withPlaceholders, '#');
        $regex = str_replace('__DOMAIN_PARAM__', '[^.]+', $quoted);

        return (bool)preg_match('#^' . $regex . '$#i', $host);
    }

    // ─── Error handlers ─────────────────────────────────────────────────

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->notFoundHandler) {
            return call_user_func($this->notFoundHandler, $request);
        }

        return new Response(
            Stream::createFromString('404 Not Found'),
            404,
            ['Content-Type' => 'text/plain']
        );
    }

    /**
     * Handle 405 Method Not Allowed
     */
    private function handleMethodNotAllowed(ServerRequestInterface $request, array $allowedMethods): ResponseInterface
    {
        if ($this->methodNotAllowedHandler) {
            return call_user_func($this->methodNotAllowedHandler, $request, $allowedMethods);
        }

        return new Response(
            Stream::createFromString('405 Method Not Allowed'),
            405,
            [
                'Content-Type' => 'text/plain',
                'Allow' => implode(', ', $allowedMethods),
            ]
        );
    }

    /**
     * Set custom 404 handler
     */
    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Set custom 405 handler
     */
    public function setMethodNotAllowedHandler(callable $handler): void
    {
        $this->methodNotAllowedHandler = $handler;
    }

    // ─── URL generation ─────────────────────────────────────────────────

    /**
     * Get the URL generator
     */
    public function getUrlGenerator(): UrlGenerator
    {
        return $this->urlGenerator;
    }

    /**
     * Generate a URL for a named route
     */
    public function url(string $name, array $parameters = [], bool $absolute = false): string
    {
        return $this->urlGenerator->generate($name, $parameters, $absolute);
    }

    /**
     * Get route collection
     */
    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    // ─── Internal helpers ───────────────────────────────────────────────

    /**
     * Extract parameter names from a path
     */
    private function extractParamNames(string $path): array
    {
        preg_match_all('/\{([^}:?]+)/', $path, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Instantiate a middleware class, optionally with parameters.
     */
    private function instantiateMiddleware(string $class, array $params = []): object
    {
        if ($this->container !== null && $this->container->has($class)) {
            return $this->container->get($class);
        }

        if (!empty($params)) {
            return new $class(...$params);
        }

        return new $class();
    }

    /**
     * Apply parameters to a middleware instance if it supports them.
     */
    private function applyParameters(object $middleware, array $params): void
    {
        if (empty($params)) {
            return;
        }

        if (method_exists($middleware, 'setParameters')) {
            $middleware->setParameters($params);
        }
    }

    /**
     * Get the configured priority for a middleware name.
     */
    private function getMiddlewarePriority(string $name): int
    {
        // Strip parameters for lookup
        $baseName = str_contains($name, ':')
            ? explode(':', $name, 2)[0]
            : $name;

        return $this->middlewarePriority[$baseName]['priority'] ?? 0;
    }

    // ─── Group context methods ──────────────────────────────────────────

    public function getCurrentPrefix(): string { return $this->currentPrefix; }
    public function setCurrentPrefix(string $prefix): void { $this->currentPrefix = $prefix; }
    public function getCurrentMiddleware(): array { return $this->currentMiddleware; }
    public function setCurrentMiddleware(array $middleware): void { $this->currentMiddleware = $middleware; }
    public function getCurrentWhere(): array { return $this->currentWhere; }
    public function setCurrentWhere(array $where): void { $this->currentWhere = $where; }
    public function getCurrentDomain(): string { return $this->currentDomain; }
    public function setCurrentDomain(string $domain): void { $this->currentDomain = $domain; }
}