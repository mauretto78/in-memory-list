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
use InMemoryList\Infrastructure\Persistance\ListRedisRepository;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class ListRedisRepositoryTest extends TestCase
{
    /**
     * @var ListRedisRepository
     */
    private $repo;

    public function setUp()
    {
        parent::setUp();

        $this->repo = new ListRedisRepository(new Client());
    }

    /**
     * @test
     */
    public function it_should_create_query_and_delete_the_list_from_redis()
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
        $element5Uuid = $fakeUUid5->getUuid();
        $element5 = $this->repo->findElement($listUuid, $element5Uuid);
        $creationDateOfElement5 = $this->repo->findCreationDateOfElement($listUuid, $element5Uuid);

        $this->assertCount(5, $this->repo->findListByUuid($list->getUuid()));
        $this->assertEquals(127, $element5['id']);
        $this->assertEquals('Dolor facius', $element5['title']);
        $this->assertEquals(27, $element5['category-id']);
        $this->assertEquals('holiday', $element5['category']);
        $this->assertEquals(5, $element5['rate']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $creationDateOfElement5);

        $this->repo->delete($listUuid);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Persistance\Exception\ListElementDoesNotExistsException
     * @expectedExceptionMessage Cannot retrieve the element not-existing-element from the collection in memory.
     */
    public function it_throws_ListElementDoesNotExistsException_if_attempt_to_call_findCreationDateOfElement_on_an_invalid_hash()
    {
        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../examples/files/users.json'));

        $listUuid = new ListCollectionUuid();
        $list = new ListCollection($listUuid);
        foreach ($parsedArrayFromJson as $element) {
            $list->addItem(new ListElement($fakeUuid1 = new ListElementUuid(), $element));
        }

        $this->repo->create($list, 3600);
        $this->repo->findCreationDateOfElement($listUuid, 'not-existing-element');
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

//    /**
//     * @test
//     */
//    public function it_should_create_query_and_delete_a_parsed_json_list_from_redis()
//    {
//        $this->repo->flush();
//
//        $headers = [
//            'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
//            'hash' => 'ec457d0a974c48d5685a7efa03d137dc8bbde7e3',
//        ];
//
//        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../examples/files/users.json'));
//
//        $listUuid = new ListCollectionUuid();
//        $list = new ListCollection($listUuid);
//        foreach ($parsedArrayFromJson as $element) {
//            $list->addItem(new ListElement($fakeUuid1 = new ListElementUuid(), $element));
//        }
//        $list->setHeaders($headers);
//
//        $this->repo->create($list, 3600);
//
//        $this->assertCount(10, $this->repo->findListByUuid($list->getUuid()));
//        $this->assertEquals($this->repo->getHeaders($listUuid), $headers);
//        $this->assertCount(11, $this->repo->all());
//        $this->assertGreaterThan(0, $this->repo->stats());
//
//        $this->repo->updateTtl($listUuid, 7200);
//
//        foreach ($this->repo->findListByUuid($listUuid) as $elementUuid){
//            $this->assertEquals(7200, $this->repo->ttl($elementUuid));
//        }
//
//        $this->repo->delete($listUuid);
//    }
}
