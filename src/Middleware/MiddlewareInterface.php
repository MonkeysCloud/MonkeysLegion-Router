<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware interface for processing requests before/after route handlers.
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response.
     *
     * @param ServerRequestInterface $request  The incoming request
     * @param callable               $next     The next middleware/handler in the chain
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface;
}