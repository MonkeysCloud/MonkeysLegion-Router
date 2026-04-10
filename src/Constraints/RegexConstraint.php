<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * Custom regex constraint.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RegexConstraint implements RouteConstraintInterface
{
    public function __construct(
        private readonly string $pattern,
    ) {}

    public function matches(string $value): bool
    {
        return preg_match('#^' . $this->pattern . '$#', $value) === 1;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }
}
