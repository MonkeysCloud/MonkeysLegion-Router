<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware interface for processing requests before/after route handlers.
 *
 * v2.2 aligns with PSR-15 by accepting a {@see RequestHandlerInterface}
 * instead of a raw callable.  For **full backward compatibility**, the
 * pipeline still supports the legacy `callable $next` signature via an
 * internal adapter — existing middleware will keep working unchanged.
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response.
     *
     * @param ServerRequestInterface   $request The incoming request
     * @param RequestHandlerInterface  $handler The next handler in the chain
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
}