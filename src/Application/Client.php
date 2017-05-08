<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
namespace InMemoryList\Application;

use InMemoryList\Application\Exception\NotSupportedDriverException;
use InMemoryList\Domain\Model\Contracts\ListRepository;
use InMemoryList\Infrastructure\Domain\Model\ListCollectionFactory;
use InMemoryList\Infrastructure\Persistance\ListMemcachedRepository;
use InMemoryList\Infrastructure\Persistance\ListRedisRepository;
use Predis\Client as Redis;

class Client
{
    /**
     * @var string
     */
    private $driver;

    /**
     * @var ListRepository
     */
    private $repository;

    /**
     * Client constructor.
     *
     * @param string $driver
     * @param array  $parameters
     */
    public function __construct($driver = 'redis', array $parameters = [])
    {
        $this->_setDriver($driver);
        $this->_setRepository($driver, $parameters);
    }

    /**
     * @param $driver
     *
     * @throws NotSupportedDriverException
     */
    private function _setDriver($driver)
    {
        $allowedDrivers = ['redis', 'memcached'];

        if (!in_array($driver, $allowedDrivers)) {
            throw new NotSupportedDriverException($driver.' is not a supported driver.');
        }

        $this->driver = $driver;
    }

    /**
     * @param $driver
     * @param array $parameters
     */
    private function _setRepository($driver, array $parameters = [])
    {
        switch ($driver) {
            case 'redis':
                $redis = new Redis($parameters);
                $this->repository = new ListRedisRepository($redis);
                break;

            case 'memcached':
                $memcached = new \Memcached();
                $memcached->addServers($parameters);
                $this->repository = new ListMemcachedRepository($memcached);
                break;
        }
    }

    /**
     * @return ListRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @param array $elements
     * @param null  $uuid
     * @param null  $elementIdentificator
     * @param null  $ttl
     *
     * @return mixed|string
     */
    public function create(array $elements, $uuid = null, $elementIdentificator = null, $ttl = null)
    {
        try {
            $factory = new ListCollectionFactory();
            $collection = $factory->create($elements, $uuid, $elementIdentificator);

            return $this->repository->create($collection, $ttl);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * @param $uuid
     */
    public function delete($uuid)
    {
        $this->repository->delete($uuid);
    }

    /**
     * @param $collectionUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function deleteElement($collectionUuid, $elementUuid)
    {
        return $this->repository->deleteElement($collectionUuid, $elementUuid);
    }

    /**
     * @param $collectionUuid
     *
     * @return mixed
     */
    public function findByUuid($collectionUuid)
    {
        return $this->repository->findByUuid($collectionUuid);
    }

    /**
     * @param $collectionUUId
     * @param $elementUuid
     *
     * @return mixed
     */
    public function findElement($collectionUUId, $elementUuid)
    {
        return $this->repository->findElement($collectionUUId, $elementUuid);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        $this->repository->flush();
    }

    /**
     * @param $string
     *
     * @return mixed
     */
    public function item($string)
    {
        return unserialize($string)->getBody();
    }
}
