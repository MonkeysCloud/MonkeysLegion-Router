<?php
declare(strict_types=1);

namespace MonkeysLegion\Router\Tests;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\Router\Router;
use MonkeysLegion\Router\RouteCollection;
use MonkeysLegion\Http\Message\ServerRequest;
use MonkeysLegion\Http\Message\Uri;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Http\Message\Stream;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Test PSR-3 logger integration for 404/405 routing events.
 *
 * Uses a spy logger to verify log level, message, and context keys
 * without requiring an actual logging backend.
 */
class RouterLoggingTest extends TestCase
{
    private Router $router;

    /** @var array<int, array{level: string, message: string, context: array}> */
    private array $logEntries = [];

    protected function setUp(): void
    {
        $this->router = new Router(new RouteCollection());
        $this->logEntries = [];

        // Spy logger — captures all log calls
        $spy = $this->createMock(LoggerInterface::class);
        $spy->method('notice')->willReturnCallback(
            fn(string $msg, array $ctx = []) => $this->logEntries[] = [
                'level' => LogLevel::NOTICE, 'message' => $msg, 'context' => $ctx,
            ]
        );
        $spy->method('warning')->willReturnCallback(
            fn(string $msg, array $ctx = []) => $this->logEntries[] = [
                'level' => LogLevel::WARNING, 'message' => $msg, 'context' => $ctx,
            ]
        );

        $this->router->setLogger($spy);
    }

    // ─── 404 Logging ─────────────────────────────────────────────────

    public function testNotFoundLogsNotice(): void
    {
        $req = new ServerRequest('GET', new Uri('http://localhost/nonexistent'), Stream::createFromString(''));
        $response = $this->router->dispatch($req);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertCount(1, $this->logEntries);
        $this->assertSame(LogLevel::NOTICE, $this->logEntries[0]['level']);
        $this->assertSame('Route not found', $this->logEntries[0]['message']);
        $this->assertSame('GET', $this->logEntries[0]['context']['method']);
        $this->assertSame('/nonexistent', $this->logEntries[0]['context']['path']);
        $this->assertArrayHasKey('host', $this->logEntries[0]['context']);
    }

    public function testNotFoundDoesNotLogSensitiveQueryParams(): void
    {
        $req = new ServerRequest(
            'GET',
            new Uri('http://localhost/missing?token=secret123&api_key=hunter2'),
            Stream::createFromString('')
        );
        $this->router->dispatch($req);

        $this->assertCount(1, $this->logEntries);
        $context = $this->logEntries[0]['context'];

        // Must NOT contain query string or full URI
        $this->assertArrayNotHasKey('uri', $context);
        $this->assertStringNotContainsString('secret123', implode(' ', array_map('strval', $context)));
        $this->assertStringNotContainsString('hunter2', implode(' ', array_map('strval', $context)));
    }

    public function testNotFoundLogsFiringBeforeCustomHandler(): void
    {
        $handlerCalled = false;
        $this->router->setNotFoundHandler(function () use (&$handlerCalled) {
            $handlerCalled = true;
            return new Response(Stream::createFromString('custom 404'), 404);
        });

        $req = new ServerRequest('GET', new Uri('http://localhost/nope'), Stream::createFromString(''));
        $response = $this->router->dispatch($req);

        // Logger fires even with custom handler
        $this->assertCount(1, $this->logEntries);
        $this->assertSame(LogLevel::NOTICE, $this->logEntries[0]['level']);
        $this->assertTrue($handlerCalled);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testNotFoundDefaultResponseIncludesPath(): void
    {
        $req = new ServerRequest('GET', new Uri('http://localhost/some/path'), Stream::createFromString(''));
        $response = $this->router->dispatch($req);

        $body = (string) $response->getBody();
        $this->assertStringContainsString('/some/path', $body);
        $this->assertStringContainsString('404 Not Found', $body);
    }

    // ─── 405 Logging ─────────────────────────────────────────────────

    public function testMethodNotAllowedLogsWarning(): void
    {
        $this->router->post('/api/users', function () {
            return new Response(Stream::createFromString('created'), 201);
        });

        $req = new ServerRequest('GET', new Uri('http://localhost/api/users'), Stream::createFromString(''));
        $response = $this->router->dispatch($req);

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertCount(1, $this->logEntries);
        $this->assertSame(LogLevel::WARNING, $this->logEntries[0]['level']);
        $this->assertSame('Method not allowed', $this->logEntries[0]['message']);
        $this->assertSame('GET', $this->logEntries[0]['context']['method']);
        $this->assertSame('/api/users', $this->logEntries[0]['context']['path']);
        $this->assertContains('POST', $this->logEntries[0]['context']['allowed_methods']);
        $this->assertArrayHasKey('host', $this->logEntries[0]['context']);
    }

    public function testMethodNotAllowedDoesNotLogSensitiveData(): void
    {
        $this->router->post('/submit', function () {
            return new Response(Stream::createFromString('ok'));
        });

        $req = new ServerRequest(
            'GET',
            new Uri('http://user:pass@localhost/submit?secret=abc'),
            Stream::createFromString('')
        );
        $this->router->dispatch($req);

        $this->assertCount(1, $this->logEntries);
        $context = $this->logEntries[0]['context'];
        $this->assertArrayNotHasKey('uri', $context);

        $flatValues = implode(' ', array_map('strval', array_filter($context, 'is_string')));
        $this->assertStringNotContainsString('secret', $flatValues);
        $this->assertStringNotContainsString('pass', $flatValues);
    }

    public function testMethodNotAllowedDefaultResponseShowsDetails(): void
    {
        $this->router->post('/api/data', function () {
            return new Response(Stream::createFromString('ok'));
        });

        $req = new ServerRequest('GET', new Uri('http://localhost/api/data'), Stream::createFromString(''));
        $response = $this->router->dispatch($req);

        $body = (string) $response->getBody();
        $this->assertStringContainsString('405 Method Not Allowed', $body);
        $this->assertStringContainsString('GET', $body);
        $this->assertStringContainsString('/api/data', $body);
        $this->assertStringContainsString('POST', $body);
    }

    public function testMethodNotAllowedLogsFiringBeforeCustomHandler(): void
    {
        $this->router->put('/items', function () {
            return new Response(Stream::createFromString('ok'));
        });

        $handlerCalled = false;
        $this->router->setMethodNotAllowedHandler(function ($req, $methods) use (&$handlerCalled) {
            $handlerCalled = true;
            return new Response(Stream::createFromString('custom 405'), 405);
        });

        $req = new ServerRequest('GET', new Uri('http://localhost/items'), Stream::createFromString(''));
        $response = $this->router->dispatch($req);

        $this->assertCount(1, $this->logEntries);
        $this->assertSame(LogLevel::WARNING, $this->logEntries[0]['level']);
        $this->assertTrue($handlerCalled);
        $this->assertEquals(405, $response->getStatusCode());
    }

    // ─── No logging without logger ───────────────────────────────────

    public function testNoLoggingWithoutLogger(): void
    {
        // Fresh router without setLogger()
        $router = new Router(new RouteCollection());

        $req = new ServerRequest('GET', new Uri('http://localhost/nope'), Stream::createFromString(''));
        $response = $router->dispatch($req);

        // Should still return 404 normally
        $this->assertEquals(404, $response->getStatusCode());
        // No entries in our spy (it wasn't attached to this router)
        $this->assertCount(0, $this->logEntries);
    }
}
