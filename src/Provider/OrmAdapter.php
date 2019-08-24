<?php declare(strict_types = 1);

namespace Nameisis\Cache\Provider;

use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Nameisis\Cache\DependencyInjection\NameisisCacheExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use function sprintf;

class OrmAdapter implements CacheAdapter
{
    /**
     * @var EntityManagerInterface
     */
    private $manager;

    /**
     * @param EntityManagerInterface $manager
     */
    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @return CacheItemPoolInterface
     * @throws DBALException
     */
    public function getCacheItemPool(): CacheItemPoolInterface
    {
        $table = sprintf('%s_items', NameisisCacheExtension::ALIAS);
        $schema = $this->manager->getConnection()->getSchemaManager();
        $adapter = new PdoAdapter($this->manager->getConnection(), '', 0, ['db_table' => $table]);

        if (!$schema->tablesExist([$table])) {
            $adapter->createTable();
        }

        if ($schema->tablesExist([$table])) {
            return $adapter;
        }

        throw DBALException::invalidTableName($table);
    }
}
