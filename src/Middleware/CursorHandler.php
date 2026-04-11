<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Cursor-based handler that walks the middleware stack one step
 * at a time — zero anonymous class allocations per dispatch.
 *
 * @internal
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CursorHandler implements RequestHandlerInterface
{
    private int $cursor = 0;

    /**
     * @param list<array{mw: MiddlewareInterface, priority: int, index: int}> $stack
     * @param RequestHandlerInterface $finalHandler
     */
    public function __construct(
        private readonly array                  $stack,
        private readonly RequestHandlerInterface $finalHandler,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->cursor >= count($this->stack)) {
            return $this->finalHandler->handle($request);
        }

        $entry = $this->stack[$this->cursor];
        $this->cursor++;

        return $entry['mw']->process($request, $this);
    }
}
