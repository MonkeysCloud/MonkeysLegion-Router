<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class AlphanumericConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return ctype_alnum($value);
    }

    public function getPattern(): string
    {
        return '[a-zA-Z0-9]+';
    }
}
