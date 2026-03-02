<?php

declare(strict_types=1);

namespace HiveCpq\Client\Middleware;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class RetryMiddleware
{
    private const RETRYABLE_STATUS_CODES = [408, 429, 500, 502, 503, 504];

    public static function create(int $maxRetries = 3, float $initialDelay = 1.0, ?LoggerInterface $logger = null): callable
    {
        $decider = function (int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $exception = null) use ($maxRetries, $logger): bool {
            if ($retries >= $maxRetries) {
                return false;
            }

            if ($exception instanceof ConnectException) {
                $logger?->warning("[Retry] {method} {uri} connection error, attempt {attempt}/{max}", [
                    'method' => $request->getMethod(),
                    'uri' => $request->getUri(),
                    'attempt' => $retries + 1,
                    'max' => $maxRetries,
                ]);
                return true;
            }

            if ($response && in_array($response->getStatusCode(), self::RETRYABLE_STATUS_CODES, true)) {
                $logger?->warning("[Retry] {method} {uri} returned {status}, attempt {attempt}/{max}", [
                    'method' => $request->getMethod(),
                    'uri' => $request->getUri(),
                    'status' => $response->getStatusCode(),
                    'attempt' => $retries + 1,
                    'max' => $maxRetries,
                ]);
                return true;
            }

            return false;
        };

        $delay = function (int $retries, ?ResponseInterface $response = null) use ($initialDelay): int {
            if ($response && $response->hasHeader('Retry-After')) {
                $retryAfter = $response->getHeaderLine('Retry-After');
                if (is_numeric($retryAfter)) {
                    return (int) ($retryAfter * 1000);
                }
                $date = strtotime($retryAfter);
                if ($date !== false) {
                    return max(0, ($date - time()) * 1000);
                }
            }

            $exponential = $initialDelay * (2 ** $retries) * 1000;
            $jitter = $exponential * 0.2 * (mt_rand() / mt_getrandmax());
            return (int) ($exponential + $jitter);
        };

        return Middleware::retry($decider, $delay);
    }
}
