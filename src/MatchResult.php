<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Immutable value object returned when a route successfully matches.
 *
 * Contains the matched route definition, extracted parameters,
 * and resolved middleware list.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final readonly class MatchResult
{
    /**
     * @param RouteDefinition        $route      The matched route definition.
     * @param array<string, string>  $parameters Extracted path parameters.
     */
    public function __construct(
        public RouteDefinition $route,
        public array           $parameters = [],
    ) {}

    /**
     * Get route handler.
     */
    public function handler(): array|callable|\Closure
    {
        return $this->route->handler;
    }

    /**
     * Get a single parameter value.
     */
    public function param(string $name, mixed $default = null): mixed
    {
        return $this->parameters[$name] ?? $this->route->defaults[$name] ?? $default;
    }

    /**
     * Get the route name.
     */
    public function name(): string
    {
        return $this->route->name;
    }

    /**
     * Get route middleware list.
     *
     * @return list<string>
     */
    public function middleware(): array
    {
        return $this->route->middleware;
    }
}
