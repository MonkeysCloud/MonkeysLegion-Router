<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Fluent CRUD resource route registrar.
 *
 * Registration is deferred — routes are added when `register()` is called
 * or when the instance is destructed.
 *
 * Usage:
 *   $router->resource('/photos', PhotoController::class);
 *   $router->apiResource('/photos', PhotoController::class)->only(['index', 'show']);
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RouteRegistrar
{
    private const RESOURCE_MAP = [
        'index'   => ['method' => 'GET',    'suffix' => ''],
        'create'  => ['method' => 'GET',    'suffix' => '/create'],
        'store'   => ['method' => 'POST',   'suffix' => ''],
        'show'    => ['method' => 'GET',    'suffix' => '/{id}'],
        'edit'    => ['method' => 'GET',    'suffix' => '/{id}/edit'],
        'update'  => ['method' => 'PUT',    'suffix' => '/{id}'],
        'destroy' => ['method' => 'DELETE', 'suffix' => '/{id}'],
    ];

    private const API_ACTIONS = ['index', 'store', 'show', 'update', 'destroy'];

    /** @var list<string> Allowed action names. */
    private array $actions;

    /** @var list<string> Middleware for resource routes. */
    private array $middleware = [];

    private readonly string $prefix;
    private readonly string $resourceName;
    private bool $registered = false;

    /**
     * @param Router            $router
     * @param string            $prefix     e.g. '/photos'
     * @param object|class-string $controller Controller instance or class name.
     * @param list<string>      $actions    CRUD actions to register.
     */
    public function __construct(
        private readonly Router $router,
        string $prefix,
        private readonly object|string $controller,
        array $actions = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'],
    ) {
        $this->prefix = '/' . trim($prefix, '/');
        $this->actions = $actions;

        $parts = explode('/', trim($this->prefix, '/'));
        $this->resourceName = end($parts);
    }

    /**
     * Auto-register on destruct if not already done.
     */
    public function __destruct()
    {
        if (!$this->registered) {
            $this->register();
        }
    }

    public function only(array $actions): self
    {
        $this->actions = array_values(array_intersect($this->actions, $actions));
        return $this;
    }

    public function except(array $actions): self
    {
        $this->actions = array_values(array_diff($this->actions, $actions));
        return $this;
    }

    /**
     * @param string|list<string> $middleware
     */
    public function middleware(string|array $middleware): self
    {
        $this->middleware = [...$this->middleware, ...(array) $middleware];
        return $this;
    }

    /**
     * Register configured resource routes.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        foreach ($this->actions as $action) {
            if (!isset(self::RESOURCE_MAP[$action])) {
                continue;
            }

            $spec = self::RESOURCE_MAP[$action];
            $path = $this->prefix . $spec['suffix'];
            $name = $this->resourceName . '.' . $action;

            // Resolve handler
            $controller = is_string($this->controller)
                ? $this->controller
                : $this->controller;

            if (is_object($controller) && method_exists($controller, $action)) {
                $handler = [$controller, $action];
            } elseif (is_string($controller)) {
                $handler = [$controller, $action];
            } else {
                $handler = fn() => throw new \BadMethodCallException(
                    "Controller method '{$action}' not found on " . (is_object($controller) ? $controller::class : $controller),
                );
            }

            $this->router->add(
                $spec['method'],
                $path,
                $handler,
                $name,
                $this->middleware,
            );
        }
    }

    /**
     * Create a registrar for API-only actions (no create/edit).
     */
    public static function api(Router $router, string $prefix, object|string $controller): self
    {
        return new self($router, $prefix, $controller, self::API_ACTIONS);
    }
}
