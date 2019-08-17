<?php declare(strict_types = 1);

namespace Nameisis\Cache\Provider;

use Predis\Client;
use Predis\ClientInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class PredisProvider implements ProviderInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @return CacheItemPoolInterface
     */
    public function getAdapter(): CacheItemPoolInterface
    {
        return new RedisAdapter($this->client, '', 0);
    }
}
