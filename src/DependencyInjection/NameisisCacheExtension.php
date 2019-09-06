<?php declare(strict_types = 1);

namespace Nameisis\Cache\DependencyInjection;

use Exception;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Vairogs\Utils\Iter;

class NameisisCacheExtension extends Extension
{
    /**
     * @var string
     */
    public const EXTENSION = 'nameisis.cache';

    /**
     * @var string
     */
    public const ALIAS = 'nameisis_cache';

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return self::ALIAS;
    }

    /**
     * @param array $configs
     * @param ContainerBuilder $container
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration($this->getAlias());
        $parameters = $this->processConfiguration($configuration, $configs);

        foreach (Iter::makeOneDimension($parameters, self::EXTENSION) as $key => $value) {
            $container->setParameter($key, $value);
        }
    }
}
