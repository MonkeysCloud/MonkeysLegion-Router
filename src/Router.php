<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Router\Middleware\CallableHandlerAdapter;
use MonkeysLegion\Router\Middleware\MiddlewarePipeline;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

use InvalidArgumentException;

/**
 * MonkeysLegion Framework — Router Package
 *
 * High-performance HTTP router with compiled trie matching, PSR-15
 * middleware pipeline, and attribute-driven configuration.
 *
 * v2 architecture:
 *  • Compiled route matching via static hash + regex scan.
 *  • Method-indexed O(1) filtering.
 *  • Cursor-based middleware pipeline (zero anonymous classes).
 *  • Immutable group context via GroupContext value objects.
 *  • Route model binding via DI container.
 *  • HEAD auto-delegation, OPTIONS auto-response, domain constraints.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class Router
{
    private readonly UrlGenerator $urlGenerator;
    private CompiledRoutes $compiled;
    private bool $isCompiled = false;

    /** @var array<string, MiddlewareInterface> Named middleware registry. */
    private array $middleware = [];

    /** @var array<string, int> Middleware priority map. */
    private array $middlewarePriority = [];

    /** @var array<string, list<string>> Middleware groups. */
    private array $middlewareGroups = [];

    /** @var list<string> Global middleware. */
    private array $globalMiddleware = [];

    private GroupContext $groupContext;

    /** @var callable|null */
    private $notFoundHandler = null;

    /** @var callable|null */
    private $methodNotAllowedHandler = null;

    /** @var callable|null */
    private $fallbackHandler = null;

    private ?ContainerInterface $container = null;
    private ?LoggerInterface $logger = null;

    public TrailingSlashStrategy $trailingSlashStrategy {
        get => $this->trailingSlashStrategy;
        set => $this->trailingSlashStrategy = $value;
    }

    public function __construct(
        private readonly RouteCollection $routes,
    ) {
        $this->urlGenerator           = new UrlGenerator();
        $this->groupContext           = new GroupContext();
        $this->trailingSlashStrategy  = TrailingSlashStrategy::STRIP;
    }

    // ── Configuration ──────────────────────────────────────────

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function fallback(callable $handler): void
    {
        $this->fallbackHandler = $handler;
    }

    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function setMethodNotAllowedHandler(callable $handler): void
    {
        $this->methodNotAllowedHandler = $handler;
    }

    // ── Route registration ─────────────────────────────────────

    /**
     * Add a route definition.
     *
     * @param string                       $method
     * @param string                       $path
     * @param array|callable|\Closure      $handler
     * @param string|null                  $name
     * @param list<string>                 $middleware
     * @param array<string, string>        $constraints
     * @param array<string, mixed>         $defaults
     * @param string                       $domain
     * @param array<string, mixed>         $meta
     */
    public function add(
        string               $method,
        string               $path,
        array|callable|\Closure $handler,
        ?string              $name        = null,
        array                $middleware  = [],
        array                $constraints = [],
        array                $defaults    = [],
        string               $domain      = '',
        array                $meta        = [],
    ): void {
        // Apply current group context
        $path       = $this->groupContext->applyPath($path);
        $middleware  = [...$this->groupContext->middleware, ...$middleware];
        $constraints = [...$this->groupContext->constraints, ...$constraints];
        $domain     = $domain ?: $this->groupContext->domain;

        $this->routes->add(
            $method,
            $path,
            $handler,
            $name ?? '',
            $middleware,
            $constraints,
            $defaults,
            $domain,
            $meta,
        );

        // Register with URL generator
        if ($name !== null && $name !== '') {
            $this->urlGenerator->register(
                $name,
                $path,
                [$method],
                $this->extractParamNames($path),
            );
        }

        $this->isCompiled = false;
    }

    /**
     * Add a route without applying group context (used by ControllerScanner).
     *
     * @internal
     */
    public function addRaw(
        string               $method,
        string               $path,
        array|callable|\Closure $handler,
        string               $name        = '',
        array                $middleware  = [],
        array                $constraints = [],
        array                $defaults    = [],
        string               $domain      = '',
        array                $meta        = [],
    ): void {
        $this->routes->add(
            $method,
            $path,
            $handler,
            $name,
            $middleware,
            $constraints,
            $defaults,
            $domain,
            $meta,
        );

        if ($name !== '') {
            $this->urlGenerator->register(
                $name,
                $path,
                [$method],
                $this->extractParamNames($path),
            );
        }

        $this->isCompiled = false;
    }

    // ── Shorthand methods ──────────────────────────────────────

    public function get(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->add('GET', $path, $handler, $name);
    }

    public function post(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->add('POST', $path, $handler, $name);
    }

    public function put(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->add('PUT', $path, $handler, $name);
    }

    public function delete(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->add('DELETE', $path, $handler, $name);
    }

    public function patch(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->add('PATCH', $path, $handler, $name);
    }

    public function options(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->add('OPTIONS', $path, $handler, $name);
    }

    public function any(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        foreach (['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $method) {
            $this->add($method, $path, $handler, $name !== null ? $name . '.' . strtolower($method) : null);
        }
    }

    /**
     * @param list<string> $methods
     */
    public function match(array $methods, string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        foreach ($methods as $method) {
            $this->add($method, $path, $handler, $name);
        }
    }

    public function redirect(string $from, string $to, int $status = 302): void
    {
        $this->get($from, fn() => new Response(
            Stream::createFromString(''),
            $status,
            ['Location' => $to],
        ));
    }

    public function resource(string $prefix, object|string $controller): RouteRegistrar
    {
        return new RouteRegistrar($this, $prefix, $controller);
    }

    public function apiResource(string $prefix, object|string $controller): RouteRegistrar
    {
        return RouteRegistrar::api($this, $prefix, $controller);
    }

    public function group(?callable $callback = null): RouteGroup
    {
        $group = new RouteGroup($this, $this->groupContext);

        if ($callback !== null) {
            $group->group($callback);
        }

        return $group;
    }

    // ── Middleware registration ─────────────────────────────────

    public function registerMiddleware(string $name, MiddlewareInterface|string $middleware, int $priority = 0): void
    {
        if (is_string($middleware)) {
            if (!class_exists($middleware)) {
                throw new InvalidArgumentException("Middleware class '{$middleware}' does not exist.");
            }
            $middleware = $this->instantiateMiddleware($middleware);
        }

        $this->middleware[$name]         = $middleware;
        $this->middlewarePriority[$name] = $priority;
    }

    /**
     * @param list<string> $middleware
     */
    public function registerMiddlewareGroup(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    public function addGlobalMiddleware(string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    // ── Compilation ────────────────────────────────────────────

    /**
     * Compile routes for dispatch. Called automatically on first dispatch.
     */
    public function compile(): void
    {
        $compiler       = new RouteCompiler();
        $this->compiled = $compiler->compile($this->routes);
        $this->isCompiled = true;
    }

    public function getCompiledRoutes(): CompiledRoutes
    {
        if (!$this->isCompiled) {
            $this->compile();
        }
        return $this->compiled;
    }

    /**
     * Load pre-compiled routes (from cache).
     */
    public function loadCompiled(CompiledRoutes $compiled): void
    {
        $this->compiled   = $compiled;
        $this->isCompiled = true;
    }

    // ── Dispatch ───────────────────────────────────────────────

    /**
     * Dispatch a PSR-7 request to the matching route.
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isCompiled) {
            $this->compile();
        }

        $method  = strtoupper($request->getMethod());
        $rawPath = $request->getUri()->getPath();

        // Trailing-slash strategy
        $path = $this->normalizeTrailingSlash($rawPath, $method);
        if ($path instanceof ResponseInterface) {
            return $path;
        }

        $host   = $request->getUri()->getHost();
        $isHead = ($method === 'HEAD');

        // HEAD → try HEAD first, then fallback to GET
        $methodsToTry = $isHead ? ['HEAD', 'GET'] : [$method];

        foreach ($methodsToTry as $tryMethod) {
            $result = $this->compiled->match($tryMethod, $path);

            if ($result === null) {
                continue;
            }

            // Domain constraint
            if ($result->route->domain !== '' && !$this->matchesDomain($result->route->domain, $host)) {
                continue;
            }

            // Attach parameters to request
            foreach ($result->parameters as $name => $value) {
                $request = $request->withAttribute($name, $value);
            }

            // Apply default values for missing optional params
            foreach ($result->route->defaults as $name => $default) {
                if (!isset($result->parameters[$name])) {
                    $request = $request->withAttribute($name, $default);
                }
            }

            // Attach throttle info if present
            if (isset($result->route->meta['throttle'])) {
                $request = $request->withAttribute('_route_throttle', $result->route->meta['throttle']);
            }

            // Build middleware pipeline
            $middlewareList = $this->expandMiddleware([
                ...$this->globalMiddleware,
                ...$result->middleware(),
            ]);

            $pipeline = new MiddlewarePipeline();
            foreach ($middlewareList as $mwName) {
                $mwInstance = $this->resolveMiddleware($mwName);
                if ($mwInstance !== null) {
                    $pipeline->pipe($mwInstance, $this->middlewarePriority[$this->baseName($mwName)] ?? 0);
                }
            }

            // Final handler
            $finalHandler = new CallableHandlerAdapter(function (ServerRequestInterface $req) use ($result): ResponseInterface {
                $handler = $result->handler();
                $params  = [];

                foreach ($result->route->paramNames as $name) {
                    $val = $req->getAttribute($name);
                    if ($val !== null) {
                        $params[] = $val;
                    } elseif (in_array($name, $result->route->optionalParams, true)) {
                        continue;
                    } else {
                        $params[] = null;
                    }
                }

                return call_user_func_array($handler, [$req, ...$params]);
            });

            $response = $pipeline->process($request, $finalHandler);

            // HEAD → strip body
            if ($isHead && $tryMethod === 'GET') {
                $response = $response->withBody(Stream::createFromString(''));
            }

            return $response;
        }

        // OPTIONS auto-response
        $allowedMethods = $this->compiled->getAllowedMethods($path);
        if ($method === 'OPTIONS' && $allowedMethods !== []) {
            $allowedMethods[] = 'OPTIONS';
            if (in_array('GET', $allowedMethods, true) && !in_array('HEAD', $allowedMethods, true)) {
                $allowedMethods[] = 'HEAD';
            }
            $unique = array_unique($allowedMethods);
            sort($unique);

            return new Response(
                Stream::createFromString(''),
                200,
                ['Allow' => implode(', ', $unique), 'Content-Length' => '0'],
            );
        }

        // 405 Method Not Allowed
        if ($allowedMethods !== []) {
            if (in_array('GET', $allowedMethods, true) && !in_array('HEAD', $allowedMethods, true)) {
                $allowedMethods[] = 'HEAD';
            }
            return $this->handleMethodNotAllowed($request, array_unique($allowedMethods));
        }

        // Fallback handler
        if ($this->fallbackHandler !== null) {
            return ($this->fallbackHandler)($request);
        }

        return $this->handleNotFound($request);
    }

    // ── URL generation ─────────────────────────────────────────

    public function getUrlGenerator(): UrlGenerator
    {
        return $this->urlGenerator;
    }

    public function url(string $name, array $parameters = [], bool $absolute = false): string
    {
        return $this->urlGenerator->generate($name, $parameters, $absolute);
    }

    // ── Accessors ──────────────────────────────────────────────

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    public function getGroupContext(): GroupContext
    {
        return $this->groupContext;
    }

    public function setGroupContext(GroupContext $context): void
    {
        $this->groupContext = $context;
    }

    // ── Error handlers ─────────────────────────────────────────

    private function handleNotFound(ServerRequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path   = $request->getUri()->getPath();

        $this->logger?->notice('Route not found', [
            'method' => $method,
            'path'   => $path,
            'host'   => $request->getUri()->getHost(),
        ]);

        if ($this->notFoundHandler !== null) {
            return ($this->notFoundHandler)($request);
        }

        $safePath = htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new Response(
            Stream::createFromString("404 Not Found\n\nThe requested URL \"{$safePath}\" was not found on this server."),
            404,
            ['Content-Type' => 'text/plain'],
        );
    }

    private function handleMethodNotAllowed(ServerRequestInterface $request, array $allowedMethods): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path   = $request->getUri()->getPath();
        $allow  = implode(', ', $allowedMethods);

        $this->logger?->warning('Method not allowed', [
            'method'          => $method,
            'path'            => $path,
            'allowed_methods' => $allowedMethods,
            'host'            => $request->getUri()->getHost(),
        ]);

        if ($this->methodNotAllowedHandler !== null) {
            return ($this->methodNotAllowedHandler)($request, $allowedMethods);
        }

        $safePath   = htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMethod = htmlspecialchars($method, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new Response(
            Stream::createFromString(
                "405 Method Not Allowed\n\nThe {$safeMethod} method is not allowed for \"{$safePath}\".\nAllowed methods: {$allow}"
            ),
            405,
            ['Content-Type' => 'text/plain', 'Allow' => $allow],
        );
    }

    // ── Internals ──────────────────────────────────────────────

    private function normalizeTrailingSlash(string $path, string $method): string|ResponseInterface
    {
        if ($path === '/') {
            return $path;
        }

        return match ($this->trailingSlashStrategy) {
            TrailingSlashStrategy::STRIP => rtrim($path, '/'),
            TrailingSlashStrategy::REDIRECT_301 => str_ends_with($path, '/')
                ? new Response(Stream::createFromString(''), 301, ['Location' => rtrim($path, '/')])
                : $path,
            TrailingSlashStrategy::ALLOW_BOTH => $path,
        };
    }

    private function matchesDomain(string $pattern, string $host): bool
    {
        if ($pattern === $host) {
            return true;
        }

        $withPlaceholders = preg_replace('/\{[^}]+\}/', '__DOMAIN_PARAM__', $pattern);
        $quoted = preg_quote($withPlaceholders, '#');
        $regex = str_replace('__DOMAIN_PARAM__', '[^.]+', $quoted);

        return (bool) preg_match('#^' . $regex . '$#i', $host);
    }

    /**
     * @return list<string>
     */
    private function extractParamNames(string $path): array
    {
        preg_match_all('/\{([^}:?+]+)/', $path, $matches);
        return $matches[1] ?? [];
    }

    private function resolveMiddleware(string $name): ?MiddlewareInterface
    {
        $params = [];
        if (str_contains($name, ':')) {
            [$name, $paramStr] = explode(':', $name, 2);
            $params = explode(',', $paramStr);
        }

        // Named middleware
        if (isset($this->middleware[$name])) {
            $mw = clone $this->middleware[$name];
            $this->applyParameters($mw, $params);
            return $mw;
        }

        // DI container
        if ($this->container !== null && $this->container->has($name)) {
            $instance = $this->container->get($name);
            if ($instance instanceof MiddlewareInterface) {
                $this->applyParameters($instance, $params);
                return $instance;
            }
        }

        // Class name
        if (class_exists($name)) {
            $instance = $this->instantiateMiddleware($name, $params);
            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function expandMiddleware(array $middlewareList): array
    {
        $expanded = [];
        foreach ($middlewareList as $mw) {
            $baseName = $this->baseName($mw);
            if (isset($this->middlewareGroups[$baseName])) {
                $expanded = [...$expanded, ...$this->expandMiddleware($this->middlewareGroups[$baseName])];
            } else {
                $expanded[] = $mw;
            }
        }
        return $expanded;
    }

    private function baseName(string $name): string
    {
        return str_contains($name, ':') ? explode(':', $name, 2)[0] : $name;
    }

    private function instantiateMiddleware(string $class, array $params = []): MiddlewareInterface
    {
        if ($this->container !== null && $this->container->has($class)) {
            $instance = $this->container->get($class);
            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }
        }

        if ($params !== []) {
            return new $class(...$params);
        }

        return new $class();
    }

    private function applyParameters(object $middleware, array $params): void
    {
        if ($params === []) {
            return;
        }

        if (method_exists($middleware, 'setParameters')) {
            $middleware->setParameters($params);
        }
    }
}