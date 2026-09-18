<?php

declare(strict_types=1);

namespace HiveCpq\Client\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use HiveCpq\Client\HiveClient;
use HiveCpq\Client\HiveClientOptions;
use PHPUnit\Framework\TestCase;

class HiveClientHandlerTest extends TestCase
{
    public function testRequestsGoThroughTheConfiguredHandler(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"items":[{"id":"m1","name":"Mock"}],"total":1}'),
        ]);

        $options = new HiveClientOptions();
        $options->bearerToken = 'token';
        $options->handler = $mock;

        $manufacturers = (new HiveClient($options))->manufacturers()->getManufacturersList();

        $this->assertSame('m1', $manufacturers['items'][0]['id']);
        $this->assertSame('Bearer token', $mock->getLastRequest()->getHeaderLine('Authorization'));
    }
}
