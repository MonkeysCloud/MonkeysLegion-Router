<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * CORS middleware for handling cross-origin requests.
 */
class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private array $config = []
    ) {
        $this->config = array_merge([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
            'exposed_headers' => [],
            'max_age' => 86400,
            'credentials' => false,
        ], $config);
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request);
        }

        // Process the request
        $response = $next($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($request, $response);
    }

    private function handlePreflightRequest(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(Stream::createFromString(''), 204);
        return $this->addCorsHeaders($request, $response);
    }

    private function addCorsHeaders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        if ($this->isOriginAllowed($origin)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin ?: '*');
        }

        if ($this->config['credentials']) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        $response = $response->withHeader(
            'Access-Control-Allow-Methods',
            implode(', ', $this->config['allowed_methods'])
        );

        $response = $response->withHeader(
            'Access-Control-Allow-Headers',
            implode(', ', $this->config['allowed_headers'])
        );

        if (!empty($this->config['exposed_headers'])) {
            $response = $response->withHeader(
                'Access-Control-Expose-Headers',
                implode(', ', $this->config['exposed_headers'])
            );
        }

        $response = $response->withHeader('Access-Control-Max-Age', (string) $this->config['max_age']);

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->config['allowed_origins'], true)) {
            return true;
        }

        return in_array($origin, $this->config['allowed_origins'], true);
    }
}