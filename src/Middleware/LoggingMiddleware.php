<?php

declare(strict_types=1);

namespace HiveCpq\Client\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class LoggingMiddleware
{
    private const REDACTED_HEADERS = ['Authorization', 'X-Api-Key'];

    public static function create(LoggerInterface $logger): callable
    {
        return function (callable $handler) use ($logger): callable {
            return function (RequestInterface $request, array $options) use ($handler, $logger): PromiseInterface {
                $requestId = substr(bin2hex(random_bytes(4)), 0, 8);
                $start = microtime(true);

                $logger->info("[{requestId}] --> {method} {uri}", [
                    'requestId' => $requestId,
                    'method' => $request->getMethod(),
                    'uri' => $request->getUri(),
                ]);

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($logger, $requestId, $request, $start): ResponseInterface {
                        $duration = round((microtime(true) - $start) * 1000, 1);
                        $level = $response->getStatusCode() >= 400 ? 'warning' : 'info';

                        $logger->$level("[{requestId}] <-- {method} {uri} {status} in {duration}ms", [
                            'requestId' => $requestId,
                            'method' => $request->getMethod(),
                            'uri' => $request->getUri(),
                            'status' => $response->getStatusCode(),
                            'duration' => $duration,
                        ]);

                        return $response;
                    },
                    function (\Throwable $exception) use ($logger, $requestId, $request, $start): void {
                        $duration = round((microtime(true) - $start) * 1000, 1);

                        $logger->error("[{requestId}] <-- {method} {uri} FAILED after {duration}ms: {error}", [
                            'requestId' => $requestId,
                            'method' => $request->getMethod(),
                            'uri' => $request->getUri(),
                            'duration' => $duration,
                            'error' => $exception->getMessage(),
                        ]);

                        throw $exception;
                    },
                );
            };
        };
    }
}
