<?php

declare(strict_types=1);

namespace HiveCpq\Client\Middleware;

use Psr\Http\Message\RequestInterface;

class DefaultHeadersMiddleware
{
    /**
     * @param array<string, string> $headers
     * @param callable(): ?string|null $correlationIdProvider
     */
    public static function create(
        array $headers = [],
        ?string $userAgent = null,
        ?string $correlationIdHeaderName = null,
        ?callable $correlationIdProvider = null,
    ): callable {
        return function (callable $handler) use ($headers, $userAgent, $correlationIdHeaderName, $correlationIdProvider): callable {
            return function (RequestInterface $request, array $options) use ($handler, $headers, $userAgent, $correlationIdHeaderName, $correlationIdProvider) {
                foreach ($headers as $name => $value) {
                    if (!$request->hasHeader($name)) {
                        $request = $request->withHeader($name, $value);
                    }
                }

                if ($userAgent !== null && !$request->hasHeader('User-Agent')) {
                    $request = $request->withHeader('User-Agent', $userAgent);
                }

                if ($correlationIdHeaderName !== null && $correlationIdProvider !== null && !$request->hasHeader($correlationIdHeaderName)) {
                    $correlationId = $correlationIdProvider();
                    if ($correlationId !== null) {
                        $request = $request->withHeader($correlationIdHeaderName, $correlationId);
                    }
                }

                return $handler($request, $options);
            };
        };
    }
}
