<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Compiles a RouteCollection into a method-indexed, trie-optimized
 * structure for O(log n) dispatching.
 *
 * Static segments are resolved via hash lookups; regex is only
 * applied to parametric paths — minimizing regex overhead.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RouteCompiler
{
    /**
     * Compile the route collection into an optimized lookup.
     */
    public function compile(RouteCollection $routes): CompiledRoutes
    {
        // Group routes by method
        $methodBuckets = [];
        foreach ($routes->all() as $route) {
            $methodBuckets[$route->method][] = $route;
        }

        // Separate static and parametric routes per method
        $staticMap    = [];
        $dynamicMap   = [];

        foreach ($methodBuckets as $method => $bucket) {
            foreach ($bucket as $route) {
                if ($this->isStatic($route->path)) {
                    // If a domain-constrained route shares a path with another,
                    // move both to dynamicMap so the dispatcher can evaluate
                    // domain constraints rather than overwriting.
                    if ($route->domain !== '' || isset($staticMap[$method][$route->path])) {
                        // Existing static entry → demote to dynamic
                        if (isset($staticMap[$method][$route->path])) {
                            $dynamicMap[$method][] = $staticMap[$method][$route->path];
                            unset($staticMap[$method][$route->path]);
                        }
                        $dynamicMap[$method][] = $route;
                    } else {
                        $staticMap[$method][$route->path] = $route;
                    }
                } else {
                    $dynamicMap[$method][] = $route;
                }
            }
        }

        return new CompiledRoutes($staticMap, $dynamicMap);
    }

    /**
     * Check if a path has zero dynamic segments.
     */
    private function isStatic(string $path): bool
    {
        return !str_contains($path, '{');
    }
}
