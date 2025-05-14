<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Router\Attributes\Route as RouteAttribute;
use MonkeysLegion\Router\RouteCollection;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The main HTTP router for MonkeysLegion.
 *
 * Registers routes (via DSL or #[Route] attributes) and dispatches
 * PSR‑7 requests to the first matching handler.
 */
class Router
{
    public function __construct(
        private RouteCollection $routes
    ) {}

    /**
     * Add a new route definition.
     *
     * @param string   $method  HTTP method, e.g. 'GET', 'POST'
     * @param string   $path    URI path, may contain placeholders {name}
     * @param callable $handler Handler callback: function(ServerRequestInterface, ...$params): ResponseInterface
     */
    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes->add($method, $path, $handler);
    }

    /**
     * Scan a controller object for methods annotated with #[Route]
     * and register each as a route.
     *
     * @param object $controller Instance of a controller class
     */
    public function registerController(object $controller): void
    {
        $ref = new \ReflectionClass($controller);
        foreach ($ref->getMethods() as $method) {
            foreach ($method->getAttributes(RouteAttribute::class) as $attr) {
                /** @var RouteAttribute $meta */
                $meta = $attr->newInstance();
                $this->add($meta->method, $meta->path, [$controller, $method->getName()]);
            }
        }
    }

    /**
     * Dispatch an incoming PSR‑7 request to the first matching route.
     *
     * Populates path parameters into request attributes, then invokes the handler.
     * Returns a 404 Response if no route matches.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path   = $request->getUri()->getPath();

        foreach ($this->routes->all() as $route) {
            if (strtoupper($route['method']) !== $method) {
                continue;
            }

            if (! preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            // Attach named placeholders as request attributes
            foreach ($route['paramNames'] as $name) {
                if (isset($matches[$name])) {
                    $request = $request->withAttribute($name, $matches[$name]);
                }
            }

            // Invoke handler and return its ResponseInterface
            return call_user_func_array(
                $route['handler'],
                array_merge([$request], array_map(fn($n) => $matches[$n], $route['paramNames']))
            );
        }

        // No route matched → return a simple 404
        return new Response(
            Stream::createFromString('404 Not Found'),
            404,
            [],
        );
    }
}