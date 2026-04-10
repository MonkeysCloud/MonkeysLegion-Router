<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class NumericConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return is_numeric($value);
    }

    public function getPattern(): string
    {
        return '\d+\.?\d*';
    }
}
