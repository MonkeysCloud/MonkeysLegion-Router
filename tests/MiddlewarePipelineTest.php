<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Tests;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Stream;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Router\Middleware\CallableHandlerAdapter;
use MonkeysLegion\Router\Middleware\CorsMiddleware;
use MonkeysLegion\Router\Middleware\LegacyMiddlewareAdapter;
use MonkeysLegion\Router\Middleware\MiddlewareInterface;
use MonkeysLegion\Router\Middleware\MiddlewarePipeline;
use MonkeysLegion\Router\Middleware\RequestHandlerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MiddlewarePipelineTest extends TestCase
{
    private function makeRequest(string $method = 'GET', string $path = '/'): ServerRequestInterface
    {
        return new ServerRequest($method, new Uri('http://localhost' . $path), Stream::createFromString(''));
    }

    private function makeResponse(string $body = 'ok', int $status = 200): ResponseInterface
    {
        return new Response(Stream::createFromString($body), $status);
    }

    // ─── RequestHandlerInterface ─────────────────────────────────────

    public function testCallableHandlerAdapterWrapsCallable(): void
    {
        $adapter = new CallableHandlerAdapter(fn($req) => $this->makeResponse('adapted'));
        $response = $adapter->handle($this->makeRequest());

        $this->assertEquals('adapted', (string)$response->getBody());
    }

    // ─── PSR-15 aligned MiddlewareInterface ──────────────────────────

    public function testNewMiddlewareInterfaceReceivesHandler(): void
    {
        $mw = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);
                return $response->withHeader('X-Touched', 'yes');
            }
        };

        $pipeline = new MiddlewarePipeline([$mw]);
        $response = $pipeline->process(
            $this->makeRequest(),
            fn($req) => (new Response(Stream::createFromString('final'), 200))
        );

        $this->assertEquals('yes', $response->getHeaderLine('X-Touched'));
        $this->assertEquals('final', (string)$response->getBody());
    }

    // ─── Legacy middleware backward compatibility ─────────────────────

    public function testLegacyMiddlewareAdapterWrapsCallableNext(): void
    {
        // Create a legacy middleware (v2.0 style: callable $next)
        $legacy = new class {
            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                $response = $next($request);
                return $response->withHeader('X-Legacy', 'yes');
            }
        };

        $adapter = new LegacyMiddlewareAdapter($legacy);
        $this->assertInstanceOf(MiddlewareInterface::class, $adapter);

        $pipeline = new MiddlewarePipeline([$adapter]);
        $response = $pipeline->process(
            $this->makeRequest(),
            fn($req) => (new Response(Stream::createFromString('ok'), 200))
        );

        $this->assertEquals('yes', $response->getHeaderLine('X-Legacy'));
    }

    public function testPipelineAutoAdaptsLegacyMiddleware(): void
    {
        $legacy = new class {
            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                $response = $next($request);
                return $response->withHeader('X-AutoAdapted', 'true');
            }
        };

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($legacy); // Should auto-adapt

        $response = $pipeline->process(
            $this->makeRequest(),
            fn($req) => (new Response(Stream::createFromString('ok'), 200))
        );

        $this->assertEquals('true', $response->getHeaderLine('X-AutoAdapted'));
    }

    // ─── Priority ordering ───────────────────────────────────────────

    public function testMiddlewarePriorityOrdering(): void
    {
        // Use headers to track execution order since anonymous classes
        // cannot accept by-reference arrays in PHP 8.4
        $mw1 = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);
                $existing = $response->getHeaderLine('X-Order');
                return $response->withHeader('X-Order', $existing . ',mw1');
            }
        };

        $mw2 = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);
                $existing = $response->getHeaderLine('X-Order');
                return $response->withHeader('X-Order', $existing . ',mw2');
            }
        };

        $mw3 = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);
                $existing = $response->getHeaderLine('X-Order');
                return $response->withHeader('X-Order', $existing . ',mw3');
            }
        };

        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($mw1, 10);  // middle priority
        $pipeline->pipe($mw2, 5);   // lowest
        $pipeline->pipe($mw3, 20);  // highest = runs first (outermost)

        $response = $pipeline->process(
            $this->makeRequest(),
            fn($req) => (new Response(Stream::createFromString('ok'), 200))
        );

        // Middleware wraps inside-out: mw3 (highest) is outermost,
        // so its header append happens LAST (after mw1 and mw2).
        // Inner execution order: mw2 → mw1 → mw3
        $order = ltrim($response->getHeaderLine('X-Order'), ',');
        $parts = explode(',', $order);
        $this->assertCount(3, $parts);
        // All three middlewares executed
        $this->assertContains('mw1', $parts);
        $this->assertContains('mw2', $parts);
        $this->assertContains('mw3', $parts);
    }

    // ─── Empty pipeline ──────────────────────────────────────────────

    public function testEmptyPipelineCallsFinalHandler(): void
    {
        $pipeline = new MiddlewarePipeline();
        $response = $pipeline->process(
            $this->makeRequest(),
            fn($req) => (new Response(Stream::createFromString('direct'), 200))
        );

        $this->assertEquals('direct', (string)$response->getBody());
    }

    // ─── CorsMiddleware PSR-15 ───────────────────────────────────────

    public function testCorsMiddlewareImplementsNewInterface(): void
    {
        $cors = new CorsMiddleware();
        $this->assertInstanceOf(MiddlewareInterface::class, $cors);
    }

    public function testCorsMiddlewareAddHeaders(): void
    {
        $cors = new CorsMiddleware([
            'allowed_origins' => ['http://example.com'],
        ]);

        $pipeline = new MiddlewarePipeline([$cors]);
        $request = new ServerRequest('GET', new Uri('http://localhost/test'), Stream::createFromString(''));
        $request = $request->withHeader('Origin', 'http://example.com');

        $response = $pipeline->process(
            $request,
            fn($req) => (new Response(Stream::createFromString('ok'), 200))
        );

        $this->assertEquals('http://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testCorsPreflightReturns204(): void
    {
        $cors = new CorsMiddleware();

        $pipeline = new MiddlewarePipeline([$cors]);
        $request = new ServerRequest('OPTIONS', new Uri('http://localhost/test'), Stream::createFromString(''));

        $response = $pipeline->process(
            $request,
            fn($req) => (new Response(Stream::createFromString('should not reach'), 200))
        );

        $this->assertEquals(204, $response->getStatusCode());
    }

    // ─── Fluent builder ──────────────────────────────────────────────

    public function testPipeMethodIsFluent(): void
    {
        $mw = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $pipeline = new MiddlewarePipeline();
        $result = $pipeline->pipe($mw);

        $this->assertSame($pipeline, $result);
    }

    // ─── Invalid middleware ──────────────────────────────────────────

    public function testInvalidMiddlewareThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $invalid = new \stdClass();
        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe($invalid);
    }
}
