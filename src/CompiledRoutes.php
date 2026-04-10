<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Immutable compiled route structure for high-performance dispatching.
 *
 * Dispatch flow:
 *  1. Hash-lookup in staticMap[method][path] → O(1)
 *  2. Regex scan in dynamicMap[method] → O(k) where k = parametric routes for that method
 *  3. Across-method scan for 405 detection (only if both fail)
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CompiledRoutes
{
    /**
     * @param array<string, array<string, RouteDefinition>>  $staticMap  method → path → RouteDefinition
     * @param array<string, list<RouteDefinition>>           $dynamicMap method → list<RouteDefinition>
     */
    public function __construct(
        private readonly array $staticMap  = [],
        private readonly array $dynamicMap = [],
    ) {}

    /**
     * Match a request against the compiled routes.
     *
     * @return MatchResult|null Null if no match found.
     */
    public function match(string $method, string $path): ?MatchResult
    {
        // Step 1: O(1) static lookup
        if (isset($this->staticMap[$method][$path])) {
            return new MatchResult($this->staticMap[$method][$path]);
        }

        // Step 2: regex scan for dynamic routes
        foreach (($this->dynamicMap[$method] ?? []) as $route) {
            if (preg_match($route->regex, $path, $matches)) {
                $params = $this->extractParams($route, $matches);
                return new MatchResult($route, $params);
            }
        }

        return null;
    }

    /**
     * Get all HTTP methods that match a given path (for 405 detection).
     *
     * @return list<string>
     */
    public function getAllowedMethods(string $path): array
    {
        $allowed = [];

        // O(m) static lookup where m = number of HTTP methods
        foreach ($this->staticMap as $method => $paths) {
            if (isset($paths[$path])) {
                $allowed[] = $method;
            }
        }

        // Only scan dynamic routes if static didn't cover all methods
        if ($this->dynamicMap !== []) {
            foreach ($this->dynamicMap as $method => $routes) {
                // Skip if already found via static
                if (in_array($method, $allowed, true)) {
                    continue;
                }
                foreach ($routes as $route) {
                    if (preg_match($route->regex, $path)) {
                        $allowed[] = $method;
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Get all routes (for debugging / export).
     *
     * @return list<RouteDefinition>
     */
    public function allRoutes(): array
    {
        $result = [];

        foreach ($this->staticMap as $paths) {
            foreach ($paths as $route) {
                $result[] = $route;
            }
        }

        foreach ($this->dynamicMap as $routes) {
            foreach ($routes as $route) {
                $result[] = $route;
            }
        }

        return $result;
    }

    /**
     * Export the compiled structure for cache serialization.
     *
     * @return array<string, mixed>
     */
    public function export(): array
    {
        $staticExport = [];
        foreach ($this->staticMap as $method => $paths) {
            foreach ($paths as $path => $route) {
                $staticExport[$method][$path] = $route->toArray();
            }
        }

        $dynamicExport = [];
        foreach ($this->dynamicMap as $method => $routes) {
            foreach ($routes as $route) {
                $dynamicExport[$method][] = $route->toArray();
            }
        }

        return ['static' => $staticExport, 'dynamic' => $dynamicExport];
    }

    /**
     * Import from cached data.
     */
    public static function import(array $data): self
    {
        $staticMap  = [];
        $dynamicMap = [];

        foreach (($data['static'] ?? []) as $method => $paths) {
            foreach ($paths as $path => $arr) {
                $staticMap[$method][$path] = new RouteDefinition(...$arr);
            }
        }

        foreach (($data['dynamic'] ?? []) as $method => $routes) {
            foreach ($routes as $arr) {
                $dynamicMap[$method][] = new RouteDefinition(...$arr);
            }
        }

        return new self($staticMap, $dynamicMap);
    }

    /**
     * Extract named parameters from regex matches.
     *
     * @return array<string, string>
     */
    private function extractParams(RouteDefinition $route, array $matches): array
    {
        $params = [];
        foreach ($route->paramNames as $name) {
            if (isset($matches[$name]) && $matches[$name] !== '') {
                $params[$name] = $matches[$name];
            } elseif (isset($route->defaults[$name])) {
                $params[$name] = $route->defaults[$name];
            }
        }
        return $params;
    }
}
