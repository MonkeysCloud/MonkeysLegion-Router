<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Adapts a plain callable into a PSR-15 RequestHandlerInterface.
 *
 * Bridge for route handlers that are plain functions/closures
 * so the middleware pipeline can use a single interface.
 *
 * @internal
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CallableHandlerAdapter implements RequestHandlerInterface
{
    /** @var callable(ServerRequestInterface): ResponseInterface */
    private $handler;

    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->handler)($request);
    }
}
