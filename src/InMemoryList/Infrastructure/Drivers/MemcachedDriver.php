<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Infrastructure\Drivers;

use InMemoryList\Infrastructure\Drivers\Contracts\DriverInterface;
use InMemoryList\Infrastructure\Drivers\Exceptions\MemcachedDriverCheckException;
use InMemoryList\Infrastructure\Drivers\Exceptions\MemcachedDriverConnectionException;
use InMemoryList\Infrastructure\Drivers\Exceptions\MemcachedMalformedConfigException;
use Memcached;

class MemcachedDriver implements DriverInterface
{
    /**
     * @var
     */
    private $config;

    /**
     * @var \Memcached
     */
    private $instance;

    /**
     * MemcachedDriver constructor.
     *
     * @codeCoverageIgnore
     *
     * @param array $config
     *
     * @throws MemcachedDriverCheckException
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
        if (!$this->check()) {
            throw new MemcachedDriverCheckException('Memcached is not loaded.');
        }

        $this->connect();
    }

    /**
     * @param $config
     *
     * @throws MemcachedMalformedConfigException
     */
    private function setConfig($config)
    {
        $allowedConfigKeys = [
            'host',
            'port',
            'sasl_user',
            'sasl_password',
        ];

        foreach ($config as $key => $server) {
            (false === is_array($server)) ? $this->checkSimpleConfigArray($key, $allowedConfigKeys) : $this->checkMultidimensionalConfigArray($server, $allowedConfigKeys);
        }

        $this->config = $config;
    }

    /**
     * @param $key
     * @param $allowedConfigKeys
     *
     * @throws MemcachedMalformedConfigException
     */
    private function checkSimpleConfigArray($key, $allowedConfigKeys)
    {
        if (!in_array($key, $allowedConfigKeys)) {
            throw new MemcachedMalformedConfigException();
        }
    }

    /**
     * @param $server
     * @param $allowedConfigKeys
     */
    private function checkMultidimensionalConfigArray($server, $allowedConfigKeys)
    {
        foreach (array_keys($server) as $key) {
            $this->checkSimpleConfigArray($key, $allowedConfigKeys);
        }
    }

    /**
     * @codeCoverageIgnore
     *
     * @return bool
     */
    public function check()
    {
        return class_exists('\Memcached');
    }

    /**
     * @return bool
     */
    public function clear()
    {
        return $this->instance->flush();
    }

    public function connect()
    {
        $this->instance = new Memcached();
        $servers = $this->config ?: [];

        // if $servers is empty, connect with default parameters
        if (count($servers) < 1) {
            $servers = [
                [
                    'host' => '127.0.0.1',
                    'port' => 11211,
                ],
            ];
        }

        // if $servers is a monodimensional array convert to multidimensional
        if (!isset($servers[0])) {
            $servers = [
                [
                    'host' => $servers['host'],
                    'port' => $servers['port'],
                ],
            ];
        }

        // loop all servers
        foreach ($servers as $server) {
            if (!$this->instance->addServer($server['host'], $server['port'])) {
                throw new MemcachedDriverConnectionException('Memcached connection refused: [host'.$server['host'].' port'.$server['port'].']');
            }
        }
    }

    /**
     * @return mixed
     */
    public function getInstance()
    {
        return $this->instance;
    }
}
