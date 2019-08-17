<?php declare(strict_types = 1);

namespace Nameisis\Cache\Provider;

use Symfony\Component\Cache\Adapter\AbstractAdapter;

interface ProviderInterface
{
    public function getAdapter(): AbstractAdapter;
}
