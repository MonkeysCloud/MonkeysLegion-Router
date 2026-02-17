<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware interface for processing requests before/after route handlers.
 *
 * This interface retains the original v2.0 `callable $next` contract so that
 * existing middleware implementations can be loaded without a PHP fatal error.
 *
 * New middleware is encouraged to implement {@see Psr15MiddlewareInterface}
 * instead, which uses {@see RequestHandlerInterface}.  The pipeline accepts
 * both interfaces transparently.
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response.
     *
     * @param ServerRequestInterface $request The incoming request
     * @param callable               $next    The next middleware/handler in the chain
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface;
}