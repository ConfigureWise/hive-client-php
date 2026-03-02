<?php

declare(strict_types=1);

namespace HiveCpq\Client\Tests;

use HiveCpq\Client\Authentication\ClientCredentialsOptions;
use HiveCpq\Client\Authentication\HiveAuthOptions;
use HiveCpq\Client\HiveClient;

class TestFixture
{
    private static ?HiveClient $client = null;
    private static ?string $manufacturerId = null;
    private static ?string $skipReason = null;

    public static function getClient(): HiveClient
    {
        self::initialize();

        if (self::$skipReason !== null) {
            throw new \RuntimeException(self::$skipReason);
        }

        return self::$client;
    }

    public static function getManufacturerId(): string
    {
        self::initialize();

        if (self::$skipReason !== null) {
            throw new \RuntimeException(self::$skipReason);
        }

        return self::$manufacturerId;
    }

    public static function getSkipReason(): ?string
    {
        self::initialize();
        return self::$skipReason;
    }

    private static function initialize(): void
    {
        if (self::$client !== null || self::$skipReason !== null) {
            return;
        }

        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->safeLoad();
        }

        try {
            self::$client = self::createClient();

            if (self::$client === null) {
                self::$skipReason = 'Missing credentials. Set HIVE_CLIENT_ID/HIVE_CLIENT_SECRET or HIVE_AUTH_USERNAME/HIVE_AUTH_PASSWORD.';
                return;
            }

            $manufacturers = self::$client->manufacturers()->getManufacturersList();
            $items = $manufacturers->getItems() ?? [];

            if (is_array($items) && count($items) > 0) {
                self::$manufacturerId = $items[0]->getId();
            }

            if (self::$manufacturerId === null) {
                self::$skipReason = 'No manufacturers found.';
            }
        } catch (\Throwable $e) {
            self::$skipReason = 'Failed to initialize: ' . $e->getMessage();
            self::$client = null;
        }
    }

    private static function createClient(): ?HiveClient
    {
        $clientId = $_ENV['HIVE_CLIENT_ID'] ?? getenv('HIVE_CLIENT_ID') ?: null;
        $clientSecret = $_ENV['HIVE_CLIENT_SECRET'] ?? getenv('HIVE_CLIENT_SECRET') ?: null;

        if (!empty($clientId) && !empty($clientSecret)) {
            $options = new ClientCredentialsOptions();
            $options->clientId = $clientId;
            $options->clientSecret = $clientSecret;
            return HiveClient::createWithClientCredentials($options);
        }

        $username = $_ENV['HIVE_AUTH_USERNAME'] ?? getenv('HIVE_AUTH_USERNAME') ?: null;
        $password = $_ENV['HIVE_AUTH_PASSWORD'] ?? getenv('HIVE_AUTH_PASSWORD') ?: null;

        if (!empty($username) && !empty($password)) {
            $authOptions = new HiveAuthOptions();
            $authOptions->username = $username;
            $authOptions->password = $password;
            return HiveClient::createWithOAuth2($authOptions);
        }

        return null;
    }
}
