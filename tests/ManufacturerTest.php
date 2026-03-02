<?php

declare(strict_types=1);

namespace HiveCpq\Client\Tests;

use PHPUnit\Framework\TestCase;

class ManufacturerTest extends TestCase
{
    protected function setUp(): void
    {
        $skipReason = TestFixture::getSkipReason();
        if ($skipReason !== null) {
            $this->markTestSkipped($skipReason);
        }
    }

    public function testGetManufacturers(): void
    {
        $client = TestFixture::getClient();
        $result = $client->manufacturers()->getManufacturersList();

        $this->assertNotNull($result);
        $this->assertNotEmpty($result->getItems());

        $this->assertNotNull($result);
    }
}
