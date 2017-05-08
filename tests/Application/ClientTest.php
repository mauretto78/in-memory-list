<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use InMemoryList\Application\Client;
use InMemoryList\Infrastructure\Persistance\ListMemcachedRepository;
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
     * @expectedException \InMemoryList\Application\Exception\NotSupportedDriverException
     * @expectedExceptionMessage not supported driver is not a supported driver.
     */
    public function it_throws_NotSupportedDriverException_if_a_not_supported_driver_is_provided()
    {
        new Client('not supported driver');
    }

    /**
     * @test
     */
    public function it_throws_ConnectionException_if_wrong_redis_credentials_are_provided()
    {
        $wrongCredentials = array(
            'host' => '0.0.0.0',
            'port' => 432423423,
            'database' => 15,
        );

        $client = new Client('redis', $wrongCredentials);
        $collection = $client->create($this->parsedArrayFromJson, 'fake list');

        $this->assertEquals($collection, 'Connection refused [tcp://0.0.0.0:432423423]');
    }

    /**
     * @test
     */
    public function it_catch_CollectionAlreadyExistsException_if_attempt_to_persist_duplicate_collection_from_redis()
    {
        $client = new Client();
        $collection = $client->create($this->parsedArrayFromJson, 'fake list');
        $collection2 = $client->create($this->parsedArrayFromJson, 'fake list');

        $this->assertEquals($collection2, 'Collection fake-list already exists in memory.');
    }

    /**
     * @test
     */
    public function it_catch_CollectionAlreadyExistsException_if_attempt_to_persist_duplicate_collection_from_memcached()
    {
        $memcached_params = [
            ['localhost', 11211]
        ];

        $client = new Client('memcached', $memcached_params);
        $collection = $client->create($this->parsedArrayFromJson, 'fake list');
        $collection2 = $client->create($this->parsedArrayFromJson, 'fake list');

        $this->assertEquals($collection2, 'Collection fake-list already exists in memory.');
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Persistance\Exception\NotExistListElementException
     * @expectedExceptionMessage Cannot retrieve the element 132131312 from the collection in memory.
     */
    public function it_throws_NotExistListElementException_if_attempt_to_find_a_not_existing_element_in_collection_from_redis()
    {
        $client = new Client();
        $client->flush();
        $client->create($this->parsedArrayFromJson, 'fake-list', 'id');
        $client->findElement('fake list', '132131312');
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Persistance\Exception\NotExistListElementException
     * @expectedExceptionMessage Cannot retrieve the element 132131312 from the collection in memory.
     */
    public function it_throws_NotExistListElementException_if_attempt_to_find_a_not_existing_element_in_collection_from_memcached()
    {
        $memcached_params = [
            ['localhost', 11211]
        ];

        $client = new Client('memcached', $memcached_params);
        $client->flush();
        $client->create($this->parsedArrayFromJson, 'fake-list', 'id');
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
        $client->deleteElement('fake-list', '7');
        $client->deleteElement('fake-list', '8');
        $client->deleteElement('fake-list', '9');
        $element1 = $client->findElement('fake-list', '1');
        $element2 = $client->findElement('fake-list', '2');

        $this->assertInstanceOf(ListRedisRepository::class, $client->getRepository());
        $this->assertCount(7, $client->findByUUid('fake-list'));
        $this->assertEquals('Leanne Graham', $element1->getBody()->name);
        $this->assertEquals('Ervin Howell', $element2->getBody()->name);

        $client->delete('fake list');
    }

    /**
     * @test
     */
    public function it_should_store_delete_and_retrieve_from_memcached_correctly_list_elements()
    {
        $memcached_params = [
            ['localhost', 11211]
        ];

        $client = new Client('memcached', $memcached_params);
        $client->flush();
        $client->create($this->parsedArrayFromJson, 'fake list', 'id');
        $client->deleteElement('fake-list', '7');
        $client->deleteElement('fake-list', '8');
        $client->deleteElement('fake-list', '9');
        $element1 = $client->findElement('fake-list', '1');
        $element2 = $client->findElement('fake-list', '2');

        $this->assertInstanceOf(ListMemcachedRepository::class, $client->getRepository());
        $this->assertCount(7, $client->findByUUid('fake-list'));
        $this->assertEquals('Leanne Graham', $element1->getBody()->name);
        $this->assertEquals('Ervin Howell', $element2->getBody()->name);

        $client->delete('fake-list');
    }
}
