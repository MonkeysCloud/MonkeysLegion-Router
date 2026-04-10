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
        // Matches both IPv4 and simple IPv6
        return '[0-9a-fA-F.:]+';
    }
}
