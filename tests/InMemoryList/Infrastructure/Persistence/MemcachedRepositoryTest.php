<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Domain\Model\ListCollectionUuid;
use InMemoryList\Infrastructure\Persistance\MemcachedRepository;
use InMemoryList\Tests\BaseTestCase;

class MemcachedRepositoryTest extends BaseTestCase
{
    /**
     * @var MemcachedRepository
     */
    private $repo;

    public function setUp()
    {
        parent::setUp();

        $memcached = new Memcached();
        $memcached->addServers($this->memcached_parameters);

        $this->repo = new MemcachedRepository($memcached);
    }

    /**
     * @test
     */
    public function it_should_create_query_and_delete_the_list_from_memcached()
    {
        $fakeElement1 = new ListElement($fakeUUid1 = new ListElementUuid(), [
            'id' => 123,
            'title' => 'Lorem Ipsum',
            'category-id' => 27,
            'category' => 'holiday',
            'rate' => 4,
        ]);
        $fakeElement2 = new ListElement($fakeUUid2 = new ListElementUuid(), [
            'id' => 124,
            'title' => 'Neque porro quisquam',
            'category-id' => 28,
            'category' => 'last minute',
            'rate' => 5,
        ]);
        $fakeElement3 = new ListElement($fakeUUid3 = new ListElementUuid(), [
            'id' => 125,
            'title' => 'Ipso facto',
            'category-id' => 28,
            'category' => 'last minute',
            'rate' => 1,
        ]);
        $fakeElement4 = new ListElement($fakeUUid4 = new ListElementUuid(), [
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

        $collectionUuid = (string)$collection->getUuid();

        $this->repo->create($collection, 3600);
        $this->repo->deleteElement(
            (string)$collection->getUuid(),
            (string)$fakeElement5->getUuid()
        );

        $element1 = unserialize($this->repo->findElement(
            (string)$collection->getUuid(),
            (string)$fakeUUid1->getUuid())
        );

        $this->assertCount(4, $this->repo->findListByUuid($collectionUuid));
        $this->assertArrayHasKey('id', $element1);
        $this->assertArrayHasKey('title', $element1);
        $this->assertArrayHasKey('category-id', $element1);
        $this->assertArrayHasKey('category', $element1);
        $this->assertArrayHasKey('rate', $element1);
        $this->assertEquals(4, $this->repo->getCounter($collectionUuid));

        $this->repo->pushElement(
            (string)$collection->getUuid(),
            $fakeElement6
        );

        $this->assertEquals(5, $this->repo->getCounter($collectionUuid));

        $this->repo->deleteElement(
            (string)$collection->getUuid(),
            (string)$fakeElement1->getUuid()
        );
        $this->repo->deleteElement(
            (string)$collection->getUuid(),
            (string)$fakeElement2->getUuid()
        );
        $this->repo->deleteElement(
            (string)$collection->getUuid(),
            (string)$fakeElement3->getUuid()
        );
        $this->repo->deleteElement(
            $collectionUuid,
            (string)$fakeElement4->getUuid()
        );
        $this->repo->deleteElement(
            $collectionUuid,
            (string)$fakeElement6->getUuid()
        );

        $this->assertEquals(0, $this->repo->getCounter($collectionUuid));
    }

    /**
     * @test
     */
    public function it_should_create_query_and_delete_a_parsed_json_list_from_memcached()
    {
        $headers = [
            'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
            'hash' => 'ec457d0a974c48d5685a7efa03d137dc8bbde7e3',
        ];

        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../../examples/files/users.json'));

        $listUuid = new ListCollectionUuid();
        $collection = new ListCollection($listUuid);
        foreach ($parsedArrayFromJson as $element) {
            $collection->addItem(new ListElement($fakeUuid1 = new ListElementUuid(), $element));
        }
        $collection->setHeaders($headers);

        $this->repo->create($collection, 1200);

        $list = $this->repo->findListByUuid((string)$collection->getUuid());
        $element = unserialize(
            (string)$this->repo->findElement((string)$collection->getUuid(),
            (string)$fakeUuid1->getUuid())
        );

        $index = unserialize($this->repo->getIndex()[(string)$listUuid]);

        $this->assertCount(10, $list);
        $this->assertInstanceOf(stdClass::class, $element);
        $this->assertEquals($this->repo->getHeaders($collection->getUuid()), $headers);
        $this->assertEquals(10, $index['size']);
        $this->assertGreaterThan(0, $this->repo->getStatistics());
        $this->assertEquals(1200, $this->repo->getTtl((string)$collection->getUuid()));

        $this->repo->updateTtl((string)$listUuid, 2400);
        $this->assertEquals(2400, $this->repo->getTtl((string)$collection->getUuid()));

        $this->repo->delete((string)$listUuid);
    }

    /**
     * @test
     * @expectedException InMemoryList\Infrastructure\Persistance\Exceptions\ListDoesNotExistsException
     * @expectedExceptionMessage List not existing hash does not exists in memory.
     */
    public function it_throws_ListAlreadyExistsException_if_attempt_to_update_ttl_on_an_invalid_hash()
    {
        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../../examples/files/users.json'));

        $listUuid = new ListCollectionUuid();
        $collection = new ListCollection($listUuid);
        foreach ($parsedArrayFromJson as $element) {
            $collection->addItem(new ListElement($fakeUuid1 = new ListElementUuid(), $element));
        }

        $this->repo->create($collection, 3600);
        $this->repo->updateTtl('not existing hash', 7200);
    }
}
