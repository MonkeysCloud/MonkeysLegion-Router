<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Adapts a plain object that has a `process(request, callable)` method
 * to the legacy {@see MiddlewareInterface}.
 *
 * This is used by the pipeline when encountering objects that do not
 * implement either interface but have a compatible process() method.
 *
 * @internal
 */
final class LegacyMiddlewareAdapter implements MiddlewareInterface
{
    /** @var object Object with legacy process(request, callable) */
    private object $legacy;

    public function __construct(object $legacy)
    {
        $this->legacy = $legacy;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return $this->legacy->process($request, $next);
    }
}
