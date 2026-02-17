<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * Route debugger that outputs a formatted list of all registered routes.
 *
 * Useful for development and debugging. Outputs an artisan-style table:
 *
 *   +--------+-------------------+----------------------+------------+
 *   | Method | URI               | Name                 | Middleware |
 *   +--------+-------------------+----------------------+------------+
 *   | GET    | /                 | home                 |            |
 *   | GET    | /users/{id}       | users.show           | auth       |
 *   | POST   | /api/users        | api.users.store      | auth, cors |
 *   +--------+-------------------+----------------------+------------+
 */
class RouteDebugger
{
    public function __construct(
        private Router $router
    ) {}

    /**
     * Return all routes as an array of descriptive maps.
     *
     * @return array<int, array{method: string, uri: string, name: string, middleware: string, domain: string}>
     */
    public function list(): array
    {
        $routes = [];

        foreach ($this->router->getRoutes()->all() as $route) {
            $routes[] = [
                'method'     => strtoupper($route['method']),
                'uri'        => $route['path'],
                'name'       => $route['name'] ?? '',
                'middleware'  => implode(', ', $route['middleware'] ?? []),
                'domain'     => $route['domain'] ?? '',
            ];
        }

        // Sort by path then by method
        usort($routes, function (array $a, array $b): int {
            return $a['uri'] <=> $b['uri'] ?: $a['method'] <=> $b['method'];
        });

        return $routes;
    }

    /**
     * Render routes as a CLI-friendly ASCII table.
     */
    public function render(): string
    {
        $routes = $this->list();

        if (empty($routes)) {
            return "No routes registered.\n";
        }

        // Calculate column widths
        $headers = ['Method', 'URI', 'Name', 'Middleware', 'Domain'];
        $widths  = array_map('strlen', $headers);

        foreach ($routes as $route) {
            $widths[0] = max($widths[0], strlen($route['method']));
            $widths[1] = max($widths[1], strlen($route['uri']));
            $widths[2] = max($widths[2], strlen($route['name']));
            $widths[3] = max($widths[3], strlen($route['middleware']));
            $widths[4] = max($widths[4], strlen($route['domain']));
        }

        $separator = '+' . implode('+', array_map(fn(int $w) => str_repeat('-', $w + 2), $widths)) . '+';

        $headerRow = '| '
            . str_pad($headers[0], $widths[0]) . ' | '
            . str_pad($headers[1], $widths[1]) . ' | '
            . str_pad($headers[2], $widths[2]) . ' | '
            . str_pad($headers[3], $widths[3]) . ' | '
            . str_pad($headers[4], $widths[4]) . ' |';

        $lines = [$separator, $headerRow, $separator];

        foreach ($routes as $route) {
            $lines[] = '| '
                . str_pad($route['method'], $widths[0]) . ' | '
                . str_pad($route['uri'], $widths[1]) . ' | '
                . str_pad($route['name'], $widths[2]) . ' | '
                . str_pad($route['middleware'], $widths[3]) . ' | '
                . str_pad($route['domain'], $widths[4]) . ' |';
        }

        $lines[] = $separator;

        return implode("\n", $lines) . "\n";
    }

    /**
     * Filter routes by method, path, or name.
     *
     * @return array<int, array{method: string, uri: string, name: string, middleware: string, domain: string}>
     */
    public function filter(?string $method = null, ?string $pathContains = null, ?string $name = null): array
    {
        $routes = $this->list();

        return array_values(array_filter($routes, function (array $route) use ($method, $pathContains, $name): bool {
            if ($method !== null && strtoupper($route['method']) !== strtoupper($method)) {
                return false;
            }
            if ($pathContains !== null && !str_contains($route['uri'], $pathContains)) {
                return false;
            }
            if ($name !== null && !str_contains($route['name'], $name)) {
                return false;
            }
            return true;
        }));
    }
}
