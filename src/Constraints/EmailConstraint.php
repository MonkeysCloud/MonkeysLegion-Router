<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class EmailConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getPattern(): string
    {
        // Anchored pattern without overlapping quantifiers to prevent ReDoS
        return '[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9](?:[a-zA-Z0-9\-]*[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9\-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}';
    }
}
