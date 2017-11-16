<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use InMemoryList\Infrastructure\Drivers\PdoDriver;
use InMemoryList\Tests\BaseTestCase;

class PdoDriverTest extends BaseTestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Drivers\Exceptions\PdoMalformedConfigException
     */
    public function it_throws_ConnectionException_if_wrong_config_array_is_provided()
    {
        $pdo_parameters = [
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 15,
            'password' => 'non-existing-password',
        ];

        $driver = new PdoDriver($pdo_parameters);
        $instance = $driver->getInstance();
        $driver->clear();

        $this->assertInstanceOf(\Pdo::class, $instance);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Drivers\Exceptions\PdoMalformedConfigException
     * @expectedExceptionMessage Malformed PDO config parameters provided.
     */
    public function it_throws_PdoMalformedConfigException_if_malformed_config_array_is_provided()
    {
        $badConfigArray = [
            'wrong' => 'param',
            'wrong2' => 'param2',
            'wrong3' => 'param3',
        ];

        new PdoDriver($badConfigArray);
    }

    /**
     * @test
     */
    public function it_should_return_PDO_instance_if_correct_config_array_is_provided()
    {
        $driver = new PdoDriver($this->pdo_parameters);
        $instance = $driver->getInstance();
        $driver->clear();

        $this->assertInstanceOf(\PDO::class, $instance);
    }
}
