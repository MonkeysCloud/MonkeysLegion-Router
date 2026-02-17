<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use InvalidArgumentException;

/**
 * URL generator for creating URLs from named routes.
 */
class UrlGenerator
{
    /**
     * @var array<string, array{path: string, methods: array<string>, paramNames: array<string>}>
     */
    private array $namedRoutes = [];

    private string $baseUrl = '';

    /**
     * Register a named route
     */
    public function register(string $name, string $path, array $methods, array $paramNames): void
    {
        $this->namedRoutes[$name] = [
            'path' => $path,
            'methods' => $methods,
            'paramNames' => $paramNames,
        ];
    }

    /**
     * Set the base URL for absolute URL generation
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Get the current base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Generate a URL for a named route
     *
     * @param string $name       Route name
     * @param array  $parameters Parameters to substitute
     * @param bool   $absolute   Generate absolute URL (includes base URL)
     * @return string Generated URL
     * @throws InvalidArgumentException If route not found or missing parameters
     */
    public function generate(string $name, array $parameters = [], bool $absolute = false): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException("Route '{$name}' not found.");
        }

        $route = $this->namedRoutes[$name];
        $path = $route['path'];
        $usedParams = [];

        // Replace path parameters
        $path = preg_replace_callback(
            '/\{([^}:?]+)([^}]*)?\}/',
            function ($matches) use ($parameters, &$usedParams) {
                $paramName = $matches[1];
                $isOptional = str_ends_with($matches[0], '?}');

                if (!isset($parameters[$paramName])) {
                    if ($isOptional) {
                        return '';
                    }
                    throw new InvalidArgumentException("Missing required parameter: {$paramName}");
                }

                $usedParams[] = $paramName;
                return $parameters[$paramName];
            },
            $path
        );

        // Remove trailing slash if path ended with optional parameter
        $path = rtrim($path, '/') ?: '/';

        // Add remaining parameters as query string
        $remaining = array_diff_key($parameters, array_flip($usedParams));
        if (!empty($remaining)) {
            $path .= '?' . http_build_query($remaining);
        }

        if ($absolute && $this->baseUrl) {
            return $this->baseUrl . $path;
        }

        return $path;
    }

    /**
     * Check if a named route exists
     */
    public function has(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Get all registered route names
     */
    public function getRouteNames(): array
    {
        return array_keys($this->namedRoutes);
    }

    /**
     * Get route information by name
     */
    public function getRoute(string $name): ?array
    {
        return $this->namedRoutes[$name] ?? null;
    }
}