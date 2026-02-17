<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-15 aligned middleware interface (v2.2+).
 *
 * New middleware should implement this interface.  The pipeline accepts both
 * this interface and the legacy {@see MiddlewareInterface} with `callable $next`.
 */
interface Psr15MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response.
     *
     * @param ServerRequestInterface  $request The incoming request
     * @param RequestHandlerInterface $handler The next handler in the chain
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
}
