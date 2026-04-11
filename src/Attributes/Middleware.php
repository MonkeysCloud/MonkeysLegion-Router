<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Middleware attribute for applying middleware to controllers or methods.
 *
 * v2 additions: `only` and `except` parameters for conditional application.
 *
 * Usage:
 *   #[Middleware(['auth', 'throttle:60,1'])]
 *   class AdminController { ... }
 *
 *   #[Middleware('auth', except: ['login', 'register'])]
 *   class AuthController { ... }
 *
 *   #[Middleware('log', only: ['index', 'show'])]
 *   class UserController { ... }
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Middleware
{
    /** @var list<string> Middleware identifiers. */
    public readonly array $middleware;

    /**
     * @param string|list<string> $middleware Middleware name(s).
     * @param list<string>        $only       Apply only to these methods.
     * @param list<string>        $except     Exclude these methods.
     */
    public function __construct(
        string|array          $middleware,
        public readonly array $only   = [],
        public readonly array $except = [],
    ) {
        $this->middleware = (array) $middleware;
    }

    /**
     * Check if this middleware applies to a given method name.
     */
    public function appliesTo(string $methodName): bool
    {
        if ($this->only !== []) {
            return in_array($methodName, $this->only, true);
        }

        if ($this->except !== []) {
            return !in_array($methodName, $this->except, true);
        }

        return true;
    }
}