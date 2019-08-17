<?php declare(strict_types = 1);

namespace Nameisis\Cache\Provider;

use Psr\Cache\CacheItemPoolInterface;

interface ProviderInterface
{
    public function getAdapter(): CacheItemPoolInterface;
}
