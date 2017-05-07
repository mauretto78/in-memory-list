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
use InMemoryList\Infrastructure\Domain\Model\Exception\CreateCollectionFromEmptyArrayException;
use InMemoryList\Infrastructure\Domain\Model\ListCollectionFactory;
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
        $allowedDrivers = ['redis'];

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
                $this->repository = new ListRedisRepository(new Redis($parameters));
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
     * @param null $uuid
     * @param null $elementIdentificator
     * @return mixed|string
     */
    public function create(array $elements, $uuid = null, $elementIdentificator = null)
    {
        try
        {
            $factory = new ListCollectionFactory();
            $collection = $factory->create($elements, $uuid, $elementIdentificator);

            return $this->repository->create($collection);
        }
        catch (\Exception $exception){
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
     * @param $collectionUUId
     * @param $uuid
     *
     * @return mixed
     */
    public function deleteElement($collectionUUId, $uuid)
    {
        return $this->repository->deleteElement($collectionUUId, $uuid);
    }

    /**
     * @param $uuid
     *
     * @return mixed
     */
    public function findByUUid($uuid)
    {
        return $this->repository->findByUuid($uuid);
    }

    /**
     * @param $collectionUUId
     * @param $uuid
     *
     * @return mixed
     */
    public function findElement($collectionUUId, $uuid)
    {
        return $this->repository->findElement($collectionUUId, $uuid);
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
     * @return mixed
     */
    public function item($string)
    {
        return unserialize($string)->getBody();
    }
}
