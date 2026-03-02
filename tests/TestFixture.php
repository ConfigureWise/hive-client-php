<?php

declare(strict_types=1);

namespace HiveCpq\Client\Tests;

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

        $username = $_ENV['HIVE_AUTH_USERNAME'] ?? getenv('HIVE_AUTH_USERNAME') ?: null;
        $password = $_ENV['HIVE_AUTH_PASSWORD'] ?? getenv('HIVE_AUTH_PASSWORD') ?: null;

        if (empty($username) || empty($password)) {
            self::$skipReason = 'Missing credentials. Set HIVE_AUTH_USERNAME and HIVE_AUTH_PASSWORD.';
            return;
        }

        try {
            $authOptions = new HiveAuthOptions();
            $authOptions->username = $username;
            $authOptions->password = $password;

            self::$client = HiveClient::createWithOAuth2($authOptions);

            $manufacturers = self::$client->manufacturers()->getManufacturersList();
            $items = $manufacturers->getItems() ?? [];

            if (is_array($items) && count($items) > 0) {
                foreach ($items as $manufacturer) {
                    $name = method_exists($manufacturer, 'getName') ? $manufacturer->getName() : '';
                    if (stripos($name, 'ConfigureWise') !== false || stripos($name, 'Configure Wise') !== false) {
                        self::$manufacturerId = method_exists($manufacturer, 'getId') ? $manufacturer->getId() : null;
                        break;
                    }
                }

                if (self::$manufacturerId === null) {
                    $first = $items[0];
                    self::$manufacturerId = method_exists($first, 'getId') ? $first->getId() : null;
                }
            }

            if (self::$manufacturerId === null) {
                self::$skipReason = 'No manufacturers found.';
            }
        } catch (\Throwable $e) {
            self::$skipReason = 'Failed to initialize: ' . $e->getMessage();
            self::$client = null;
        }
    }
}
