<?php

declare(strict_types=1);

namespace HiveCpq\Client\Authentication;

use InvalidArgumentException;

class ClientCredentialsOptions
{
    public string $authDomain = 'authenticate.hivecpq.com';
    public ?string $clientId = null;
    public ?string $clientSecret = null;
    public string $audience = 'https://ebusinesscloud.eu.auth0.com/api/v2/';
    public string $domain = 'https://ebusinesscloud.eu.auth0.com';
    public int $tokenRefreshBuffer = 3600;

    public function validate(): void
    {
        if (empty($this->clientId)) {
            throw new InvalidArgumentException('clientId is required.');
        }
        if (empty($this->clientSecret)) {
            throw new InvalidArgumentException('clientSecret is required.');
        }
    }
}
