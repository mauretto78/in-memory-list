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
use InMemoryList\Domain\Model\ListElementUuId;
use InMemoryList\Domain\Model\ListCollectionUuId;
use InMemoryList\Infrastructure\Persistance\ListMemcachedRepository;
use PHPUnit\Framework\TestCase;

class ListMemcachedRepositoryTest extends TestCase
{
    /**
     * @var ListMemcachedRepository
     */
    private $repo;

    public function setUp()
    {
        parent::setUp();

        $memcached = new \Memcached();
        $memcached->addServers(
            [
                ['localhost', 11211]
            ]
        );

        $this->repo = new ListMemcachedRepository($memcached);
    }

    /**
     * @test
     */
    public function it_should_create_query_and_delete_the_list_from_memcached()
    {
        $fakeElement1 = new ListElement($fakeUUid1 = new ListElementUuId(), [
            'id' => 123,
            'title' => 'Lorem Ipsum',
            'category-id' => 27,
            'category' => 'holiday',
            'rate' => 4,
        ]);
        $fakeElement2 = new ListElement($fakeUUid2 = new ListElementUuId(), [
            'id' => 124,
            'title' => 'Neque porro quisquam',
            'category-id' => 28,
            'category' => 'last minute',
            'rate' => 5,
        ]);
        $fakeElement3 = new ListElement($fakeUUid3 = new ListElementUuId(), [
            'id' => 125,
            'title' => 'Ipso facto',
            'category-id' => 28,
            'category' => 'last minute',
            'rate' => 1,
        ]);
        $fakeElement4 = new ListElement($fakeUUid4 = new ListElementUuId(), [
            'id' => 126,
            'title' => 'Ipse dixit',
            'category-id' => 27,
            'category' => 'holiday',
            'rate' => 3,
        ]);
        $fakeElement5 = new ListElement($fakeUUid5 = new ListElementUuId(), [
            'id' => 127,
            'title' => 'Dolor facius',
            'category-id' => 27,
            'category' => 'holiday',
            'rate' => 5,
        ]);

        $collectionUuid = new ListCollectionUuId();
        $collection = new ListCollection($collectionUuid);
        $collection->addItem($fakeElement1);
        $collection->addItem($fakeElement2);
        $collection->addItem($fakeElement3);
        $collection->addItem($fakeElement4);
        $collection->addItem($fakeElement5);

        $this->repo->create($collection);
        $this->repo->deleteElement($collection->getUuid(), $fakeElement5->getUuid());

        $this->assertCount(4, $this->repo->findByUuid($collection->getUuid()));
        $this->assertInstanceOf(ListElement::class, $this->repo->findElement($collection->getUuid(), $fakeUUid1->getUuid()));

        $this->repo->delete($collectionUuid);
    }

    /**
     * @test
     */
    public function it_should_create_query_and_delete_a_parsed_json_list_from_memcached()
    {
        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../examples/files/users.json'));

        $collectionUuid = new ListCollectionUuId();
        $collection = new ListCollection($collectionUuid);

        foreach ($parsedArrayFromJson as $element) {
            $collection->addItem(new ListElement($fakeUuid1 = new ListElementUuId(), $element));
        }

        $this->repo->create($collection);

        $this->assertCount(10, $this->repo->findByUuid($collection->getUuid()));
        $this->assertInstanceOf(ListElement::class, $this->repo->findElement($collection->getUuid(), $fakeUuid1->getUuid()));

        $this->repo->delete($collectionUuid);
    }
}
