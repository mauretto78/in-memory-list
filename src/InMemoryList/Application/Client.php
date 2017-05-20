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

use InMemoryList\Application\Exceptions\NotSupportedDriverException;
use InMemoryList\Domain\Model\Contracts\ListRepository;
use InMemoryList\Infrastructure\Domain\Model\ListCollectionFactory;

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
        $allowedDrivers = [
            'apcu',
            'memcached',
            'redis'
        ];

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
        $repository = 'InMemoryList\Infrastructure\Persistance\\'.ucfirst($driver).'Repository';
        $driver = 'InMemoryList\Infrastructure\Drivers\\'.ucfirst($driver).'Driver';
        $instance = (new $driver($parameters))->getInstance();

        $this->repository = new $repository($instance);
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
    public function delete($uuid)
    {
        $this->repository->delete($uuid);
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
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        return $this->repository->findListByUuid($listUuid);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function findElement($listUuid, $elementUuid)
    {
        return $this->repository->findElement($listUuid, $elementUuid);
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
    public function getHeaders($listUuid)
    {
        return $this->repository->getHeaders($listUuid);
    }

    /**
     * @return mixed
     */
    public function getStatistics()
    {
        return $this->repository->getStatistics();
    }

    /**
     * @param $string
     *
     * @return mixed
     */
    public function item($string)
    {
        return unserialize($string);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, array $data = [])
    {
        return $this->repository->updateElement($listUuid, $elementUuid, $data);
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
