<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * ISO 8601 date format constraint (YYYY-MM-DD).
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class DateConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            && strtotime($value) !== false;
    }

    public function getPattern(): string
    {
        return '\d{4}-\d{2}-\d{2}';
    }
}
