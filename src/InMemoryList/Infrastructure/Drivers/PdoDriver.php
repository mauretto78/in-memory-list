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
use InMemoryList\Infrastructure\Drivers\Exceptions\PdoDriverCheckException;
use InMemoryList\Infrastructure\Drivers\Exceptions\PdoMalformedConfigException;

class PdoDriver implements DriverInterface
{
    /**
     * @var
     */
    private $config;

    /**
     * @var \PDO
     */
    private $instance;

    /**
     * RedisDriver constructor.
     *
     * @codeCoverageIgnore
     *
     * @param array $config
     *
     * @throws PdoDriverCheckException
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
        if (!$this->check()) {
            throw new PdoDriverCheckException('PDO extension is not loaded.');
        }

        $this->connect();
    }

    /**
     * @param $config
     *
     * @throws PdoMalformedConfigException
     */
    private function setConfig($config)
    {
        $allowedConfigKeys = [
            'driver',
            'host',
            'username',
            'password',
            'database',
            'port',
            'options',
            'charset',
        ];

        foreach ($config as $param => $server) {
            if (is_array($server)) {
                foreach (array_keys($server) as $key) {
                    if (!in_array($key, $allowedConfigKeys)) {
                        throw new PdoMalformedConfigException();
                    }
                }
            }

            if (!is_array($server) && !in_array($param, $allowedConfigKeys)) {
                throw new PdoMalformedConfigException();
            }
        }

        $this->config = $config;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return bool
     */
    public function check()
    {
        return extension_loaded('PDO');
    }

    /**
     * @return mixed
     */
    public function clear()
    {
    }

    /**
     * @return bool
     */
    public function connect()
    {
        $servers = $this->config ?: [];

        if (count($servers) === 1) {
            $servers = $servers[0];
        }

        $default = [
            'host' => '127.0.0.1',
            'port' => 3306,
            'username' => 'root',
            'password' => null,
            'driver' => 'mysql',
            'charset' => 'utf8',
            'database' => 'in-memory-list',
        ];

        $servers = array_merge($default, $servers);
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $servers['driver'],
            $servers['host'],
            $servers['port'] ?? '3306',
            $servers['database'],
            $servers['charset'] ?? 'utf8'
            );

        $this->instance = new \PDO($dsn, $servers['username'], $servers['password']);

        return true;
    }

    /**
     * @return mixed
     */
    public function getInstance()
    {
        return $this->instance;
    }
}
