<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * Usage examples
 *
 *   #[Route(['GET','POST'], '/users', name: 'user_collection')]
 *   #[Route('POST', '/login', summary: 'User login', tags: ['Auth'])]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /**
     * @param string|array{string}|array<string> $methods  HTTP verb(s) – 'GET' or ['GET','POST']
     * @param string                             $path     URI template (/users/{id})
     * @param string                             $name     Optional router name / operationId
     * @param string                             $summary  Short human-readable description
     * @param string[]                           $tags     Grouping labels for docs
     */
    public function __construct(
        public string|array $methods,
        public string       $path,
        public string       $name    = '',
        public string       $summary = '',
        public array        $tags    = [],
    ) {
        // normalise to upper-case array
        $this->methods = array_map('strtoupper', (array) $this->methods);
        $this->path    = '/' . ltrim($this->path, '/'); // ensure leading slash
    }
}