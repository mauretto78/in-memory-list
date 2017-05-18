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

class ApcuDriver implements DriverInterface
{

    /**
     * @return bool
     */
    public function check()
    {
        // TODO: Implement check() method.
    }

    public function clear()
    {
        // TODO: Implement clear() method.
    }

    public function connect()
    {
        // TODO: Implement connect() method.
    }
}