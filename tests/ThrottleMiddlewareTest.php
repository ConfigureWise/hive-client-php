<?php

declare(strict_types=1);

namespace HiveCpq\Client\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use HiveCpq\Client\Middleware\ThrottleMiddleware;
use PHPUnit\Framework\TestCase;

class ThrottleMiddlewareTest extends TestCase
{
    private function clientAt(float $requestsPerSecond, int $responses): Client
    {
        $mock = new MockHandler(array_fill(0, $responses, new Response(200)));
        $stack = HandlerStack::create($mock);
        $stack->push(ThrottleMiddleware::create($requestsPerSecond));

        return new Client(['handler' => $stack]);
    }

    public function testRequestsArePacedAtTheConfiguredRate(): void
    {
        $client = $this->clientAt(20.0, 5);

        $start = microtime(true);
        for ($i = 0; $i < 5; ++$i) {
            $client->get('https://example.test/things');
        }
        $elapsed = microtime(true) - $start;

        // Five requests at 20/s means four gaps of 50ms.
        $this->assertGreaterThan(0.15, $elapsed, 'the throttle did not slow anything down');
        $this->assertLessThan(1.0, $elapsed, 'the throttle slowed things down far too much');
    }

    public function testZeroDisablesThrottling(): void
    {
        $client = $this->clientAt(0.0, 20);

        $start = microtime(true);
        for ($i = 0; $i < 20; ++$i) {
            $client->get('https://example.test/things');
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.2, $elapsed);
    }

    public function testFirstRequestIsNotDelayed(): void
    {
        $client = $this->clientAt(1.0, 1);

        $start = microtime(true);
        $client->get('https://example.test/things');
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.2, $elapsed, 'a cold throttle must not make the first call wait');
    }
}
