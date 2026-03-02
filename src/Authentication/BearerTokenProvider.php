<?php

declare(strict_types=1);

namespace HiveCpq\Client\Authentication;

use Psr\Http\Message\RequestInterface;

class BearerTokenProvider
{
    private readonly mixed $tokenProvider;

    public function __construct(string|callable $tokenOrProvider)
    {
        if (is_string($tokenOrProvider)) {
            $token = $tokenOrProvider;
            $this->tokenProvider = static fn(): string => $token;
        } else {
            $this->tokenProvider = $tokenOrProvider;
        }
    }

    public function __invoke(callable $handler): callable
    {
        $tokenProvider = $this->tokenProvider;

        return function (RequestInterface $request, array $options) use ($handler, $tokenProvider) {
            $token = ($tokenProvider)();
            $request = $request->withHeader('Authorization', "Bearer {$token}");
            return $handler($request, $options);
        };
    }
}
