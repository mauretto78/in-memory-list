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
use InMemoryList\Domain\Model\Contracts\ListRepositoryInterface;
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
     * @expectedException \InMemoryList\Application\Exceptions\NotSupportedDriverException
     * @expectedExceptionMessage not supported driver is not a supported driver.
     */
    public function it_throws_NotSupportedDriverException_if_a_not_supported_driver_is_provided()
    {
        new Client('not supported driver');
    }

    /**
     * @test
     * @expectedException \Predis\Connection\ConnectionException
     * @expectedExceptionMessage Connection refused [tcp://0.0.0.0:432423423]
     */
    public function it_throws_ConnectionException_if_wrong_redis_credentials_are_provided()
    {
        $wrongCredentials = [
            'host' => '0.0.0.0',
            'port' => 432423423,
            'database' => 15,
        ];

        $client = new Client('redis', $wrongCredentials);
        $collection = $client->create($this->parsedArrayFromJson, []);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Persistance\Exceptions\ListAlreadyExistsException
     * @expectedExceptionMessage List fake-list already exists in memory.
     */
    public function it_throws_CollectionAlreadyExistsException_if_attempt_to_persist_duplicate_collection()
    {
        foreach ($this->clients as $client) {
            $client->create($this->parsedArrayFromJson, [
                'uuid' => 'fake list',
            ]);
            $collection2 = $client->create($this->parsedArrayFromJson, [
                'uuid' => 'fake list',
            ]);
        }
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exceptions\MalformedParametersException
     * @expectedExceptionMessage Malformed parameters array provided to Client create function.
     */
    public function it_throws_MalformedParametersException_if_attempt_to_provide_a_wrong_parameters_array_when_create_list()
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
     * @expectedException \InMemoryList\Infrastructure\Persistance\Exceptions\ListElementDoesNotExistsException
     * @expectedExceptionMessage Cannot retrieve the element 132131312 from the collection in memory.
     */
    public function it_throws_NotExistListElementException_if_attempt_to_find_a_not_existing_element_in_collection_from_redis()
    {
        foreach ($this->clients as $client) {
            $client->getRepository()->flush();
            $client->create($this->parsedArrayFromJson, [
                'uuid' => 'fake list',
                'element-uuid' => 'id',
            ]);

            $client->getRepository()->findElement('fake list', '132131312');
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
            $this->assertEquals(5001, $client->getRepository()->getCounter('range-list'));
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

            $client->getRepository()->flush();
            $client->create($this->parsedArrayFromJson, [
                'headers' => $headers,
                'ttl' => 3600,
                'uuid' => 'fake list',
                'element-uuid' => 'id',
            ]);
            $client->getRepository()->deleteElement('fake-list', '7');
            $client->getRepository()->deleteElement('fake-list', '8');
            $client->getRepository()->deleteElement('fake-list', '9');
            $element1 = $client->getRepository()->findElement('fake-list', '1');
            $element2 = $client->getRepository()->findElement('fake-list', '2');

            $this->assertInstanceOf(ListRepositoryInterface::class, $client->getRepository());
            $this->assertCount(7, $client->getRepository()->findListByUuid('fake-list'));
            $this->assertEquals('Leanne Graham', $element1->name);
            $this->assertEquals('Ervin Howell', $element2->name);

            $headers1 = $client->getRepository()->getHeaders('fake-list');
            $this->assertEquals($headers1, $headers);
            $this->assertArrayHasKey('expires', $headers1);
            $this->assertArrayHasKey('hash', $headers1);
            $this->assertEquals('ec457d0a974c48d5685a7efa03d137dc8bbde7e3', $headers1['hash']);

            $a = [
                'id' => 2,
                'name' => 'Mauro Cassani',
                'username' => 'mauretto78',
                'email' => 'mauretto1978@yahoo.it',
                'address' => [
                    'street' => 'Kulas Light',
                    'suite' => 'Apt. 556',
                    'city' => 'Gwenborough',
                    'zipcode' => '92998-3874',
                    'geo' => [
                        'lat' => '-37.3159',
                        'lng' => '81.1496',
                    ],
                ],
                'phone' => '1-770-736-8031 x56442',
                'website' => 'hildegard.org',
                'company' => [
                    'name' => 'Romaguera-Crona',
                    'catchPhrase' => 'Multi-layered client-server neural-net',
                    'bs' => 'harness real-time e-markets',
                ],
                'tags' => [
                    'apple',
                    'pear',
                ],
            ];

            $client->getRepository()->updateElement('fake-list', '2', $a);

            $element2 = $client->getRepository()->findElement('fake-list', '2');

            $this->assertEquals('Mauro Cassani', $element2->name);
            $this->assertEquals('mauretto78', $element2->username);
            $this->assertEquals('mauretto1978@yahoo.it', $element2->email);

            $client->getRepository()->updateTtl('fake-list', 7200);
            $this->assertEquals($client->getRepository()->getTtl('fake-list'), 7200);

            $client->getRepository()->removeListFromIndex('fake list');
            $client->getRepository()->delete('fake list');
        }
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Domain\Model\Exceptions\NotValidKeyElementInListException
     * @expectedExceptionMessage id is not a valid key. If your elements are Entities class, please check if you implement getId() method.
     */
    public function it_catch_NotValidKeyElementInListException_if_entity_class_does_not_have_getter_method()
    {
        $dummyUser1 = new DummyUserEntityWithNoGetters(
            23,
            'Mauro',
            'mauro@gmail.com',
            '1234567'
        );
        $dummyUser2 = new DummyUserEntityWithNoGetters(
            24,
            'Cristina',
            'cristina@gmail.com',
            '7654321'
        );
        $dummyUser3 = new DummyUserEntityWithNoGetters(
            25,
            'Lilli',
            'lilliput@gmail.com',
            '99999999'
        );
        $entityList = [$dummyUser1, $dummyUser2, $dummyUser3];

        foreach ($this->clients as $client) {
            $client->getRepository()->flush();

            $collection = $client->create($entityList, [
                'ttl' => 3600,
                'uuid' => 'entity list',
                'element-uuid' => 'id',
            ]);
        }
    }

    /**
     * @test
     */
    public function it_should_correct_pesist_an_entity_list()
    {
        $dummyUser1 = new DummyUserEntity(
            23,
            'Mauro',
            'mauro@gmail.com',
            '1234567'
        );
        $dummyUser2 = new DummyUserEntity(
            24,
            'Cristina',
            'cristina@gmail.com',
            '7654321'
        );
        $dummyUser3 = new DummyUserEntity(
            25,
            'Lilli',
            'lilliput@gmail.com',
            '99999999'
        );
        $entityList = [$dummyUser1, $dummyUser2, $dummyUser3];

        foreach ($this->clients as $client) {
            $headers = [
                'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
                'hash' => 'ec457d0a974c48d5685a7efa03d137dc8bbde7e3',
            ];

            $client->getRepository()->flush();
            $client->create($entityList, [
                'headers' => $headers,
                'ttl' => 3600,
                'uuid' => 'entity list',
                'element-uuid' => 'id',
            ]);

            $element1 = $client->getRepository()->findElement('entity-list', '23');
            $element2 = $client->getRepository()->findElement('entity-list', '24');

            $this->assertInstanceOf(ListRepositoryInterface::class, $client->getRepository());
            $this->assertCount(3, $client->getRepository()->findListByUuid('entity-list'));
            $this->assertTrue($client->getRepository()->existsListInIndex('entity-list'));
            $this->assertTrue($client->getRepository()->existsElement('entity-list', '24'));
            $this->assertEquals('Mauro', $element1->getName());
            $this->assertEquals('Cristina', $element2->getName());
        }
    }
}

class DummyUserEntityWithNoGetters
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $phone;

    /**
     * DummyUserEntity constructor.
     *
     * @param $id
     * @param $name
     * @param $email
     * @param $phone
     */
    public function __construct(
        $id,
        $name,
        $email,
        $phone
    ) {
        $this->_setId($id);
        $this->_setName($name);
        $this->_setEmail($email);
        $this->_setPhone($phone);
    }

    /**
     * @param mixed $id
     */
    private function _setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param mixed $name
     */
    private function _setName($name)
    {
        $this->name = $name;
    }

    /**
     * @param mixed $email
     */
    private function _setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @param mixed $phone
     */
    private function _setPhone($phone)
    {
        $this->phone = $phone;
    }
}

class DummyUserEntity
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $phone;

    /**
     * DummyUserEntity constructor.
     *
     * @param $id
     * @param $name
     * @param $email
     * @param $phone
     */
    public function __construct(
        $id,
        $name,
        $email,
        $phone
    ) {
        $this->_setId($id);
        $this->_setName($name);
        $this->_setEmail($email);
        $this->_setPhone($phone);
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    private function _setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    private function _setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    private function _setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param mixed $phone
     */
    private function _setPhone($phone)
    {
        $this->phone = $phone;
    }
}
