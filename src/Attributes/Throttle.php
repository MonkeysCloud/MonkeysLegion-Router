<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Per-route rate limiting attribute.
 *
 * Usage:
 *   #[Throttle(max: 60, per: 60)]     // 60 req per 60s
 *   #[Throttle(max: 5, per: 3600)]    // 5 req per hour
 *   #[Throttle(max: 10, per: 60, by: 'ip')]
 *
 * The `RouteRateLimiter` middleware reads this from route meta.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class Throttle
{
    /**
     * @param int    $max Maximum number of requests.
     * @param int    $per Time window in seconds.
     * @param string $by  Identifier strategy: 'ip', 'user', 'route'.
     */
    public function __construct(
        public readonly int    $max = 60,
        public readonly int    $per = 60,
        public readonly string $by  = 'ip',
    ) {}
}
