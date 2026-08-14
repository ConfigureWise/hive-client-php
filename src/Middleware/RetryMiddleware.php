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
    /**
     * Opt a non-idempotent request into replay. Guzzle's retry decider never sees the
     * request options, so the opt-in travels as a header.
     */
    public const RETRY_UNSAFE_HEADER = 'X-Hive-Retry-Unsafe';

    private const RATE_LIMIT_STATUS = 429;
    private const RETRYABLE_STATUS_CODES = [408, 429, 500, 502, 503, 504];
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'TRACE'];

    /** Upserts are keyed by the caller, so replaying one cannot create a duplicate. */
    private const REPLAYABLE_PATH_SUFFIXES = ['/bulkUpsert', '/bulkDelete'];

    public static function create(int $maxRetries = 3, float $initialDelay = 1.0, ?LoggerInterface $logger = null, float $maxDelay = 30.0): callable
    {
        $decider = function (int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $exception = null) use ($maxRetries, $logger): bool {
            if ($retries >= $maxRetries) {
                return false;
            }

            // A 429 was rejected outright, never processed, so replaying it is always safe.
            if ($response && self::RATE_LIMIT_STATUS === $response->getStatusCode()) {
                $logger?->warning("[Retry] {method} {uri} rate limited, attempt {attempt}/{max}", [
                    'method' => $request->getMethod(),
                    'uri' => $request->getUri(),
                    'attempt' => $retries + 1,
                    'max' => $maxRetries,
                ]);
                return true;
            }

            // Anything else may already have been applied server-side before the response
            // was lost, so only replay it when the request is safe to repeat.
            if (!self::isReplayable($request)) {
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

        $delay = function (int $retries, ?ResponseInterface $response = null) use ($initialDelay, $maxDelay): int {
            $maxDelayMs = (int) ($maxDelay * 1000);

            if ($response && $response->hasHeader('Retry-After')) {
                $retryAfter = $response->getHeaderLine('Retry-After');
                if (is_numeric($retryAfter)) {
                    return min(self::withJitter((float) $retryAfter * 1000), $maxDelayMs);
                }
                $date = strtotime($retryAfter);
                if ($date !== false) {
                    return min(self::withJitter((float) max(0, ($date - time()) * 1000)), $maxDelayMs);
                }
            }

            $exponential = $initialDelay * (2 ** $retries) * 1000;
            return min(self::withJitter($exponential), $maxDelayMs);
        };

        return Middleware::retry($decider, $delay);
    }

    private static function isReplayable(RequestInterface $request): bool
    {
        if (in_array(strtoupper($request->getMethod()), self::IDEMPOTENT_METHODS, true)) {
            return true;
        }

        if ($request->hasHeader(self::RETRY_UNSAFE_HEADER)) {
            return true;
        }

        $path = $request->getUri()->getPath();

        foreach (self::REPLAYABLE_PATH_SUFFIXES as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /** Spreads concurrent workers so they do not all wake at the same instant. */
    private static function withJitter(float $milliseconds): int
    {
        return (int) ($milliseconds + ($milliseconds * 0.2 * (mt_rand() / mt_getrandmax())));
    }
}
