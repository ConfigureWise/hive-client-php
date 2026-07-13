<?php

declare(strict_types=1);

namespace HiveCpq\Client\Authentication;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class BearerTokenProvider
{
    private const RETRIED_OPTION = 'hive_auth_retried';

    private readonly mixed $tokenProvider;
    private readonly mixed $onUnauthorized;

    public function __construct(string|callable $tokenOrProvider, ?callable $onUnauthorized = null)
    {
        if (is_string($tokenOrProvider)) {
            $token = $tokenOrProvider;
            $this->tokenProvider = static fn(): string => $token;
        } else {
            $this->tokenProvider = $tokenOrProvider;
        }

        $this->onUnauthorized = $onUnauthorized;
    }

    public function __invoke(callable $handler): callable
    {
        $tokenProvider = $this->tokenProvider;
        $onUnauthorized = $this->onUnauthorized;

        return function (RequestInterface $request, array $options) use ($handler, $tokenProvider, $onUnauthorized) {
            $token = ($tokenProvider)();
            $request = $request->withHeader('Authorization', "Bearer {$token}");

            $promise = $handler($request, $options);

            if ($onUnauthorized === null || !empty($options[self::RETRIED_OPTION])) {
                return $promise;
            }

            return $promise->then(
                function (ResponseInterface $response) use ($request, $options, $handler, $tokenProvider, $onUnauthorized) {
                    if ($response->getStatusCode() !== 401) {
                        return $response;
                    }

                    ($onUnauthorized)();

                    $options[self::RETRIED_OPTION] = true;
                    $freshToken = ($tokenProvider)();

                    if ($request->getBody()->isSeekable()) {
                        $request->getBody()->rewind();
                    }

                    $request = $request->withHeader('Authorization', "Bearer {$freshToken}");

                    return $handler($request, $options);
                }
            );
        };
    }
}
