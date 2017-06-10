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
use InMemoryList\Tests\BaseTestCase;

class MemcachedDriverTest extends BaseTestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function it_should_return_Memcached_instance_if_empty_config_array_is_provided()
    {
        $driver = new MemcachedDriver([]);
        $instance = $driver->getInstance();
        $driver->clear();

        $this->assertInstanceOf(\Memcached::class, $instance);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Drivers\Exceptions\MemcachedMalformedConfigException
     * @expectedExceptionMessage Malformed Memcached config parameters provided.
     */
    public function it_throws_RedisMalformedConfigException_if_malformed_config_array_is_provided()
    {
        $badConfigArray = [
            [
                'wrong' => 'param',
                'wrong2' => 'param2',
                'wrong3' => 'param3',
            ],
        ];

        new MemcachedDriver($badConfigArray);
    }

    /**
     * @test
     */
    public function it_should_return_Memcached_instance_if_correct_config_array_is_provided()
    {
        $driver = new MemcachedDriver($this->memcached_parameters);
        $instance = $driver->getInstance();
        $driver->clear();

        $this->assertInstanceOf(\Memcached::class, $instance);
    }
}
