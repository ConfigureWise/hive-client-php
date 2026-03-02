<?php

declare(strict_types=1);

namespace HiveCpq\Client;

use InvalidArgumentException;

class HiveClientOptions
{
    public string $baseUrl = 'https://connect.hivecpq.com/api/v1';
    public ?string $bearerToken = null;
    /** @var callable(string): string|null */
    public mixed $tokenProvider = null;
    /** @var array<string, string> */
    public array $defaultHeaders = [];
    public string $userAgent = 'HiveCpq.Client.PHP/1.0.0';
    public ?string $correlationIdHeaderName = 'X-Correlation-ID';
    /** @var callable(): ?string|null */
    public mixed $correlationIdProvider = null;
    public int $timeout = 30;
    public int $maxRetries = 3;
    public float $retryDelay = 1.0;

    public function validate(): void
    {
        if (empty($this->baseUrl)) {
            throw new InvalidArgumentException('BaseUrl is required.');
        }

        if (empty($this->bearerToken) && $this->tokenProvider === null) {
            throw new InvalidArgumentException('Either bearerToken or tokenProvider must be configured.');
        }
    }
}
