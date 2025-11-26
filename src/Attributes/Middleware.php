<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * Middleware attribute for applying middleware to routes.
 *
 * Usage:
 *   #[Middleware(['auth', 'throttle:60,1'])]
 *   class AdminController { ... }
 *
 *   #[Middleware('auth')]
 *   public function dashboard() { ... }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Middleware
{
    public array $middleware;

    public function __construct(string|array $middleware)
    {
        $this->middleware = (array) $middleware;
    }
}