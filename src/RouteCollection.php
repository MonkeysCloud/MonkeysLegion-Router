<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use MonkeysLegion\Router\Constraints\RouteConstraints;
use InvalidArgumentException;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Stores route definitions and compiles path templates into regex.
 *
 * v2 improvements:
 *  • Accepts `array|callable|\Closure` handlers (not just callable)
 *  • Stores routes as `RouteDefinition` value objects
 *  • `freeze()` makes the collection immutable after compilation
 *  • Method-indexed lookups for O(1) method filtering
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RouteCollection
{
    /** @var list<RouteDefinition> */
    private array $routes = [];

    /** @var array<string, int> Map of route names → indices. */
    private array $namedRoutes = [];

    /** @var array<string, list<int>> Method → route indices. */
    private array $methodIndex = [];

    private bool $needsSorting = false;
    private bool $frozen = false;

    // ── Registration ───────────────────────────────────────────

    /**
     * Add a new route to the collection.
     *
     * @param string                       $method      HTTP method.
     * @param string                       $path        URI template.
     * @param array|callable|\Closure      $handler     Route handler.
     * @param string                       $name        Route name.
     * @param list<string>                 $middleware  Middleware list.
     * @param array<string, string>        $constraints Parameter constraints.
     * @param array<string, mixed>         $defaults    Default parameter values.
     * @param string                       $domain      Domain constraint.
     * @param array<string, mixed>         $meta        Additional metadata.
     */
    public function add(
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
        if ($this->frozen) {
            throw new \LogicException('Cannot add routes to a frozen collection.');
        }

        $method = strtoupper($method);
        $path   = $path !== '/' ? rtrim($path, '/') : $path;

        // Reject paths containing null bytes or control characters
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $path) === 1) {
            throw new InvalidArgumentException(
                'Route path must not contain null bytes or control characters.'
            );
        }

        // Normalize handler: callable must become Closure or array for property storage
        if (!is_array($handler) && !$handler instanceof \Closure) {
            $handler = \Closure::fromCallable($handler);
        }

        // Auto-populate meta['handler'] for array handlers (OpenAPI)
        if (is_array($handler) && count($handler) >= 2 && !isset($meta['handler'])) {
            $meta['handler'] = $handler;
        }

        // Extract inline constraints and build regex
        [$regex, $paramNames, $optionalParams, $constraints] = $this->compilePath($path, $constraints);

        $specificity = $this->calculateSpecificity($path, $paramNames, $optionalParams);

        $index = count($this->routes);

        $this->routes[] = new RouteDefinition(
            method:         $method,
            path:           $path,
            regex:          $regex,
            handler:        $handler,
            paramNames:     $paramNames,
            optionalParams: $optionalParams,
            name:           $name,
            middleware:     $middleware,
            constraints:   $constraints,
            defaults:      $defaults,
            domain:        $domain,
            meta:          $meta,
            specificity:   $specificity,
        );

        // Method index
        $this->methodIndex[$method][] = $index;

        // Named route
        if ($name !== '') {
            if (isset($this->namedRoutes[$name])) {
                throw new InvalidArgumentException("Route name '{$name}' is already registered.");
            }
            $this->namedRoutes[$name] = $index;
        }

        $this->needsSorting = true;
    }

    // ── Accessors ──────────────────────────────────────────────

    /**
     * Get all routes sorted by specificity.
     *
     * @return list<RouteDefinition>
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
     * Get routes for a specific HTTP method.
     *
     * @return list<RouteDefinition>
     */
    public function getByMethod(string $method): array
    {
        $method  = strtoupper($method);
        $indices = $this->methodIndex[$method] ?? [];
        $routes  = [];
        foreach ($indices as $i) {
            $routes[] = $this->routes[$i];
        }
        return $routes;
    }

    /**
     * Get a route by name.
     */
    public function getByName(string $name): ?RouteDefinition
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }
        return $this->routes[$this->namedRoutes[$name]] ?? null;
    }

    public function hasName(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Get all HTTP methods that have at least one route matching a path.
     *
     * @return list<string>
     */
    public function getMethodsForPath(string $path): array
    {
        $methods = [];
        foreach ($this->all() as $route) {
            if (preg_match($route->regex, $path)) {
                $methods[] = $route->method;
            }
        }
        return array_values(array_unique($methods));
    }

    /**
     * Get all registered HTTP methods.
     *
     * @return list<string>
     */
    public function getMethods(): array
    {
        return array_keys($this->methodIndex);
    }

    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Freeze the collection — no more routes can be added.
     */
    public function freeze(): void
    {
        if ($this->needsSorting) {
            $this->sortRoutes();
            $this->needsSorting = false;
        }
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    // ── Cache ──────────────────────────────────────────────────

    /**
     * Export for cache serialization.
     *
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return [
            'routes'      => array_map(fn(RouteDefinition $r) => $r->toArray(), $this->routes),
            'namedRoutes' => $this->namedRoutes,
        ];
    }

    /**
     * Import from cached data.
     */
    public function import(array $data): void
    {
        $this->routes = [];
        $this->namedRoutes = $data['namedRoutes'] ?? [];
        $this->methodIndex = [];

        foreach (($data['routes'] ?? []) as $i => $arr) {
            $def = new RouteDefinition(...$arr);
            $this->routes[] = $def;
            $this->methodIndex[$def->method][] = $i;
        }

        $this->needsSorting = false;
    }

    public function clear(): void
    {
        $this->routes      = [];
        $this->namedRoutes = [];
        $this->methodIndex = [];
        $this->needsSorting = false;
        $this->frozen       = false;
    }

    // ── Internals ──────────────────────────────────────────────

    /**
     * Compile a path template into a regex pattern.
     *
     * @return array{0: string, 1: list<string>, 2: list<string>, 3: array<string, string>}
     */
    private function compilePath(string $path, array $constraints): array
    {
        $paramNames     = [];
        $optionalParams = [];
        $greedyParams   = [];

        // Extract inline constraints: {id:\d+}, {path+}, {slug?}
        $path = preg_replace_callback(
            '/\{([^}:?+]+)([+])?:?([^}?]*)(\?)?}/',
            function (array $m) use (&$constraints, &$greedyParams): string {
                $name     = $m[1];
                $greedy   = !empty($m[2]);
                $inline   = $m[3] ?? '';
                $optional = !empty($m[4]);

                if ($greedy) {
                    $greedyParams[] = $name;
                }
                if ($inline !== '') {
                    $constraints[$name] = $inline;
                }

                return '{' . $name . ($greedy ? '+' : '') . ($optional ? '?' : '') . '}';
            },
            $path,
        );

        // Build regex
        $regex = preg_replace_callback(
            '/(\/?)(\{([^}?+]+)([+])?(\?)?\})/',
            function (array $m) use (&$paramNames, &$optionalParams, $constraints, $greedyParams): string {
                $slash    = $m[1];
                $name     = $m[3];
                $greedy   = !empty($m[4]);
                $optional = !empty($m[5]);

                $paramNames[] = $name;
                if ($optional) {
                    $optionalParams[] = $name;
                }

                if ($greedy || in_array($name, $greedyParams, true)) {
                    $pattern = '.+';
                } elseif (isset($constraints[$name])) {
                    $pattern = RouteConstraints::get($constraints[$name])->getPattern();
                } else {
                    $pattern = '[^/]+';
                }

                $capture = '(?P<' . $name . '>' . $pattern . ')';

                return $optional
                    ? '(?:' . $slash . $capture . ')?'
                    : $slash . $capture;
            },
            $path,
        );

        $regex = '#^' . $regex . '$#';

        return [$regex, $paramNames, $optionalParams, $constraints];
    }

    private function calculateSpecificity(string $path, array $paramNames, array $optionalParams): int
    {
        $score    = 0;
        $segments = array_filter(explode('/', trim($path, '/')));

        foreach ($segments as $seg) {
            if (!str_contains($seg, '{')) {
                $score += 10000; // static
            } elseif (!str_ends_with($seg, '?}')) {
                $score += 100; // required param
            } else {
                $score += 1; // optional param
            }
        }

        $score += count($segments) * 50;
        $score += count($paramNames) * 10;
        $score -= count($optionalParams) * 50;

        return $score;
    }

    private function sortRoutes(): void
    {
        usort($this->routes, static function (RouteDefinition $a, RouteDefinition $b): int {
            return $a->method !== $b->method
                ? strcmp($a->method, $b->method)
                : $b->specificity <=> $a->specificity;
        });

        // Rebuild indices
        $this->namedRoutes = [];
        $this->methodIndex = [];
        foreach ($this->routes as $i => $route) {
            if ($route->name !== '') {
                $this->namedRoutes[$route->name] = $i;
            }
            $this->methodIndex[$route->method][] = $i;
        }
    }
}