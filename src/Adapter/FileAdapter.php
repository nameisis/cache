<?php declare(strict_types = 1);

namespace Nameisis\Cache\Adapter;

use Nameisis\Cache\DependencyInjection\NameisisCacheExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Exception\CacheException;

class FileAdapter implements CacheAdapter
{
    /**
     * @return CacheItemPoolInterface
     * @throws CacheException
     */
    public function getCacheItemPool(): CacheItemPoolInterface
    {
        return new PhpFilesAdapter(NameisisCacheExtension::ALIAS, 0);
    }
}
