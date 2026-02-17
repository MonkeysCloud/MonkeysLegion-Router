<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-15 compatible request handler interface.
 *
 * Wraps the final route handler (or next middleware) so the whole
 * pipeline speaks a single, standards-based contract.
 *
 * Backward-compatible: existing callable-based middleware is adapted
 * transparently by {@see MiddlewarePipeline}.
 */
interface RequestHandlerInterface
{
    /**
     * Handle the request and produce a response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
