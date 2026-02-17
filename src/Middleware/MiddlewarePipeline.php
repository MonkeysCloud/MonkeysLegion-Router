<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware pipeline that processes a chain of middleware.
 *
 * v2.2 improvements:
 *  - Accepts both {@see Psr15MiddlewareInterface} and the legacy
 *    {@see MiddlewareInterface} (`callable $next`) transparently.
 *  - **Priority ordering**: middleware with higher priority runs first.
 *  - **Legacy support**: v2.0 middleware is accepted without adaptation.
 */
class MiddlewarePipeline
{
    /**
     * @var array<array{middleware: Psr15MiddlewareInterface|MiddlewareInterface, priority: int}>
     */
    private array $stack = [];

    private bool $sorted = true;

    /**
     * @param array<Psr15MiddlewareInterface|MiddlewareInterface|object> $middleware
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
     * Accepts:
     *  - {@see Psr15MiddlewareInterface} (new PSR-15 style)
     *  - {@see MiddlewareInterface} (legacy callable $next style)
     *  - Any object with a `process()` method (auto-adapted to legacy interface)
     *
     * @param int $priority  Higher = runs earlier (default 0)
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
     * @param callable               $finalHandler  The route handler (callable style)
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, callable $finalHandler): ResponseInterface
    {
        $this->sort();

        $handler = new CallableHandlerAdapter($finalHandler);

        // Build the handler chain from inside out
        foreach (array_reverse($this->stack) as $entry) {
            $mw = $entry['middleware'];

            if ($mw instanceof Psr15MiddlewareInterface) {
                // New PSR-15 middleware — pass RequestHandlerInterface
                $handler = new class($mw, $handler) implements RequestHandlerInterface {
                    public function __construct(
                        private readonly Psr15MiddlewareInterface $middleware,
                        private readonly RequestHandlerInterface $next,
                    ) {}

                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return $this->middleware->process($request, $this->next);
                    }
                };
            } else {
                // Legacy MiddlewareInterface — pass callable $next
                $handler = new class($mw, $handler) implements RequestHandlerInterface {
                    public function __construct(
                        private readonly MiddlewareInterface $middleware,
                        private readonly RequestHandlerInterface $next,
                    ) {}

                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return $this->middleware->process(
                            $request,
                            fn(ServerRequestInterface $req) => $this->next->handle($req)
                        );
                    }
                };
            }
        }

        return $handler->handle($request);
    }

    /**
     * Create a new pipeline from an array of middleware.
     */
    public static function from(array $middleware): self
    {
        return new self($middleware);
    }

    /**
     * Adapt objects into one of the accepted interfaces.
     */
    private function adapt(object $middleware): Psr15MiddlewareInterface|MiddlewareInterface
    {
        // Already implements PSR-15 interface
        if ($middleware instanceof Psr15MiddlewareInterface) {
            return $middleware;
        }

        // Already implements legacy interface
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        // Check for a process() method on plain objects → wrap as legacy
        if (method_exists($middleware, 'process')) {
            return new LegacyMiddlewareAdapter($middleware);
        }

        throw new \InvalidArgumentException(
            sprintf(
                'Middleware must implement %s or %s, or have a process() method. Got: %s',
                Psr15MiddlewareInterface::class,
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