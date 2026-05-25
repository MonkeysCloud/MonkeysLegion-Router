<?php

declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Immutable value object representing a single route definition.
 *
 * Replaces raw arrays used in v1, providing type safety and
 * IDE completion for every route property.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final readonly class RouteDefinition
{
    /**
     * @param string                       $method      HTTP method (upper-case).
     * @param string                       $path        URI template.
     * @param string                       $regex       Compiled regex pattern.
     * @param \Closure|array               $handler     Route handler.
     * @param list<string>                 $paramNames  Extracted parameter names.
     * @param list<string>                 $optionalParams Optional parameter names.
     * @param string                       $name        Route name for URL generation.
     * @param list<string>                 $middleware  Middleware identifiers.
     * @param array<string, string>        $constraints Parameter constraints.
     * @param array<string, mixed>         $defaults    Default parameter values.
     * @param string                       $domain      Domain constraint pattern.
     * @param array<string, mixed>         $meta        Additional metadata.
     * @param int                          $specificity Routing priority score.
     */
    public function __construct(
        public string           $method,
        public string           $path,
        public string           $regex,
        public \Closure|array   $handler,
        public array            $paramNames     = [],
        public array            $optionalParams = [],
        public string           $name           = '',
        public array            $middleware     = [],
        public array            $constraints   = [],
        public array            $defaults      = [],
        public string           $domain        = '',
        public array            $meta          = [],
        public int              $specificity   = 0,
    ) {}

    /**
     * Get the HTTP method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the URI template path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the compiled regex pattern.
     */
    public function getRegex(): string
    {
        return $this->regex;
    }

    /**
     * Get the route handler.
     */
    public function getHandler(): \Closure|array
    {
        return $this->handler;
    }

    /**
     * Get the extracted parameter names.
     *
     * @return list<string>
     */
    public function getParamNames(): array
    {
        return $this->paramNames;
    }

    /**
     * Get the optional parameter names.
     *
     * @return list<string>
     */
    public function getOptionalParams(): array
    {
        return $this->optionalParams;
    }

    /**
     * Get the route name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the assigned middleware.
     *
     * @return list<string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get parameter constraints.
     *
     * @return array<string, string>
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * Get default parameter values.
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * Get the domain constraint pattern.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Get additional metadata.
     *
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Get the routing priority score.
     */
    public function getSpecificity(): int
    {
        return $this->specificity;
    }

    /**
     * Get the handler as a [class, method] pair for reflection.
     *
     * @return array{0: class-string, 1: string}|null
     */
    public function handlerPair(): ?array
    {
        // Direct array handler
        if (is_array($this->handler) && count($this->handler) >= 2) {
            return [$this->handler[0], $this->handler[1]];
        }

        // Handler stored in meta (OpenAPI compatibility)
        if (isset($this->meta['handler']) && is_array($this->meta['handler'])) {
            return $this->meta['handler'];
        }

        return null;
    }

    /**
     * Export to an array for cache serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method'         => $this->method,
            'path'           => $this->path,
            'regex'          => $this->regex,
            'handler'        => $this->handler,
            'paramNames'     => $this->paramNames,
            'optionalParams' => $this->optionalParams,
            'name'           => $this->name,
            'middleware'     => $this->middleware,
            'constraints'    => $this->constraints,
            'defaults'       => $this->defaults,
            'domain'         => $this->domain,
            'meta'           => $this->meta,
            'specificity'    => $this->specificity,
        ];
    }
}
