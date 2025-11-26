<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * Interface for route parameter constraints.
 */
interface RouteConstraintInterface
{
    /**
     * Check if the given value matches the constraint.
     *
     * @param string $value The parameter value to validate
     * @return bool True if the value matches, false otherwise
     */
    public function matches(string $value): bool;

    /**
     * Get the regex pattern for this constraint.
     *
     * @return string The regex pattern (without delimiters)
     */
    public function getPattern(): string;
}