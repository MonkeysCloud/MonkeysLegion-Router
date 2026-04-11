<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * IPv4 and IPv6 address constraint.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class IpConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    public function getPattern(): string
    {
        // Matches IPv4 (e.g. 192.168.1.1) and IPv6 (e.g. ::1, fe80::1)
        return '(?:\d{1,3}(?:\.\d{1,3}){3}|[0-9a-fA-F]{1,4}(?::[0-9a-fA-F]{0,4}){2,7})';
    }
}
