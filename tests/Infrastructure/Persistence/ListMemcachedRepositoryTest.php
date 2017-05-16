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
                ['localhost', 11211],
            ]
        );

        $this->repo = new ListMemcachedRepository($memcached);
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

        $listUuid = new ListCollectionUuid();
        $list = new ListCollection($listUuid);
        $list->addItem($fakeElement1);
        $list->addItem($fakeElement2);
        $list->addItem($fakeElement3);
        $list->addItem($fakeElement4);
        $list->addItem($fakeElement5);

        $this->repo->create($list);
        $this->repo->deleteElement($listUuid, $fakeElement3->getUuid());
        $element5Uuid = $fakeUUid5->getUuid();
        $element5 = $this->repo->findElement($listUuid, $element5Uuid);

        $this->assertCount(4, $this->repo->findListByUuid($listUuid));

        $element5 = unserialize($element5)->getBody();

        $this->assertEquals(127, $element5['id']);
        $this->assertEquals('Dolor facius', $element5['title']);
        $this->assertEquals(27, $element5['category-id']);
        $this->assertEquals('holiday', $element5['category']);
        $this->assertEquals(5, $element5['rate']);

        $this->repo->updateTtl($listUuid, 7200);
        $this->repo->deleteList($listUuid);
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

        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../examples/files/users.json'));

        $listUuid = new ListCollectionUuid();
        $list = new ListCollection($listUuid);
        foreach ($parsedArrayFromJson as $element) {
            $list->addItem(new ListElement($fakeUuid1 = new ListElementUuid(), $element));
        }
        $list->setHeaders($headers);
        $this->repo->create($list);

        $this->assertCount(10, $this->repo->findListByUuid($list->getUuid()));
        $this->assertEquals($this->repo->getHeaders($list->getUuid()), $headers);
        $this->assertGreaterThan(0, $this->repo->stats());

        $this->repo->deleteList($listUuid);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Persistance\Exception\ListDoesNotExistsException
     * @expectedExceptionMessage List not existing hash does not exists in memory.
     */
    public function it_throws_ListAlreadyExistsException_if_attempt_to_update_ttl_on_an_invalid_hash()
    {
        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../examples/files/users.json'));

        $listUuid = new ListCollectionUuid();
        $list = new ListCollection($listUuid);
        foreach ($parsedArrayFromJson as $element) {
            $list->addItem(new ListElement($fakeUuid1 = new ListElementUuid(), $element));
        }

        $this->repo->create($list, 3600);
        $this->repo->updateTtl('not existing hash', 7200);
    }
}
