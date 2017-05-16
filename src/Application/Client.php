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
     * @param null  $elementUniqueIdentificator
     * @param null  $ttl
     *
     * @return mixed|string
     */
    public function create(array $elements, array $headers = [], $uuid = null, $elementUniqueIdentificator = null, $ttl = null)
    {
        try {
            $factory = new ListCollectionFactory();
            $list = $factory->create($elements, $headers, $uuid, $elementUniqueIdentificator);

            return $this->repository->create($list, $ttl);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * @param $uuid
     */
    public function deleteList($uuid)
    {
        $this->repository->deleteList($uuid);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        return $this->repository->deleteElement($listUuid, $elementUuid);
    }

    /**
     * @param $listUuid
     * @return bool
     */
    public function existsList($listUuid)
    {
        return $this->repository->existsList($listUuid);
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        return $this->repository->findListByUuid($listUuid);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        $this->repository->flush();
    }

    /**
     * @return mixed
     */
    public function getAll()
    {
        return $this->repository->all();
    }

    /**
     * @return mixed
     */
    public function getHeaders($listUuid)
    {
        return $this->repository->getHeaders($listUuid);
    }

    /**
     * @param $completeListElementUuid
     * @return mixed
     */
    public function getItem($completeListElementUuid)
    {
        $listElement = $this->repository->findElementByCompleteCollectionElementUuid($completeListElementUuid);

        return unserialize($listElement)->getBody();
    }

    /**
     * @return mixed
     */
    public function getStats()
    {
        return $this->repository->stats();
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getTtl($listUuid)
    {
        return $this->repository->ttl($listUuid);
    }

    /**
     * @param $listUuid
     * @param bool $ttl
     *
     * @return mixed
     */
    public function updateTtl($listUuid, $ttl = false)
    {
        return $this->repository->updateTtl($listUuid, $ttl);
    }
}
