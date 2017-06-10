<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use InMemoryList\Domain\Model\Contracts\ListRepository;
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Domain\Model\ListCollectionUuid;
use InMemoryList\Infrastructure\Persistance\ApcuRepository;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListDoesNotExistsException;
use InMemoryList\Infrastructure\Persistance\MemcachedRepository;
use InMemoryList\Infrastructure\Persistance\RedisRepository;
use InMemoryList\Tests\BaseTestCase;
use Predis\Client;

class RepositoryTest extends BaseTestCase
{
    /**
     * @var array
     */
    private $repos;

    public function setUp()
    {
        parent::setUp();

        // memcached
        $memcached = new Memcached();
        $memcached->addServers($this->memcached_parameters);

        // redis
        $redis_parameters = $this->redis_parameters;

        $this->repos = [
            new ApcuRepository(),
            new MemcachedRepository($memcached),
            new RedisRepository(new Client($redis_parameters)),
        ];
    }

    /**
     * @test
     */
    public function it_should_create_query_and_delete_the_list()
    {
        /** @var ListRepository $repo */
        foreach ($this->repos as $repo) {
            $fakeElement1 = new ListElement($fakeUUid1 = new ListElementUuid(), [
                'id' => 123,
                'title' => 'Lorem Ipsum',
                'category-id' => 27,
                'category' => 'holiday',
                'rate' => 4,
            ]);
            $fakeElement2 = new ListElement(new ListElementUuid(), [
                'id' => 124,
                'title' => 'Neque porro quisquam',
                'category-id' => 28,
                'category' => 'last minute',
                'rate' => 5,
            ]);
            $fakeElement3 = new ListElement(new ListElementUuid(), [
                'id' => 125,
                'title' => 'Ipso facto',
                'category-id' => 28,
                'category' => 'last minute',
                'rate' => 1,
            ]);
            $fakeElement4 = new ListElement(new ListElementUuid(), [
                'id' => 126,
                'title' => 'Ipse dixit',
                'category-id' => 27,
                'category' => 'holiday',
                'rate' => 3,
            ]);
            $fakeElement5 = new ListElement($fakeUUid5 = new ListElementUuid(), [
                'id' => 127,
                'title' => 'Dolor facius',
                'category-id' => 27,
                'category' => 'holiday',
                'rate' => 5,
            ]);
            $fakeElement6 = new ListElement($fakeUUid5 = new ListElementUuid(), [
                'id' => 128,
                'title' => 'Veni vidi vici',
                'category-id' => 29,
                'category' => 'travel',
                'rate' => 5,
            ]);

            $listUuid = new ListCollectionUuid();
            $collection = new ListCollection($listUuid);
            $collection->addItem($fakeElement1);
            $collection->addItem($fakeElement2);
            $collection->addItem($fakeElement3);
            $collection->addItem($fakeElement4);
            $collection->addItem($fakeElement5);

            $collectionUuid = (string) $collection->getUuid();

            $repo->create($collection, 3600);
            $repo->deleteElement($collectionUuid, (string) $fakeElement5->getUuid());

            $element1 = unserialize($repo->findElement((string) $collection->getUuid(), (string) $fakeUUid1->getUuid()));

            $this->assertCount(4, $repo->findListByUuid($collectionUuid));
            $this->assertArrayHasKey('id', $element1);
            $this->assertArrayHasKey('title', $element1);
            $this->assertArrayHasKey('category-id', $element1);
            $this->assertArrayHasKey('category', $element1);
            $this->assertArrayHasKey('rate', $element1);
            $this->assertEquals(4, $repo->getCounter($collectionUuid));

            $repo->pushElement($collectionUuid, $fakeElement6);

            $this->assertEquals(5, $repo->getCounter($collectionUuid));

            $repo->deleteElement($collectionUuid, (string) $fakeElement1->getUuid());
            $repo->deleteElement($collectionUuid, (string) $fakeElement2->getUuid());
            $repo->deleteElement($collectionUuid, (string) $fakeElement3->getUuid());
            $repo->deleteElement($collectionUuid, (string) $fakeElement4->getUuid());
            $repo->deleteElement($collectionUuid, (string) $fakeElement6->getUuid());

            $this->assertEquals(0, $repo->getCounter($collectionUuid));
            $this->assertEquals(0, $repo->getNumberOfChunks($collectionUuid));
            $this->assertEquals(0, $repo->getChunkSize($collectionUuid));
        }
    }

    /**
     * @test
     */
    public function it_throws_ListAlreadyExistsException_if_attempt_to_update_ttl_on_an_invalid_hash()
    {
        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../../examples/files/users.json'));

        /** @var ListRepository $repo */
        foreach ($this->repos as $repo) {
            $listUuid = new ListCollectionUuid();
            $collection = new ListCollection($listUuid);
            foreach ($parsedArrayFromJson as $element) {
                $collection->addItem(new ListElement(new ListElementUuid(), $element));
            }

            $repo->create($collection, 3600);

            try {
                $repo->updateTtl('not existing hash', 7200);
            } catch (\Exception $exception) {
                $this->assertInstanceOf(ListDoesNotExistsException::class, $exception);
                $this->assertEquals($exception->getMessage(), 'List not existing hash does not exists in memory.');
            }
        }
    }

    /**
     * @test
     */
    public function it_should_create_query_and_delete_a_parsed_json_list_and_get_statistics()
    {
        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../../examples/files/users.json'));

        /** @var ListRepository $repo */
        foreach ($this->repos as $repo) {
            $repo->flush();

            $headers = [
                'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
                'hash' => 'ec457d0a974c48d5685a7efa03d137dc8bbde7e3',
            ];

            $listUuid = new ListCollectionUuid();
            $collection = new ListCollection($listUuid);
            foreach ($parsedArrayFromJson as $element) {
                $collection->addItem(new ListElement($fakeUuid1 = new ListElementUuid(), $element));
            }
            $collection->setHeaders($headers);

            $repo->create($collection, 3600);

            $listUuid1 = (string) $collection->getUuid();
            $list = $repo->findListByUuid($listUuid1);
            $element = $repo->findElement($listUuid1, $fakeUuid1->getUuid());
            $listInTheIndex = $repo->getIndex()[$listUuid1];

            $this->assertCount(10, $list);
            $this->assertInstanceOf(stdClass::class, unserialize($element));
            $this->assertEquals($repo->getHeaders($listUuid1), $headers);
            $this->assertArrayHasKey('expires', $repo->getHeaders($listUuid1));
            $this->assertArrayHasKey('hash', $repo->getHeaders($listUuid1));
            $this->assertEquals(10, unserialize($listInTheIndex)['size']);
            $this->assertArrayHasKey($listUuid1, $repo->getIndex());

            $repo->updateTtl((string) $listUuid, -1);

            $this->assertEquals(-1, $repo->getTtl((string) $listUuid));

            $repo->delete((string) $listUuid);

            $this->assertGreaterThan(0, $repo->getStatistics());
        }
    }
}
