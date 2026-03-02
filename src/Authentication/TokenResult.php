<?php

declare(strict_types=1);

namespace HiveCpq\Client\Authentication;

class TokenResult
{
    public function __construct(
        public readonly string $accessToken,
        public readonly int $expiresAt,
        public readonly ?string $idToken = null,
        public readonly string $tokenType = 'Bearer',
    ) {}
}
