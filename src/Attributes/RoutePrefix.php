<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * Route prefix attribute for applying a common path prefix to all routes in a controller.
 *
 * Usage:
 *   #[RoutePrefix('/api/v1/users')]
 *   class UserController { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class RoutePrefix
{
    public function __construct(
        public string $prefix,
        public array  $middleware = [],
    ) {
        $this->prefix = '/' . trim($this->prefix, '/');
    }
}