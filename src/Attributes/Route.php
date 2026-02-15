<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * Route attribute for defining HTTP routes on controller methods.
 *
 * Usage examples:
 *   #[Route('GET', '/users', name: 'user_collection')]
 *   #[Route(['GET','POST'], '/users/{id:\d+}', name: 'user_detail', middleware: ['auth'])]
 *   #[Route('POST', '/login', summary: 'User login', tags: ['Auth'])]
 *   #[Route('GET', '/posts/{id?}', name: 'post_view')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /**
     * @param string|array<string>        $methods     HTTP verb(s) – 'GET' or ['GET','POST']
     * @param string                      $path        URI template (/users/{id}, /users/{id:\d+}, /posts/{slug?})
     * @param string                      $name        Optional route name for URL generation
     * @param string                      $summary     Short human-readable description
     * @param array<string>               $tags        Grouping labels for API documentation
     * @param array<string|class-string>  $middleware  Middleware to apply to this route
     * @param array<string, string>       $where       Parameter constraints: ['id' => '\d+']
     * @param array<string, mixed>        $defaults    Default parameter values
     * @param string                      $domain      Domain constraint (optional)
     * @param string                      $description Detailed description for documentation
     * @param array<string, mixed>        $meta        Additional metadata
     */
    public function __construct(
        public string|array $methods,
        public string       $path,
        public string       $name        = '',
        public string       $summary     = '',
        public array        $tags        = [],
        public array        $middleware  = [],
        public array        $where       = [],
        public array        $defaults    = [],
        public string       $domain      = '',
        public string       $description = '',
        public array        $meta        = [],
    ) {
        // Normalize to upper-case array
        $this->methods = array_map('strtoupper', (array) $this->methods);

        // Ensure leading slash (but keep empty path as empty for clean prefix concatenation)
        $this->path = $this->path === '' ? '' : '/' . ltrim($this->path, '/');
    }

    /**
     * Check if route has a specific middleware
     */
    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }

    /**
     * Get constraint for a parameter
     */
    public function getConstraint(string $param): ?string
    {
        return $this->where[$param] ?? null;
    }
}