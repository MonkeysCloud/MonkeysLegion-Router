<?php

declare(strict_types=1);

namespace MonkeysLegion\Router;

class RouteCollection
{
    /**
     * @var array<int, array{
     *     method: string,
     *     path: string,
     *     regex: string,
     *     paramNames: string[],
     *     handler: callable,
     *     specificity: int
     * }>
     */
    private array $routes = [];
    private bool $needsSorting = false;

    public function add(string $method, string $path, callable $handler): void
    {
        $paramNames = [];

        // Convert "/users/{id}" into a regex with named capture groups
        $regex = preg_replace_callback(
            '/\{([^}]+)\}/',
            function (array $matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                // capture one or more chars that are not "/"
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $path
        );

        // Anchor to beginning/end
        $regex = '#^' . $regex . '$#';

        // Calculate specificity score
        $specificity = $this->calculateSpecificity($path);

        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'regex'      => $regex,
            'paramNames' => $paramNames,
            'handler'    => $handler,
            'specificity' => $specificity,
        ];

        $this->needsSorting = true;
    }

    /**
     * Calculate route specificity score (higher = more specific)
     */
    private function calculateSpecificity(string $path): int
    {
        $score = 0;

        // Count static segments (more specific)
        $segments = explode('/', trim($path, '/'));
        foreach ($segments as $segment) {
            if (!str_contains($segment, '{')) {
                $score += 1000; // Static segments are highly specific
            } else {
                $score += 1; // Dynamic segments are less specific
            }
        }

        // Longer paths are generally more specific
        $score += strlen($path);

        return $score;
    }

    /**
     * Get all registered routes, sorted by specificity.
     */
    public function all(): array
    {
        if ($this->needsSorting) {
            $this->sortRoutes();
            $this->needsSorting = false;
        }

        return $this->routes;
    }

    private function sortRoutes(): void
    {
        usort($this->routes, function ($a, $b) {
            // First sort by method (so GET and POST routes don't interfere)
            if ($a['method'] !== $b['method']) {
                return strcmp($a['method'], $b['method']);
            }

            // Then sort by specificity (higher first)
            return $b['specificity'] <=> $a['specificity'];
        });
    }
}