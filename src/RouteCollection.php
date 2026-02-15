<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use MonkeysLegion\Router\Constraints\RouteConstraints;
use InvalidArgumentException;

/**
 * Enhanced route collection with support for constraints, middleware, and named routes.
 */
class RouteCollection
{
    /**
     * @var array<int, array{
     *     method: string,
     *     path: string,
     *     regex: string,
     *     paramNames: array<string>,
     *     optionalParams: array<string>,
     *     handler: callable,
     *     specificity: int,
     *     name: string,
     *     middleware: array<string>,
     *     constraints: array<string, string>,
     *     defaults: array<string, mixed>,
     *     domain: string,
     *     meta: array<string, mixed>
     * }>
     */
    private array $routes = [];

    /**
     * @var array<string, int> Map of route names to route indices
     */
    private array $namedRoutes = [];

    private bool $needsSorting = false;

    /**
     * Add a new route to the collection
     */
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
    ): void {
        // Normalize trailing slashes (keep root '/')
        $path = $path !== '/' ? rtrim($path, '/') : $path;

        $paramNames = [];
        $optionalParams = [];
        $constraints = $constraints ?: [];

        // Extract inline constraints from path: {id:\d+} or {id:int}
        $path = preg_replace_callback(
            '/\{([^}:?]+):([^}?]+)(\?)?\}/',
            function ($matches) use (&$constraints) {
                $paramName = $matches[1];
                $constraint = $matches[2];
                $isOptional = isset($matches[3]);

                $constraints[$paramName] = $constraint;

                return '{' . $paramName . ($isOptional ? '?' : '') . '}';
            },
            $path
        );

        // Convert path to regex with parameter capture groups
        $regex = preg_replace_callback(
            '/(\/?)(\{([^}?]+)(\?)?\})/',
            function (array $matches) use (&$paramNames, &$optionalParams, $constraints) {
                $leadingSlash = $matches[1];
                $paramName = $matches[3];
                $isOptional = isset($matches[4]);

                $paramNames[] = $paramName;

                if ($isOptional) {
                    $optionalParams[] = $paramName;
                }

                // Get constraint pattern
                $pattern = '[^/]+';
                if (isset($constraints[$paramName])) {
                    $constraint = RouteConstraints::get($constraints[$paramName]);
                    $pattern = $constraint->getPattern();
                }

                $capture = '(?P<' . $paramName . '>' . $pattern . ')';

                // For optional params, include the leading slash in the optional group
                if ($isOptional) {
                    return '(?:' . $leadingSlash . $capture . ')?';
                }

                return $leadingSlash . $capture;
            },
            $path
        );

        // Anchor to beginning/end
        $regex = '#^' . $regex . '$#';

        // Calculate specificity score
        $specificity = $this->calculateSpecificity($path, $paramNames, $optionalParams);

        $routeIndex = count($this->routes);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => $regex,
            'paramNames' => $paramNames,
            'optionalParams' => $optionalParams,
            'handler' => $handler,
            'specificity' => $specificity,
            'name' => $name,
            'middleware' => $middleware,
            'constraints' => $constraints,
            'defaults' => $defaults,
            'domain' => $domain,
            'meta' => $meta,
        ];

        // Register named route
        if ($name !== '') {
            if (isset($this->namedRoutes[$name])) {
                throw new InvalidArgumentException("Route name '{$name}' is already registered.");
            }
            $this->namedRoutes[$name] = $routeIndex;
        }

        $this->needsSorting = true;
    }

    /**
     * Calculate route specificity score (higher = more specific)
     */
    private function calculateSpecificity(string $path, array $paramNames, array $optionalParams): int
    {
        $score = 0;

        // Split path into segments
        $segments = array_filter(explode('/', trim($path, '/')));

        foreach ($segments as $segment) {
            // Static segments are most specific
            if (!str_contains($segment, '{')) {
                $score += 10000;
            }
            // Required parameters are moderately specific
            elseif (!str_ends_with($segment, '?}')) {
                $score += 100;
            }
            // Optional parameters are least specific
            else {
                $score += 1;
            }
        }

        // Longer paths are generally more specific
        $score += count($segments) * 50;

        // Paths with constraints are more specific
        $score += count($paramNames) * 10;

        // Penalize optional parameters
        $score -= count($optionalParams) * 50;

        return $score;
    }

    /**
     * Get all registered routes, sorted by specificity
     */
    public function all(): array
    {
        if ($this->needsSorting) {
            $this->sortRoutes();
            $this->needsSorting = false;
        }

        return $this->routes;
    }

    /**
     * Get a route by name
     */
    public function getByName(string $name): ?array
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }

        return $this->routes[$this->namedRoutes[$name]] ?? null;
    }

    /**
     * Get all named routes
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    /**
     * Check if a named route exists
     */
    public function hasName(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Sort routes by method and specificity
     */
    private function sortRoutes(): void
    {
        usort($this->routes, function ($a, $b) {
            // First sort by method
            if ($a['method'] !== $b['method']) {
                return strcmp($a['method'], $b['method']);
            }

            // Then by specificity (higher first)
            return $b['specificity'] <=> $a['specificity'];
        });

        // Rebuild named route index after sorting
        $this->namedRoutes = [];
        foreach ($this->routes as $index => $route) {
            if ($route['name'] !== '') {
                $this->namedRoutes[$route['name']] = $index;
            }
        }
    }

    /**
     * Get routes matching a specific method
     */
    public function getByMethod(string $method): array
    {
        $method = strtoupper($method);
        return array_filter($this->all(), fn($route) => $route['method'] === $method);
    }

    /**
     * Get all HTTP methods registered
     */
    public function getMethods(): array
    {
        return array_unique(array_map(fn($route) => $route['method'], $this->routes));
    }

    /**
     * Export routes for caching
     */
    public function export(): array
    {
        return [
            'routes' => $this->routes,
            'namedRoutes' => $this->namedRoutes,
        ];
    }

    /**
     * Import routes from cache
     */
    public function import(array $data): void
    {
        $this->routes = $data['routes'] ?? [];
        $this->namedRoutes = $data['namedRoutes'] ?? [];
        $this->needsSorting = false;
    }

    /**
     * Clear all routes
     */
    public function clear(): void
    {
        $this->routes = [];
        $this->namedRoutes = [];
        $this->needsSorting = false;
    }

    /**
     * Get route count
     */
    public function count(): int
    {
        return count($this->routes);
    }
}