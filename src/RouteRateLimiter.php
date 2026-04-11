<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonkeysLegion Framework — Router Package
 *
 * PSR-15 middleware for per-route rate limiting.
 *
 * Reads `meta['throttle']` from the matched route and enforces
 * request limits. Returns 429 Too Many Requests with Retry-After.
 *
 * Usage:
 *   // Register as global middleware
 *   $pipeline->pipe(new RouteRateLimiter($cache), priority: 100);
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RouteRateLimiter implements MiddlewareInterface
{
    /** @var array<string, array{count: int, reset: int}> In-memory store (swap for cache in production). */
    private array $store = [];

    /**
     * @param object|null $cache Optional cache backend with get/set methods.
     */
    public function __construct(
        private readonly ?object $cache = null,
    ) {}

    public function process(
        ServerRequestInterface  $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $throttle = $request->getAttribute('_route_throttle');

        if ($throttle === null) {
            return $handler->handle($request);
        }

        $max    = $throttle['max'] ?? 60;
        $per    = $throttle['per'] ?? 60;
        $by     = $throttle['by'] ?? 'ip';

        $key    = $this->resolveKey($request, $by);
        $bucket = $this->getBucket($key, $per);

        if ($bucket['count'] >= $max) {
            $retryAfter = max(1, $bucket['reset'] - time());
            return new Response(
                Stream::createFromString(json_encode([
                    'error'   => 'Too Many Requests',
                    'message' => "Rate limit exceeded. Try again in {$retryAfter} seconds.",
                ], JSON_THROW_ON_ERROR)),
                429,
                [
                    'Content-Type'            => 'application/json',
                    'Retry-After'             => (string) $retryAfter,
                    'X-RateLimit-Limit'       => (string) $max,
                    'X-RateLimit-Remaining'   => '0',
                    'X-RateLimit-Reset'       => (string) $bucket['reset'],
                ],
            );
        }

        $bucket['count']++;
        $this->setBucket($key, $bucket, $per);

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $max)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $max - $bucket['count']))
            ->withHeader('X-RateLimit-Reset', (string) $bucket['reset']);
    }

    private function resolveKey(ServerRequestInterface $request, string $by): string
    {
        $path = $request->getUri()->getPath();

        return match ($by) {
            'ip'    => 'rl:' . ($request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1') . ':' . $path,
            'user'  => 'rl:user:' . ($request->getAttribute('_user_id', 'anon')) . ':' . $path,
            'route' => 'rl:route:' . $path,
            default => 'rl:' . $by . ':' . $path,
        };
    }

    /**
     * @return array{count: int, reset: int}
     */
    private function getBucket(string $key, int $windowSeconds): array
    {
        // Try cache first
        if ($this->cache !== null && method_exists($this->cache, 'get')) {
            $cached = $this->cache->get($key);
            if (is_array($cached) && isset($cached['count'], $cached['reset'])) {
                if ($cached['reset'] > time()) {
                    return $cached;
                }
            }
        }

        // In-memory fallback
        if (isset($this->store[$key]) && $this->store[$key]['reset'] > time()) {
            return $this->store[$key];
        }

        return ['count' => 0, 'reset' => time() + $windowSeconds];
    }

    /**
     * @param array{count: int, reset: int} $bucket
     */
    private function setBucket(string $key, array $bucket, int $windowSeconds): void
    {
        if ($this->cache !== null && method_exists($this->cache, 'set')) {
            $this->cache->set($key, $bucket, $windowSeconds);
        }

        $this->store[$key] = $bucket;
    }
}
