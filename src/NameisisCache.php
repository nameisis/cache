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
    public const CACHE_HEADER = 'NameisisCache';

    /**
     * @var string
     */
    public const INVALIDATE_CACHE = 'invalidate';

    /**
     * @var string
     */
    public const SKIP_CACHE = 'skip';

    /**
     * @return null|ExtensionInterface
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ?? new NameisisCacheExtension();
    }
}
