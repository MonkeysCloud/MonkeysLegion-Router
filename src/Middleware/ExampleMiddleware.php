<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Example authentication middleware.
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Check for authentication token
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return new Response(
                Stream::createFromString(json_encode(['error' => 'Unauthorized'])),
                401,
                ['Content-Type' => 'application/json']
            );
        }

        // Extract token
        $token = substr($authHeader, 7);

        // Validate token (example - implement your own logic)
        if (!$this->validateToken($token)) {
            return new Response(
                Stream::createFromString(json_encode(['error' => 'Invalid token'])),
                401,
                ['Content-Type' => 'application/json']
            );
        }

        // Add user info to request attributes
        $user = $this->getUserFromToken($token);
        $request = $request->withAttribute('user', $user);

        return $next($request);
    }

    private function validateToken(string $token): bool
    {
        // Implement your token validation logic
        return !empty($token);
    }

    private function getUserFromToken(string $token): array
    {
        // Implement your user retrieval logic
        return ['id' => 1, 'email' => 'user@example.com'];
    }
}

/**
 * Example rate limiting middleware.
 */
class ThrottleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxAttempts = 60,
        private int $decayMinutes = 1
    ) {}

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->tooManyAttempts($key)) {
            return new Response(
                Stream::createFromString(json_encode([
                    'error' => 'Too many requests',
                    'retry_after' => $this->getRetryAfter($key)
                ])),
                429,
                [
                    'Content-Type' => 'application/json',
                    'Retry-After' => (string) $this->getRetryAfter($key),
                ]
            );
        }

        $this->hit($key);

        $response = $next($request);

        return $this->addHeaders($response, $key);
    }

    private function resolveRequestSignature(ServerRequestInterface $request): string
    {
        // Use IP address as key (could also use user ID for authenticated requests)
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        return 'throttle:' . $ip . ':' . $request->getUri()->getPath();
    }

    private function tooManyAttempts(string $key): bool
    {
        // Implement using cache/Redis/etc
        // This is a simplified example
        $attempts = $this->getAttempts($key);
        return $attempts >= $this->maxAttempts;
    }

    private function hit(string $key): void
    {
        // Increment attempt counter
        // Store in cache with TTL = decayMinutes * 60
    }

    private function getAttempts(string $key): int
    {
        // Get current attempt count from cache
        return 0; // Placeholder
    }

    private function getRetryAfter(string $key): int
    {
        // Calculate seconds until rate limit resets
        return $this->decayMinutes * 60;
    }

    private function addHeaders(ResponseInterface $response, string $key): ResponseInterface
    {
        $attempts = $this->getAttempts($key);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $this->maxAttempts - $attempts));
    }
}

/**
 * Example logging middleware.
 */
class LoggingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $startTime = microtime(true);

        // Log request
        $this->logRequest($request);

        $response = $next($request);

        // Log response
        $duration = microtime(true) - $startTime;
        $this->logResponse($request, $response, $duration);

        return $response;
    }

    private function logRequest(ServerRequestInterface $request): void
    {
        error_log(sprintf(
            '[%s] %s %s',
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            $request->getUri()->getPath()
        ));
    }

    private function logResponse(ServerRequestInterface $request, ResponseInterface $response, float $duration): void
    {
        error_log(sprintf(
            '[%s] %s %s - %d (%.3fms)',
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            $request->getUri()->getPath(),
            $response->getStatusCode(),
            $duration * 1000
        ));
    }
}

/**
 * Example JSON request validation middleware.
 */
class JsonMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        if ($request->getMethod() !== 'GET' && $request->getMethod() !== 'HEAD') {
            $contentType = $request->getHeaderLine('Content-Type');

            if (str_contains($contentType, 'application/json')) {
                $body = (string) $request->getBody();

                if (!empty($body)) {
                    $data = json_decode($body, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return new Response(
                            Stream::createFromString(json_encode([
                                'error' => 'Invalid JSON',
                                'message' => json_last_error_msg()
                            ])),
                            400,
                            ['Content-Type' => 'application/json']
                        );
                    }

                    $request = $request->withParsedBody($data);
                }
            }
        }

        return $next($request);
    }
}