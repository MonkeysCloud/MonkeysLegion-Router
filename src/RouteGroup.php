<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * Route group for organizing routes with shared attributes.
 */
class RouteGroup
{
    private string $prefix = '';
    private array $middleware = [];
    private array $where = [];
    private string $domain = '';
    private string $namespace = '';

    public function __construct(
        private Router $router
    ) {}

    /**
     * Set the prefix for routes in this group
     */
    public function prefix(string $prefix): self
    {
        $this->prefix = '/' . trim($prefix, '/');
        return $this;
    }

    /**
     * Set middleware for routes in this group
     */
    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    /**
     * Set constraints for routes in this group
     */
    public function where(array $where): self
    {
        $this->where = array_merge($this->where, $where);
        return $this;
    }

    /**
     * Set domain constraint for routes in this group
     */
    public function domain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * Set namespace for controller classes in this group
     */
    public function namespace(string $namespace): self
    {
        $this->namespace = rtrim($namespace, '\\');
        return $this;
    }

    /**
     * Define routes within this group
     */
    public function group(callable $callback): void
    {
        $previousPrefix = $this->router->getCurrentPrefix();
        $previousMiddleware = $this->router->getCurrentMiddleware();
        $previousWhere = $this->router->getCurrentWhere();
        $previousDomain = $this->router->getCurrentDomain();

        // Apply group attributes
        $this->router->setCurrentPrefix($previousPrefix . $this->prefix);
        $this->router->setCurrentMiddleware(array_merge($previousMiddleware, $this->middleware));
        $this->router->setCurrentWhere(array_merge($previousWhere, $this->where));

        if ($this->domain) {
            $this->router->setCurrentDomain($this->domain);
        }

        // Execute the group callback
        $callback($this->router);

        // Restore previous state
        $this->router->setCurrentPrefix($previousPrefix);
        $this->router->setCurrentMiddleware($previousMiddleware);
        $this->router->setCurrentWhere($previousWhere);
        $this->router->setCurrentDomain($previousDomain);
    }

    /**
     * Add a GET route to this group
     */
    public function get(string $path, callable $handler, ?string $name = null): void
    {
        $this->addRoute('GET', $path, $handler, $name);
    }

    /**
     * Add a POST route to this group
     */
    public function post(string $path, callable $handler, ?string $name = null): void
    {
        $this->addRoute('POST', $path, $handler, $name);
    }

    /**
     * Add a PUT route to this group
     */
    public function put(string $path, callable $handler, ?string $name = null): void
    {
        $this->addRoute('PUT', $path, $handler, $name);
    }

    /**
     * Add a DELETE route to this group
     */
    public function delete(string $path, callable $handler, ?string $name = null): void
    {
        $this->addRoute('DELETE', $path, $handler, $name);
    }

    /**
     * Add a PATCH route to this group
     */
    public function patch(string $path, callable $handler, ?string $name = null): void
    {
        $this->addRoute('PATCH', $path, $handler, $name);
    }

    /**
     * Add a route with the current group attributes
     */
    private function addRoute(string $method, string $path, callable $handler, ?string $name): void
    {
        $fullPath = $this->prefix . '/' . ltrim($path, '/');

        $this->router->add(
            $method,
            $fullPath,
            $handler,
            $name,
            $this->middleware,
            $this->where
        );
    }
}