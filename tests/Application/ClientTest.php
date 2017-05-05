<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<assistenza@easy-grafica.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

use InMemoryList\Application\Client;
use InMemoryList\Infrastructure\Persistance\ListRedisRepository;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private $parsedArrayFromJson;

    public function setUp()
    {
        $this->parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../examples/files/users.json'));
    }

    /**
     * @test
     * @expectedException \Predis\Connection\ConnectionException
     * @expectedExceptionMessage Connection refused [tcp://0.0.0.0:432423423]
     */
    public function it_throws_ConnectionException_if_wrong_redis_credentials_are_provided()
    {
        $wrongCredentials = array(
            'host' => '0.0.0.0',
            'port' => 432423423,
            'database' => 15,
        );

        $client = new Client('redis', $wrongCredentials);
        $client->create($this->parsedArrayFromJson, 'fake list');
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exception\NotSupportedDriverException
     * @expectedExceptionMessage not supported driver is not a supported driver.
     */
    public function it_throws_NotSupportedDriverException_if_a_not_supported_driver_is_provided()
    {
        new Client('not supported driver');
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Persistance\Exception\CollectionAlreadyExistsException
     * @expectedExceptionMessage Collection fake list already exists in memory.
     */
    public function it_throws_CollectionAlreadyExistsException_if_attempt_to_persist_duplicate_collection()
    {
        $client = new Client();
        $client->create($this->parsedArrayFromJson, 'fake list');
        $client->create($this->parsedArrayFromJson, 'fake list');
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Persistance\Exception\NotExistListElementException
     * @expectedExceptionMessage Cannot retrieve the element 132131312 from the collection in memory.
     */
    public function it_throws_NotExistListElementException_if_attempt_to_find_a_not_existing_element_in_collection()
    {
        $client = new Client();
        $client->flush();
        $client->create($this->parsedArrayFromJson, 'fake list', 'id');
        $client->findElement('fake list', '132131312');
    }

    /**
     * @test
     */
    public function it_should_store_delete_and_retrieve_correctly_list_elements()
    {
        $client = new Client();
        $client->flush();
        $client->create($this->parsedArrayFromJson, 'fake list', 'id');
        $client->deleteElement('fake list', '7');
        $client->deleteElement('fake list', '8');
        $client->deleteElement('fake list', '9');
        $element1 = $client->findElement('fake list', '1');
        $element2 = $client->findElement('fake list', '2');

        $this->assertInstanceOf(ListRedisRepository::class, $client->getRepository());
        $this->assertCount(7, $client->findByUUid('fake list'));
        $this->assertEquals('Leanne Graham', $element1->getBody()->name);
        $this->assertEquals('Ervin Howell', $element2->getBody()->name);

        $client->delete('fake list');
    }
}
