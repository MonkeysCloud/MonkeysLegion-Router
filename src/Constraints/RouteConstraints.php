<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * Collection of built-in route constraints.
 */
class RouteConstraints
{
    /**
     * Get a constraint by name or pattern.
     */
    public static function get(string $constraint): RouteConstraintInterface
    {
        return match ($constraint) {
            'int', 'integer' => new IntegerConstraint(),
            'numeric' => new NumericConstraint(),
            'alpha' => new AlphaConstraint(),
            'alphanumeric', 'alphanum' => new AlphanumericConstraint(),
            'slug' => new SlugConstraint(),
            'uuid' => new UuidConstraint(),
            'email' => new EmailConstraint(),
            default => new RegexConstraint($constraint),
        };
    }
}

/**
 * Integer constraint (digits only)
 */
class IntegerConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return ctype_digit($value);
    }

    public function getPattern(): string
    {
        return '\d+';
    }
}

/**
 * Numeric constraint (digits and decimal point)
 */
class NumericConstraint implements RouteConstraintInterface
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

/**
 * Alphabetic characters only
 */
class AlphaConstraint implements RouteConstraintInterface
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

/**
 * Alphanumeric characters only
 */
class AlphanumericConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return ctype_alnum($value);
    }

    public function getPattern(): string
    {
        return '[a-zA-Z0-9]+';
    }
}

/**
 * URL slug format (lowercase letters, numbers, hyphens)
 */
class SlugConstraint implements RouteConstraintInterface
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

/**
 * UUID format
 */
class UuidConstraint implements RouteConstraintInterface
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

/**
 * Email format
 */
class EmailConstraint implements RouteConstraintInterface
{
    public function matches(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getPattern(): string
    {
        return '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}';
    }
}

/**
 * Custom regex constraint
 */
class RegexConstraint implements RouteConstraintInterface
{
    public function __construct(
        private string $pattern
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