<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class UuidConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    public function getPattern(): string
    {
        return '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
    }
}
