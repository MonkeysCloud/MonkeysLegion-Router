<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class SlugConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;
    }

    public function getPattern(): string
    {
        return '[a-z0-9]+(?:-[a-z0-9]+)*';
    }
}
