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
use InMemoryList\Infrastructure\Drivers\Exceptions\ApcuDriverCheckException;

class ApcuDriver implements DriverInterface
{
    /**
     * ApcuDriver constructor.
     * @throws ApcuDriverCheckException
     */
    public function __construct()
    {
        if (!$this->check()) {
            throw new ApcuDriverCheckException('Apcu extension is not loaded.');
        }

        $this->connect();
    }

    /**
     * @return bool
     */
    public function check()
    {
        if (extension_loaded('apcu') && ini_get('apc.enabled')) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function clear()
    {
        return @apcu_clear_cache();
    }

    /**
     * @return bool
     */
    public function connect()
    {
        return true;
    }

    /**
     * @return mixed
     */
    public function getInstance()
    {
        return $this;
    }
}
