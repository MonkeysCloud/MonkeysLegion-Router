<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use InvalidArgumentException;

/**
 * MonkeysLegion Framework — Router Package
 *
 * URL generator for creating URLs from named routes.
 *
 * v2: property hook for baseUrl, model binding support.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class UrlGenerator
{
    /**
     * @var array<string, array{path: string, methods: list<string>, paramNames: list<string>}>
     */
    private array $namedRoutes = [];

    public string $baseUrl {
        get => $this->baseUrl;
        set(string $value) {
            $this->baseUrl = rtrim($value, '/');
        }
    }

    public function __construct()
    {
        $this->baseUrl = '';
    }

    /**
     * Register a named route for URL generation.
     *
     * @param string       $name
     * @param string       $path
     * @param list<string> $methods
     * @param list<string> $paramNames
     */
    public function register(string $name, string $path, array $methods, array $paramNames): void
    {
        $this->namedRoutes[$name] = [
            'path'       => $path,
            'methods'    => $methods,
            'paramNames' => $paramNames,
        ];
    }

    /**
     * Generate a URL for a named route.
     *
     * Parameters can be:
     *  - scalar values: ['id' => 42]
     *  - objects with an `id` property (route model binding): ['user' => $userEntity]
     *
     * @param string              $name       Route name.
     * @param array<string,mixed> $parameters Parameters to substitute.
     * @param bool                $absolute   Generate absolute URL.
     *
     * @return string Generated URL.
     * @throws InvalidArgumentException If route not found or missing parameters.
     */
    public function generate(string $name, array $parameters = [], bool $absolute = false): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException("Route '{$name}' not found.");
        }

        $route     = $this->namedRoutes[$name];
        $path      = $route['path'];
        $usedParams = [];

        // Resolve model objects into their identifiers
        $resolved = [];
        foreach ($parameters as $key => $value) {
            if (is_object($value)) {
                $resolved[$key] = match (true) {
                    property_exists($value, 'id')           => (string) $value->id,
                    method_exists($value, 'getRouteKey')    => (string) $value->getRouteKey(),
                    $value instanceof \BackedEnum           => $value->value,
                    default => throw new InvalidArgumentException(
                        "Cannot resolve object of type " . $value::class . " to a route parameter."
                    ),
                };
            } else {
                $resolved[$key] = (string) $value;
            }
        }

        // Replace path parameters
        $path = preg_replace_callback(
            '/\{([^}:?+]+)([^}]*)?\}/',
            function (array $m) use ($resolved, &$usedParams): string {
                $paramName  = $m[1];
                $isOptional = str_ends_with($m[0], '?}');

                if (!isset($resolved[$paramName])) {
                    if ($isOptional) {
                        return '';
                    }
                    throw new InvalidArgumentException("Missing required parameter: {$paramName}");
                }

                $usedParams[] = $paramName;
                return $resolved[$paramName];
            },
            $path,
        );

        // Remove trailing slash from optional parameter removal
        $path = rtrim($path, '/') ?: '/';

        // Remaining params → query string
        $remaining = array_diff_key($resolved, array_flip($usedParams));
        if ($remaining !== []) {
            $path .= '?' . http_build_query($remaining);
        }

        if ($absolute && $this->baseUrl !== '') {
            return $this->baseUrl . $path;
        }

        return $path;
    }

    public function has(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * @return list<string>
     */
    public function getRouteNames(): array
    {
        return array_keys($this->namedRoutes);
    }

    /**
     * @return array{path: string, methods: list<string>, paramNames: list<string>}|null
     */
    public function getRoute(string $name): ?array
    {
        return $this->namedRoutes[$name] ?? null;
    }
}