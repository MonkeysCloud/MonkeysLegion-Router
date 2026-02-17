<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Adapts a legacy v2.0 middleware (callable $next) to the new PSR-15
 * style {@see MiddlewareInterface}.
 *
 * Any middleware class whose `process()` signature still uses
 * `callable $next` is auto-wrapped by the pipeline so it can
 * participate transparently in the new handler-based chain.
 *
 * @internal
 */
final class LegacyMiddlewareAdapter implements MiddlewareInterface
{
    /** @var object Middleware with legacy process(request, callable) */
    private object $legacy;

    public function __construct(object $legacy)
    {
        $this->legacy = $legacy;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Convert RequestHandlerInterface back to a callable for the legacy signature
        $next = static fn(ServerRequestInterface $req): ResponseInterface => $handler->handle($req);

        return $this->legacy->process($request, $next);
    }
}
