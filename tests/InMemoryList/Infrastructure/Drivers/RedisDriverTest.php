<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use InMemoryList\Infrastructure\Drivers\RedisDriver;
use InMemoryList\Tests\BaseTestCase;

class RedisDriverTest extends BaseTestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * @test
     * @expectedException \Predis\Connection\ConnectionException
     * @expectedExceptionMessage `AUTH` failed: ERR Client sent AUTH, but no password is set [tcp://127.0.0.1:6379]
     */
    public function it_throws_ConnectionException_if_wrong_config_array_is_provided()
    {
        $redis_parameters = [
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 15,
            'password' => 'non-existing-password',
        ];

        $driver = new RedisDriver($redis_parameters);
        $instance = $driver->getInstance();
        $driver->clear();

        $this->assertInstanceOf(\Predis\Client::class, $instance);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Drivers\Exceptions\RedisMalformedConfigException
     * @expectedExceptionMessage Malformed Redis config parameters provided.
     */
    public function it_throws_RedisMalformedConfigException_if_malformed_config_array_is_provided()
    {
        $badConfigArray = [
            'wrong' => 'param',
            'wrong2' => 'param2',
            'wrong3' => 'param3',
        ];

        new RedisDriver($badConfigArray);
    }

    /**
     * @test
     */
    public function it_should_return_PRedis_Client_instance_if_correct_config_array_is_provided()
    {
        $driver = new RedisDriver($this->redis_parameters);
        $instance = $driver->getInstance();
        $driver->clear();

        $this->assertInstanceOf(\Predis\Client::class, $instance);
    }
}
