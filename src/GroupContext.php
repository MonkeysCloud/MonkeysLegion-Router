<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Immutable value object carrying inherited group context
 * (prefix, middleware, constraints, domain) for nested route groups.
 *
 * Eliminates mutable state in the Router class — each group
 * creates a new context via `with*()` methods.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final readonly class GroupContext
{
    /**
     * @param string               $prefix     Accumulated path prefix.
     * @param list<string>         $middleware Accumulated middleware stack.
     * @param array<string,string> $constraints Accumulated parameter constraints.
     * @param string               $domain     Domain constraint.
     */
    public function __construct(
        public string $prefix      = '',
        public array  $middleware  = [],
        public array  $constraints = [],
        public string $domain      = '',
    ) {}

    public function withPrefix(string $prefix): self
    {
        $combined = $this->prefix . '/' . ltrim($prefix, '/');
        // Normalize multiple slashes
        $combined = preg_replace('#/{2,}#', '/', $combined);

        return new self(
            $combined,
            $this->middleware,
            $this->constraints,
            $this->domain,
        );
    }

    /**
     * @param list<string> $middleware
     */
    public function withMiddleware(array $middleware): self
    {
        return new self(
            $this->prefix,
            [...$this->middleware, ...$middleware],
            $this->constraints,
            $this->domain,
        );
    }

    /**
     * @param array<string, string> $constraints
     */
    public function withConstraints(array $constraints): self
    {
        return new self(
            $this->prefix,
            $this->middleware,
            [...$this->constraints, ...$constraints],
            $this->domain,
        );
    }

    public function withDomain(string $domain): self
    {
        return new self(
            $this->prefix,
            $this->middleware,
            $this->constraints,
            $domain,
        );
    }

    /**
     * Apply context to a path, producing the full route path.
     */
    public function applyPath(string $path): string
    {
        $full = $this->prefix . '/' . ltrim($path, '/');
        // Normalize multiple slashes
        $full = preg_replace('#/{2,}#', '/', $full);
        return $full !== '/' ? rtrim($full, '/') : '/';
    }
}
