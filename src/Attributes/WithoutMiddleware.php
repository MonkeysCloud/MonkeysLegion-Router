<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Exclude specific middleware from a method.
 *
 * Usage:
 *   #[RoutePrefix('/admin')]
 *   #[Middleware(['auth', 'cors'])]
 *   class AdminController
 *   {
 *       #[Route('GET', '/login')]
 *       #[WithoutMiddleware('auth')]  // Login page doesn't need auth
 *       public function login() { ... }
 *   }
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class WithoutMiddleware
{
    /** @var list<string> */
    public readonly array $excluded;

    public function __construct(string|array $middleware)
    {
        $this->excluded = (array) $middleware;
    }
}
