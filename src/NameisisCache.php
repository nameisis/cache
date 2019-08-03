<?php declare(strict_types = 1);

namespace Nameisis\Cache;

use Nameisis\Cache\DependencyInjection\NameisisCacheExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class NameisisCache extends Bundle
{
    /**
     * @var string
     */
    public const CACHE_HEADER = 'N-CACHE';

    /**
     * @var string
     */
    public const DISABLE_CACHE = 'N-CACHE-DISABLE';

    /**
     * @return NameisisCacheExtension|ExtensionInterface|null
     */
    public function getContainerExtension()
    {
        return $this->extension ?? new NameisisCacheExtension();
    }
}
