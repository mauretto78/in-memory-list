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

use InMemoryList\Application\Exceptions\MalformedParametersException;
use InMemoryList\Application\Exceptions\NotSupportedDriverException;
use InMemoryList\Domain\Model\Contracts\ListRepositoryInterface;
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Infrastructure\Domain\Model\ListCollectionFactory;

class Client
{
    /**
     * @var string
     */
    private $driver;

    /**
     * @var ListRepositoryInterface
     */
    private $repository;

    /**
     * Client constructor.
     *
     * @param string $driver
     * @param array  $parameters
     */
    public function __construct($driver = 'redis', array $parameters = [], $createSchema = false)
    {
        $this->setDriver($driver);
        $this->setRepository($driver, $parameters, $createSchema = false);
    }

    /**
     * @param $driver
     *
     * @throws NotSupportedDriverException
     */
    private function setDriver($driver)
    {
        $allowedDrivers = [
            'apcu',
            'memcached',
            'pdo',
            'redis',
        ];

        if (!in_array($driver, $allowedDrivers)) {
            throw new NotSupportedDriverException($driver.' is not a supported driver.');
        }

        $this->driver = $driver;
    }

    /**
     * @return string
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @param $driver
     * @param array $config
     */
    private function setRepository($driver, array $config = [])
    {
        $repository = 'InMemoryList\Infrastructure\Persistance\\'.ucfirst($driver).'Repository';
        $driver = 'InMemoryList\Infrastructure\Drivers\\'.ucfirst($driver).'Driver';
        $instance = (new $driver($config))->getInstance();

        $this->repository = new $repository($instance);
    }

    /**
     * @return ListRepositoryInterface
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @param array $elements
     * @param array $parameters
     *
     * @return mixed|string
     *
     * @throws MalformedParametersException
     */
    public function create(array $elements, array $parameters = [])
    {
        $this->validateParameters($parameters);
        $factory = new ListCollectionFactory();
        $list = $factory->create(
            $elements,
            (isset($parameters['headers'])) ? $parameters['headers'] : [],
            (isset($parameters['uuid'])) ? $parameters['uuid'] : null,
            (isset($parameters['element-uuid'])) ? $parameters['element-uuid'] : null
        );

        return $this->repository->create(
            $list,
            (isset($parameters['ttl'])) ? $parameters['ttl'] : null,
            (isset($parameters['chunk-size'])) ? $parameters['chunk-size'] : null
        );
    }

    /**
     * @param $parameters
     *
     * @throws MalformedParametersException
     */
    private function validateParameters($parameters)
    {
        $allowedParameters = [
            'chunk-size',
            'element-uuid',
            'headers',
            'ttl',
            'uuid',
        ];

        foreach (array_keys($parameters) as $key) {
            if (!in_array($key, $allowedParameters)) {
                throw new MalformedParametersException();
            }
        }
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     *
     * @return mixed
     */
    public function pushElement($listUuid, $elementUuid, array $data = [])
    {
        $newElement = new ListElement(
            new ListElementUuid($elementUuid),
            $data
        );

        return $this->repository->pushElement($listUuid, $newElement);
    }
}
