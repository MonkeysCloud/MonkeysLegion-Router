<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware pipeline that processes a chain of middleware.
 */
class MiddlewarePipeline
{
    /**
     * @param array<MiddlewareInterface> $middleware
     */
    public function __construct(
        private array $middleware = []
    ) {}

    /**
     * Add middleware to the pipeline
     */
    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Process the request through the middleware pipeline
     */
    public function process(ServerRequestInterface $request, callable $finalHandler): ResponseInterface
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $middleware) => fn($req) => $middleware->process($req, $next),
            $finalHandler
        );

        return $pipeline($request);
    }

    /**
     * Create a new pipeline from an array of middleware
     */
    public static function from(array $middleware): self
    {
        return new self($middleware);
    }
}