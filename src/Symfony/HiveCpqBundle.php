<?php

declare(strict_types=1);

namespace HiveCpq\Client\Symfony;

use HiveCpq\Client\Symfony\DependencyInjection\HiveCpqExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HiveCpqBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new HiveCpqExtension();
    }
}
