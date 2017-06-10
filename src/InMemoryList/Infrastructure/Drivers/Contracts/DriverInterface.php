<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Infrastructure\Drivers\Contracts;

interface DriverInterface
{
    /**
     * @return bool
     */
    public function check();

    /**
     * @return mixed
     */
    public function clear();

    /**
     * @return bool
     */
    public function connect();

    /**
     * @return mixed
     */
    public function getInstance();
}
