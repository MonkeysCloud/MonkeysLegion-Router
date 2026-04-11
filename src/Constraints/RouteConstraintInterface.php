<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Contract for route parameter constraints.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
interface RouteConstraintInterface
{
    /**
     * Test if a parameter value matches this constraint.
     */
    public function matches(string $value): bool;

    /**
     * Get the regex pattern for this constraint.
     */
    public function getPattern(): string;
}