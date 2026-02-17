<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware pipeline that processes a chain of middleware.
 *
 * v2.2 improvements:
 *  - PSR-15 aligned: uses {@see RequestHandlerInterface} throughout.
 *  - **Priority ordering**: middleware with higher priority runs first.
 *  - **Legacy support**: v2.0 middleware (callable $next) is auto-adapted.
 *  - Accepts both {@see MiddlewareInterface} instances and legacy objects.
 */
class MiddlewarePipeline
{
    /**
     * @var array<array{middleware: MiddlewareInterface, priority: int}>
     */
    private array $stack = [];

    private bool $sorted = true;

    /**
     * @param array<MiddlewareInterface|object> $middleware
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
     * @param MiddlewareInterface|object $middleware  PSR-15 or legacy middleware
     * @param int                        $priority    Higher = runs earlier (default 0)
     */
    public function pipe(object $middleware, int $priority = 0): self
    {
        $adapted = $this->adapt($middleware);

        $this->stack[] = [
            'middleware' => $adapted,
            'priority'   => $priority,
        ];

        $this->sorted = false;

        return $this;
    }

    /**
     * Process the request through the middleware pipeline.
     *
     * @param ServerRequestInterface $request
     * @param callable               $finalHandler  The route handler (callable style, auto-adapted)
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, callable $finalHandler): ResponseInterface
    {
        $this->sort();

        $handler = new CallableHandlerAdapter($finalHandler);

        // Build the handler chain from inside out
        foreach (array_reverse($this->stack) as $entry) {
            $mw = $entry['middleware'];
            $handler = new class($mw, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private readonly MiddlewareInterface $middleware,
                    private readonly RequestHandlerInterface $next,
                ) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }

        return $handler->handle($request);
    }

    /**
     * Create a new pipeline from an array of middleware.
     *
     * @param array<MiddlewareInterface|object> $middleware
     */
    public static function from(array $middleware): self
    {
        return new self($middleware);
    }

    /**
     * Adapt legacy or PSR-15 middleware into the current interface.
     */
    private function adapt(object $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        // Check for legacy process(request, callable) signature
        if (method_exists($middleware, 'process')) {
            return new LegacyMiddlewareAdapter($middleware);
        }

        throw new \InvalidArgumentException(
            sprintf(
                'Middleware must implement %s or have a process() method. Got: %s',
                MiddlewareInterface::class,
                get_class($middleware)
            )
        );
    }

    /**
     * Sort by priority (higher first) — stable sort preserves insertion order for equal priorities.
     */
    private function sort(): void
    {
        if ($this->sorted) {
            return;
        }

        usort($this->stack, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
        $this->sorted = true;
    }
}