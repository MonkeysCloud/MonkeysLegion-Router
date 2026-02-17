<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * Fluent route registrar that provides convenience methods
 * for registering CRUD resource routes.
 *
 * Usage:
 *   $router->resource('/photos', PhotoController::class);          // Full CRUD
 *   $router->apiResource('/photos', PhotoController::class);       // API CRUD (no create/edit forms)
 *   $router->resource('/photos', $ctrl)->only(['index', 'show']);   // Subset
 *   $router->resource('/photos', $ctrl)->except(['destroy']);       // Inverted subset
 */
class RouteRegistrar
{
    /**
     * Standard resource action → HTTP method + path suffix mapping.
     *
     * @var array<string, array{method: string, suffix: string}>
     */
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

    /** @var string[] Allowed action names */
    private array $actions;

    private Router $router;
    private string $prefix;
    private object $controller;
    private string $resourceName;

    /**
     * @param Router       $router
     * @param string       $prefix      e.g. '/photos'
     * @param object       $controller  Controller instance or class-string
     * @param string[]     $actions     Which CRUD actions to register
     */
    public function __construct(
        Router $router,
        string $prefix,
        object $controller,
        array $actions = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']
    ) {
        $this->router     = $router;
        $this->prefix     = '/' . trim($prefix, '/');
        $this->controller = $controller;
        $this->actions    = $actions;

        // Derive resource name from prefix:  /admin/photos → photos
        $parts = explode('/', trim($this->prefix, '/'));
        $this->resourceName = end($parts);
    }

    /**
     * Register only the specified actions.
     */
    public function only(array $actions): self
    {
        $this->actions = array_intersect($this->actions, $actions);
        return $this;
    }

    /**
     * Register all actions except the specified ones.
     */
    public function except(array $actions): self
    {
        $this->actions = array_diff($this->actions, $actions);
        return $this;
    }

    /**
     * Register the configured resource routes.
     *
     * Called automatically at destruct if not called explicitly.
     */
    public function register(): void
    {
        foreach ($this->actions as $action) {
            if (!isset(self::RESOURCE_MAP[$action])) {
                continue;
            }

            $spec   = self::RESOURCE_MAP[$action];
            $path   = $this->prefix . $spec['suffix'];
            $name   = $this->resourceName . '.' . $action;

            // Try to bind to controller method matching the action name
            if (method_exists($this->controller, $action)) {
                $handler = [$this->controller, $action];
            } else {
                // Fallback: a no-op handler (user should override)
                $handler = fn() => throw new \BadMethodCallException(
                    "Controller method '{$action}' not found on " . get_class($this->controller)
                );
            }

            $this->router->add(
                $spec['method'],
                $path,
                $handler,
                $name
            );
        }
    }

    /**
     * Create a RouteRegistrar for API-only actions (no create/edit).
     */
    public static function api(Router $router, string $prefix, object $controller): self
    {
        return new self($router, $prefix, $controller, self::API_ACTIONS);
    }
}
