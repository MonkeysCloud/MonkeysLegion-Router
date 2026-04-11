<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Route attribute for defining HTTP routes on controller methods.
 *
 * Usage:
 *   #[Route('GET', '/users', name: 'users.index')]
 *   #[Route(['GET','POST'], '/users/{id:\d+}', name: 'users.detail', middleware: ['auth'])]
 *   #[Route('POST', '/login', summary: 'User login', tags: ['Auth'])]
 *   #[Route('GET', '/posts/{slug?}', name: 'posts.show')]
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /** @var list<string> Normalized HTTP methods. */
    public readonly array $methods;

    /**
     * @param string|list<string>          $methods     HTTP verb(s).
     * @param string                       $path        URI template.
     * @param string                       $name        Route name for URL generation.
     * @param string                       $summary     Short description.
     * @param list<string>                 $tags        Grouping labels.
     * @param list<string|class-string>    $middleware  Middleware to apply.
     * @param array<string, string>        $where       Parameter constraints.
     * @param array<string, mixed>         $defaults    Default parameter values.
     * @param string                       $domain      Domain constraint.
     * @param string                       $description Detailed description.
     * @param array<string, mixed>         $meta        Additional metadata.
     */
    public function __construct(
        string|array           $methods,
        public readonly string $path        = '',
        public readonly string $name        = '',
        public readonly string $summary     = '',
        public readonly array  $tags        = [],
        public readonly array  $middleware  = [],
        public readonly array  $where       = [],
        public readonly array  $defaults    = [],
        public readonly string $domain      = '',
        public readonly string $description = '',
        public readonly array  $meta        = [],
    ) {
        // Normalize to upper-case array
        $this->methods = array_map('strtoupper', (array) $methods);
    }

    /**
     * Check if this route has a specific middleware.
     */
    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }

    /**
     * Get constraint for a parameter.
     */
    public function getConstraint(string $param): ?string
    {
        return $this->where[$param] ?? null;
    }
}