<?php

declare(strict_types=1);

namespace HiveCpq\Client\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HiveCpq\Client\Middleware\RetryMiddleware;
use PHPUnit\Framework\TestCase;

class RetryMiddlewareTest extends TestCase
{
    /** @param list<Response|ConnectException> $queue */
    private function clientFor(array $queue, int $maxRetries = 3, float $maxDelay = 60.0): array
    {
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);
        $stack->push(RetryMiddleware::create($maxRetries, 0.001, null, $maxDelay));

        $attempts = 0;
        $stack->push(function (callable $handler) use (&$attempts): callable {
            return function ($request, $options) use ($handler, &$attempts) {
                ++$attempts;

                return $handler($request, $options);
            };
        });

        return [new Client(['handler' => $stack]), function () use (&$attempts): int { return $attempts; }];
    }

    public function testRateLimitedGetIsRetried(): void
    {
        [$client, $attempts] = $this->clientFor([
            new Response(429, ['Retry-After' => '0']),
            new Response(200),
        ]);

        $response = $client->get('https://example.test/things');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $attempts());
    }

    public function testRateLimitedPostIsRetriedBecauseItWasNeverProcessed(): void
    {
        [$client, $attempts] = $this->clientFor([
            new Response(429, ['Retry-After' => '0']),
            new Response(201),
        ]);

        $response = $client->post('https://example.test/companies');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(2, $attempts());
    }

    public function testServerErrorOnPostIsNotRetried(): void
    {
        [$client, $attempts] = $this->clientFor([
            new Response(503),
            new Response(201),
        ]);

        $response = $client->post('https://example.test/companies', ['http_errors' => false]);

        $this->assertSame(503, $response->getStatusCode(), 'a create must not be replayed blindly');
        $this->assertSame(1, $attempts());
    }

    public function testServerErrorOnPutIsRetried(): void
    {
        [$client, $attempts] = $this->clientFor([
            new Response(503),
            new Response(200),
        ]);

        $response = $client->put('https://example.test/companies/1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $attempts());
    }

    public function testServerErrorOnBulkUpsertIsRetried(): void
    {
        [$client, $attempts] = $this->clientFor([
            new Response(503),
            new Response(200),
        ]);

        $response = $client->post('https://example.test/customObjects/bcColour/bulkUpsert');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $attempts());
    }

    public function testUnsafeHeaderOptsAPostIntoReplay(): void
    {
        [$client, $attempts] = $this->clientFor([
            new Response(503),
            new Response(200),
        ]);

        $response = $client->post('https://example.test/oauth/token', [
            'headers' => [RetryMiddleware::RETRY_UNSAFE_HEADER => '1'],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $attempts());
    }

    public function testConnectionErrorOnPostIsNotRetried(): void
    {
        [$client, $attempts] = $this->clientFor([
            new ConnectException('boom', new Request('POST', 'https://example.test/companies')),
            new Response(201),
        ]);

        $this->expectException(ConnectException::class);

        try {
            $client->post('https://example.test/companies');
        } finally {
            $this->assertSame(1, $attempts());
        }
    }

    public function testConnectionErrorOnCheckConfigurationIsRetried(): void
    {
        $uri = 'https://example.test/manufacturers/m/configurations/c/checkConfiguration';

        [$client, $attempts] = $this->clientFor([
            new ConnectException('timed out', new Request('POST', $uri)),
            new Response(204),
        ]);

        $response = $client->post($uri);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(2, $attempts());
    }

    public function testConnectionErrorOnAddConfigurationIsNotRetried(): void
    {
        $uri = 'https://example.test/manufacturers/m/projects/p/addConfiguration';

        [$client, $attempts] = $this->clientFor([
            new ConnectException('timed out', new Request('POST', $uri)),
            new Response(201),
        ]);

        $this->expectException(ConnectException::class);

        try {
            $client->post($uri);
        } finally {
            $this->assertSame(1, $attempts());
        }
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        [$client, $attempts] = $this->clientFor([
            new Response(429, ['Retry-After' => '0']),
            new Response(429, ['Retry-After' => '0']),
            new Response(429, ['Retry-After' => '0']),
            new Response(429, ['Retry-After' => '0']),
        ], maxRetries: 2);

        $response = $client->get('https://example.test/things', ['http_errors' => false]);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame(3, $attempts(), 'the original call plus two retries');
    }

    public function testOversizedRetryAfterIsCapped(): void
    {
        [$client] = $this->clientFor([
            new Response(429, ['Retry-After' => '3600']),
            new Response(200),
        ], maxRetries: 3, maxDelay: 0.05);

        $start = microtime(true);
        $response = $client->get('https://example.test/things');
        $elapsed = microtime(true) - $start;

        $this->assertSame(200, $response->getStatusCode());
        $this->assertLessThan(2.0, $elapsed, 'an hour-long Retry-After must not block the worker');
    }

    public function testHttpDateRetryAfterIsUnderstood(): void
    {
        [$client, $attempts] = $this->clientFor([
            new Response(429, ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 1)]),
            new Response(200),
        ], maxRetries: 3, maxDelay: 0.05);

        $response = $client->get('https://example.test/things');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $attempts());
    }
}
