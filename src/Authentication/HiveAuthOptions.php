<?php

declare(strict_types=1);

namespace HiveCpq\Client\Authentication;

use InvalidArgumentException;

class HiveAuthOptions
{
    public string $authDomain = 'authenticate.hivecpq.com';
    public string $clientId = 'jjrtN9BvYMnwTSOrYb6fobNzqQP5MnFU';
    public string $realm = 'Username-Password-Authentication';
    public ?string $username = null;
    public ?string $password = null;
    public string $redirectUri = 'https://app.hivecpq.com';
    public int $tokenRefreshBuffer = 3600;

    public function validate(): void
    {
        if (empty($this->authDomain)) {
            throw new InvalidArgumentException('authDomain is required.');
        }
        if (empty($this->clientId)) {
            throw new InvalidArgumentException('clientId is required.');
        }
        if (empty($this->username)) {
            throw new InvalidArgumentException('username is required.');
        }
        if (empty($this->password)) {
            throw new InvalidArgumentException('password is required.');
        }
    }
}
