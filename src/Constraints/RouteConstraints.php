<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Constraints;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Factory for resolving route constraints by shorthand name.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RouteConstraints
{
    /**
     * Get a constraint by shorthand name or raw regex pattern.
     */
    public static function get(string $constraint): RouteConstraintInterface
    {
        return match ($constraint) {
            'int', 'integer'                => new IntegerConstraint(),
            'numeric'                       => new NumericConstraint(),
            'alpha'                         => new AlphaConstraint(),
            'alphanumeric', 'alphanum'      => new AlphanumericConstraint(),
            'slug'                          => new SlugConstraint(),
            'uuid'                          => new UuidConstraint(),
            'ulid'                          => new UlidConstraint(),
            'email'                         => new EmailConstraint(),
            'date'                          => new DateConstraint(),
            'ip'                            => new IpConstraint(),
            default                         => new RegexConstraint($constraint),
        };
    }
}