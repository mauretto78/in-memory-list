<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

use InMemoryList\Infrastructure\Drivers\ApcuDriver;
use PHPUnit\Framework\TestCase;

class ApcuDriverTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_return_ApcuDriver_instance()
    {
        $driver = new ApcuDriver();
        $instance = $driver->getInstance();
        $driver->clear();

        $this->assertInstanceOf(ApcuDriver::class, $instance);
    }
}
