<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class AlphaConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return ctype_alpha($value);
    }

    public function getPattern(): string
    {
        return '[a-zA-Z]+';
    }
}
