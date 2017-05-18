<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

use InMemoryList\Infrastructure\Drivers\MemcachedDriver;
use PHPUnit\Framework\TestCase;

class MemcachedDriverTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_return_Memcached_instance_if_empty_config_array_is_provided()
    {
        $memcached_params = [];

        $driver = new MemcachedDriver($memcached_params);
        $instance = $driver->getInstance();
        $driver->clear();

        $this->assertInstanceOf(\Memcached::class, $instance);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Drivers\Exceptions\MemcachedMalformedConfigException
     * @expectedExceptionMessage Malformed Memcached config params provided.
     */
    public function it_throws_RedisMalformedConfigException_if_malformed_config_array_is_provided()
    {
        $malformed_config_array = [
            [
                'wrong' => 'param',
                'wrong2' => 'param2',
                'wrong3' => 'param3',
            ]
        ];

        new MemcachedDriver($malformed_config_array);
    }


    /**
     * @test
     */
    public function it_should_return_Memcached_instance_if_correct_config_array_is_provided()
    {
        $memcached_params = [
            [
                'host' =>'127.0.0.1',
                'port' => 11211,
            ],
            [
                'host' =>'127.0.0.1',
                'port' => 11221,
            ],
        ];

        $driver = new MemcachedDriver($memcached_params);
        $instance = $driver->getInstance();
        $driver->clear();

        $this->assertInstanceOf(\Memcached::class, $instance);
    }
}