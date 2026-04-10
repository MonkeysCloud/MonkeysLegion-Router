<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Auto-register CRUD resource routes for a controller.
 *
 * Usage:
 *   #[ApiResource(prefix: '/users', parameter: 'user')]
 *   final class UserController
 *   {
 *       public function index(ServerRequestInterface $request): Response { ... }
 *       public function show(ServerRequestInterface $request, string $user): Response { ... }
 *       public function store(ServerRequestInterface $request): Response { ... }
 *       public function update(ServerRequestInterface $request, string $user): Response { ... }
 *       public function destroy(ServerRequestInterface $request, string $user): Response { ... }
 *   }
 *
 * Generates:
 *   GET    {prefix}            → index   ({resourceName}.index)
 *   GET    {prefix}/{param}    → show    ({resourceName}.show)
 *   POST   {prefix}            → store   ({resourceName}.store)
 *   PUT    {prefix}/{param}    → update  ({resourceName}.update)
 *   DELETE {prefix}/{param}    → destroy ({resourceName}.destroy)
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiResource
{
    /** @var list<string> Actions to register. */
    public readonly array $actions;

    /**
     * @param string        $prefix    Path prefix (e.g. '/users').
     * @param string        $parameter Route parameter name (e.g. 'user', 'id').
     * @param list<string>  $only      Only these actions (default: all 5).
     * @param list<string>  $except    Exclude these actions.
     * @param list<string>  $middleware Middleware for all resource routes.
     * @param string        $constraint Regex constraint for the parameter.
     */
    public function __construct(
        public readonly string $prefix     = '',
        public readonly string $parameter  = 'id',
        array                  $only       = [],
        array                  $except     = [],
        public readonly array  $middleware = [],
        public readonly string $constraint = '\\d+',
    ) {
        $all = ['index', 'show', 'store', 'update', 'destroy'];

        if ($only !== []) {
            $filtered = array_intersect($all, $only);
        } elseif ($except !== []) {
            $filtered = array_diff($all, $except);
        } else {
            $filtered = $all;
        }

        $this->actions = array_values($filtered);
    }
}
