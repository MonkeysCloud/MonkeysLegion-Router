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
 * PSR-15 middleware pipeline with priority ordering.
 *
 * v2 changes:
 *  • Accepts only `Psr\Http\Server\MiddlewareInterface` (no legacy).
 *  • Cursor-based dispatch — zero anonymous class allocations.
 *  • `lock()` pre-sorts the stack for zero-cost per-request dispatch.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class MiddlewarePipeline
{
    /** @var list<array{mw: MiddlewareInterface, priority: int, index: int}> */
    private array $stack = [];

    private bool $locked = false;
    private int  $insertionIndex = 0;

    /**
     * @param list<MiddlewareInterface> $middleware
     */
    public function __construct(array $middleware = [])
    {
        foreach ($middleware as $mw) {
            $this->pipe($mw);
        }
    }

    /**
     * Add middleware to the pipeline.
     *
     * @param int $priority Higher = runs earlier. Default 0.
     */
    public function pipe(MiddlewareInterface $middleware, int $priority = 0): self
    {
        if ($this->locked) {
            throw new \LogicException('Cannot add middleware to a locked pipeline.');
        }

        $this->stack[] = [
            'mw'       => $middleware,
            'priority' => $priority,
            'index'    => $this->insertionIndex++,
        ];

        return $this;
    }

    /**
     * Lock and sort the pipeline — call once at boot time.
     */
    public function lock(): self
    {
        $this->sort();
        $this->locked = true;
        return $this;
    }

    /**
     * Process the request through the pipeline.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $finalHandler,
    ): ResponseInterface {
        if (!$this->locked) {
            $this->sort();
        }

        if (count($this->stack) === 0) {
            return $finalHandler->handle($request);
        }

        $handler = new CursorHandler($this->stack, $finalHandler);
        return $handler->handle($request);
    }

    /**
     * Create from an array of middleware.
     *
     * @param list<MiddlewareInterface> $middleware
     */
    public static function from(array $middleware): self
    {
        return new self($middleware);
    }

    /**
     * Sort stack by priority (descending), then insertion order (ascending).
     */
    private function sort(): void
    {
        usort($this->stack, static fn(array $a, array $b): int =>
            $b['priority'] <=> $a['priority'] ?: $a['index'] <=> $b['index']
        );
    }
}