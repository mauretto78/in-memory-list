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
use InMemoryList\Domain\Model\Contracts\ListRepository;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListElementDoesNotExistsException;
use InMemoryList\Tests\BaseTestCase;

class ClientTest extends BaseTestCase
{
    /**
     * @var array
     */
    private $parsedArrayFromJson;

    /**
     * @var array
     */
    private $clients;

    public function setUp()
    {
        parent::setUp();

        $this->parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../examples/files/users.json'));
        $this->clients = [
            'apcu' => new Client('apcu'),
            'memcached' => new Client('memcached', $this->memcached_parameters),
            'redis' => new Client('redis', $this->redis_parameters),
        ];
    }

    /**
     * @test
     * @expectedException InMemoryList\Application\Exceptions\NotSupportedDriverException
     * @expectedExceptionMessage not supported driver is not a supported driver.
     */
    public function it_throws_NotSupportedDriverException_if_a_not_supported_driver_is_provided()
    {
        new Client('not supported driver');
    }

    /**
     * @test
     */
    public function it_catch_ConnectionException_if_wrong_redis_credentials_are_provided()
    {
        $wrongCredentials = array(
            'host' => '0.0.0.0',
            'port' => 432423423,
            'database' => 15,
        );

        $client = new Client('redis', $wrongCredentials);
        $collection = $client->create($this->parsedArrayFromJson, [], 'fake list');

        $this->assertEquals($collection, 'Connection refused [tcp://0.0.0.0:432423423]');
    }

    /**
     * @test
     */
    public function it_catch_CollectionAlreadyExistsException_if_attempt_to_persist_duplicate_collection()
    {
        foreach ($this->clients as $client) {
            $client->create($this->parsedArrayFromJson, [
                'uuid' => 'fake list',
            ]);
            $collection2 = $client->create($this->parsedArrayFromJson, [
                'uuid' => 'fake list',
            ]);

            $this->assertEquals($collection2, 'List fake-list already exists in memory.');
        }
    }

    /**
     * @test
     */
    public function it_catch_MalformedParametersException_if_attempt_to_provide_a_wrong_parameters_array_when_create_list()
    {
        foreach ($this->clients as $client) {
            $collection = $client->create($this->parsedArrayFromJson, [
                'not-allowed-key' => 'not-allowed-value',
                'uuid' => 'fake list',
            ]);

            $this->assertEquals($collection, 'Malformed parameters array provided to Client create function.');
        }
    }

    /**
     * @test
     */
    public function it_throws_NotExistListElementException_if_attempt_to_find_a_not_existing_element_in_collection_from_redis()
    {
        foreach ($this->clients as $client) {
            $client->flush();
            $client->create($this->parsedArrayFromJson, [
                'uuid' => 'fake list',
                'element-uuid' => 'id',
            ]);

            try {
                $client->findElement('fake list', '132131312');
            } catch (\Exception $exception) {
                $this->assertInstanceOf(ListElementDoesNotExistsException::class, $exception);
                $this->assertEquals($exception->getMessage(), 'Cannot retrieve the element 132131312 from the collection in memory.');
            }
        }
    }

    /**
     * @test
     */
    public function it_should_store_delete_and_retrieve_correctly_list_elements_in_chunks()
    {
        foreach ($this->clients as $driver => $client) {
            $array = [];
            foreach (range(1, 5000) as $number) {
                $array[] = [
                    'id' => $number,
                    'name' => 'Name '.$number,
                    'email' => 'Email'.$number,
                ];
            }

            $apiArray = json_encode($array);

            $client->create(json_decode($apiArray), [
                'uuid' => 'range list',
                'chunk-size' => 10,
            ]);

            $client->pushElement(
                'range-list',
                5001,
                [
                    'id' => 5001,
                    'name' => 'Name 5001',
                    'email' => 'Email 5001',
                ]
            );

            $this->assertEquals($driver, $client->getDriver());
            $this->assertEquals(5001, $client->getCounter('range-list'));
        }
    }

    /**
     * @test
     */
    public function it_should_store_delete_and_retrieve_correctly_list_elements()
    {
        foreach ($this->clients as $client) {
            $headers = [
                'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
                'hash' => 'ec457d0a974c48d5685a7efa03d137dc8bbde7e3',
            ];

            $client->flush();
            $client->create($this->parsedArrayFromJson, [
                'headers' => $headers,
                'ttl' => 3600,
                'uuid' => 'fake list',
                'element-uuid' => 'id',
            ]);
            $client->deleteElement('fake-list', '7');
            $client->deleteElement('fake-list', '8');
            $client->deleteElement('fake-list', '9');
            $element1 = unserialize($client->findElement('fake-list', '1'));
            $element2 = unserialize($client->findElement('fake-list', '2'));

            $this->assertInstanceOf(ListRepository::class, $client->getRepository());
            $this->assertCount(7, $client->findListByUuid('fake-list'));
            $this->assertEquals('Leanne Graham', $element1->name);
            $this->assertEquals('Ervin Howell', $element2->name);

            $headers1 = $client->getHeaders('fake-list');
            $this->assertEquals($headers1, $headers);
            $this->assertArrayHasKey('expires', $headers1);
            $this->assertArrayHasKey('hash', $headers1);
            $this->assertEquals('ec457d0a974c48d5685a7efa03d137dc8bbde7e3', $headers1['hash']);

            $client->updateElement('fake-list', '2', [
                'name' => 'Mauro Cassani',
                'username' => 'mauretto78',
                'email' => 'mauretto1978@yahoo.it',
            ]);

            $element2 = unserialize($client->findElement('fake-list', '2'));

            $this->assertEquals('Mauro Cassani', $element2->name);
            $this->assertEquals('mauretto78', $element2->username);
            $this->assertEquals('mauretto1978@yahoo.it', $element2->email);

            $client->updateTtl('fake-list', 7200);

            $client->removeListFromIndex('fake list');
            $client->delete('fake list');
        }
    }
}
