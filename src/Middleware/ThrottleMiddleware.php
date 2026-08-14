<?php

declare(strict_types=1);

namespace HiveCpq\Client\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class ThrottleMiddleware
{
    public static function create(float $maxRequestsPerSecond, ?LoggerInterface $logger = null): callable
    {
        $interval = $maxRequestsPerSecond > 0 ? 1.0 / $maxRequestsPerSecond : 0.0;
        $nextSlot = 0.0;

        return function (callable $handler) use ($interval, &$nextSlot, $logger): callable {
            return function (RequestInterface $request, array $options) use ($handler, $interval, &$nextSlot, $logger): PromiseInterface {
                if ($interval > 0.0) {
                    $now = microtime(true);

                    if ($nextSlot > $now) {
                        $wait = $nextSlot - $now;
                        $logger?->debug('[Throttle] {method} {uri} wacht {wait}ms', [
                            'method' => $request->getMethod(),
                            'uri' => (string) $request->getUri(),
                            'wait' => (int) round($wait * 1000),
                        ]);
                        usleep((int) round($wait * 1_000_000));
                        $nextSlot += $interval;
                    } else {
                        $nextSlot = $now + $interval;
                    }
                }

                return $handler($request, $options);
            };
        };
    }
}
