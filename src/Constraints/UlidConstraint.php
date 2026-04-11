<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * ULID format constraint (26 characters, Crockford Base32).
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class UlidConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $value) === 1;
    }

    public function getPattern(): string
    {
        return '[0-9A-HJKMNP-TV-Z]{26}';
    }
}
