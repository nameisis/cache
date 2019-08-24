<?php declare(strict_types = 1);

namespace Nameisis\Cache\Provider;

use Predis\Client;
use Predis\ClientInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class PredisAdapter implements CacheAdapter
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
    public function getCacheItemPool(): CacheItemPoolInterface
    {
        return new RedisAdapter($this->client, '', 0);
    }
}
