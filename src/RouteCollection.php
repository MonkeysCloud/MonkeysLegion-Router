<?php

declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * Holds all registered routes and their metadata.
 *
 * Each route is stored as an associative array with keys:
 *  - method     : HTTP verb (GET, POST, etc.)
 *  - path       : original path template (e.g. "/users/{id}")
 *  - regex      : compiled PCRE regex to match incoming URIs
 *  - paramNames : list of placeholder names in the order they appear
 *  - handler    : callable to invoke when the route matches
 */
class RouteCollection
{
    /**
     * @var array<int, array{
     *     method: string,
     *     path: string,
     *     regex: string,
     *     paramNames: string[],
     *     handler: callable
     * }>
     */
    private array $routes = [];

    /**
     * Register a new route.
     *
     * @param string   $method  HTTP method (e.g. "GET", "POST")
     * @param string   $path    URI template, supports placeholders {name}
     * @param callable $handler A function or [controller, method] callback
     */
    public function add(string $method, string $path, callable $handler): void
    {
        $paramNames = [];

        // Convert "/users/{id}" into a regex with named capture groups
        $regex = preg_replace_callback(
            '/\{([^}]+)\}/',
            function (array $matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                // capture one or more chars that are not "/"
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $path
        );

        // Anchor to beginning/end
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'regex'      => $regex,
            'paramNames' => $paramNames,
            'handler'    => $handler,
        ];
    }

    /**
     * Get all registered routes.
     *
     * @return array<int, array{method:string, path:string, regex:string, paramNames:string[], handler:callable}>
     */
    public function all(): array
    {
        return $this->routes;
    }
}