<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Route prefix attribute for applying a common path prefix to all
 * routes in a controller.
 *
 * Usage:
 *   #[RoutePrefix('/api/v1/users')]
 *   class UserController { ... }
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class RoutePrefix
{
    public readonly string $prefix;

    /**
     * @param string       $prefix     Path prefix.
     * @param list<string> $middleware Controller-level middleware.
     */
    public function __construct(
        string                $prefix,
        public readonly array $middleware = [],
    ) {
        $this->prefix = '/' . trim($prefix, '/');
    }
}