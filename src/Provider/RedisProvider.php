<?php declare(strict_types = 1);

namespace Nameisis\Cache\Provider;

use Predis\Client;
use Predis\ClientInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class RedisProvider implements ProviderInterface
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
     * @return AbstractAdapter
     */
    public function getAdapter(): AbstractAdapter
    {
        return new RedisAdapter($this->client, '', 0);
    }
}
