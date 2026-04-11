<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Fluent route group builder using immutable GroupContext.
 *
 * Usage:
 *   $router->group()
 *       ->prefix('/api')
 *       ->middleware('auth')
 *       ->group(function (Router $r) {
 *           $r->get('/users', $handler, 'api.users.index');
 *       });
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RouteGroup
{
    private GroupContext $context;

    public function __construct(
        private readonly Router $router,
        ?GroupContext $parentContext = null,
    ) {
        $this->context = $parentContext ?? new GroupContext();
    }

    public function prefix(string $prefix): self
    {
        $this->context = $this->context->withPrefix($prefix);
        return $this;
    }

    /**
     * @param string|list<string> $middleware
     */
    public function middleware(string|array $middleware): self
    {
        $this->context = $this->context->withMiddleware((array) $middleware);
        return $this;
    }

    /**
     * @param array<string, string> $where
     */
    public function where(array $where): self
    {
        $this->context = $this->context->withConstraints($where);
        return $this;
    }

    public function domain(string $domain): self
    {
        $this->context = $this->context->withDomain($domain);
        return $this;
    }

    /**
     * Define routes within this group.
     */
    public function group(callable $callback): void
    {
        // Save parent context
        $previous = $this->router->getGroupContext();

        // Apply this group's context
        $this->router->setGroupContext($this->context);

        // Execute callback
        $callback($this->router);

        // Restore parent context
        $this->router->setGroupContext($previous);
    }

    // ── Shorthand route methods ────────────────────────────────

    public function get(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->addRoute('GET', $path, $handler, $name);
    }

    public function post(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->addRoute('POST', $path, $handler, $name);
    }

    public function put(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->addRoute('PUT', $path, $handler, $name);
    }

    public function delete(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->addRoute('DELETE', $path, $handler, $name);
    }

    public function patch(string $path, array|callable|\Closure $handler, ?string $name = null): void
    {
        $this->addRoute('PATCH', $path, $handler, $name);
    }

    private function addRoute(string $method, string $path, array|callable|\Closure $handler, ?string $name): void
    {
        $fullPath = $this->context->applyPath($path);

        $this->router->addRaw(
            $method,
            $fullPath,
            $handler,
            $name ?? '',
            $this->context->middleware,
            $this->context->constraints,
        );
    }
}