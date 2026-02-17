<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Adapts a plain callable into a {@see RequestHandlerInterface}.
 *
 * This is the backward-compatibility bridge: existing code that passes
 * `callable $next` to middleware (v2.0 style) is transparently wrapped
 * so that the new PSR-15–aligned pipeline works without changes.
 *
 * @internal
 */
final class CallableHandlerAdapter implements RequestHandlerInterface
{
    /** @var callable(ServerRequestInterface): ResponseInterface */
    private $callable;

    /**
     * @param callable(ServerRequestInterface): ResponseInterface $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->callable)($request);
    }
}
