<?php

declare(strict_types=1);

namespace HiveCpq\Client\Symfony;

use HiveCpq\Client\Authentication\ClientCredentialsOptions;
use HiveCpq\Client\Authentication\ClientCredentialsTokenProvider;
use HiveCpq\Client\Authentication\HiveAuthOptions;
use HiveCpq\Client\Authentication\HiveOAuth2TokenProvider;
use HiveCpq\Client\HiveClient;
use HiveCpq\Client\HiveClientOptions;
use Psr\Log\LoggerInterface;

class HiveClientFactory
{
    public static function create(array $config, ?LoggerInterface $logger = null): HiveClient
    {
        $options = new HiveClientOptions();
        $options->baseUrl = $config['base_url'];
        $options->timeout = $config['timeout'];
        $options->maxRetries = $config['max_retries'];
        $options->userAgent = $config['user_agent'];

        $auth = $config['auth'];

        match ($auth['type']) {
            'client_credentials' => self::applyClientCredentials($options, $auth),
            'oauth2' => self::applyOAuth2($options, $auth),
            'bearer_token' => $options->bearerToken = $auth['bearer_token'],
        };

        return new HiveClient($options, $logger);
    }

    private static function applyClientCredentials(HiveClientOptions $options, array $auth): void
    {
        $credentialsOptions = new ClientCredentialsOptions();
        $credentialsOptions->clientId = $auth['client_id'];
        $credentialsOptions->clientSecret = $auth['client_secret'];
        $credentialsOptions->authDomain = $auth['auth_domain'];
        $credentialsOptions->audience = $auth['audience'];

        $provider = new ClientCredentialsTokenProvider($credentialsOptions);
        $options->tokenProvider = $provider->getToken(...);
    }

    private static function applyOAuth2(HiveClientOptions $options, array $auth): void
    {
        $authOptions = new HiveAuthOptions();
        $authOptions->username = $auth['username'];
        $authOptions->password = $auth['password'];
        $authOptions->authDomain = $auth['auth_domain'];
        $authOptions->clientId = $auth['client_id'];

        $provider = new HiveOAuth2TokenProvider($authOptions);
        $options->tokenProvider = $provider->getToken(...);
    }
}
