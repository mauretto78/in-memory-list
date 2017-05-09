<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use InMemoryList\Domain\Model\ListCollectionUuid;
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Domain\Model\ListCollection;
use PHPUnit\Framework\TestCase;

class ListCollectionTest extends TestCase
{
    /**
     * @test
     * @expectedException \InMemoryList\Domain\Model\Exception\ListElementDuplicateKeyException
     */
    public function it_should_return_exception_if_a_try_to_add_duplicate_element()
    {
        $fakeElement1 = new ListElement($fakeUUid1 = new ListElementUuid(), 'lorem ipsum');

        $collection = new ListCollection(new ListCollectionUuid());
        $collection->addItem($fakeElement1);
        $collection->addItem($fakeElement1);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Domain\Model\Exception\ListElementKeyDoesNotExistException
     */
    public function it_should_return_exception_if_try_to_delete_a_not_existing_element()
    {
        $fakeElement1 = new ListElement($fakeUUid1 = new ListElementUuid(), 'lorem ipsum');

        $collection = new ListCollection(new ListCollectionUuid());
        $collection->deleteElement($fakeElement1);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Domain\Model\Exception\ListElementKeyDoesNotExistException
     */
    public function it_should_return_exception_if_not_finds_an_element()
    {
        $fakeElement1 = new ListElement($fakeUUid1 = new ListElementUuid(), 'lorem ipsum');

        $collection = new ListCollection(new ListCollectionUuid());
        $collection->getElement($fakeElement1->getUuid());
    }

    /**
     * @test
     */
    public function it_should_add_and_delete_elements_to_list()
    {
        $headers = [
            'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
            'hash' => 'ec457d0a974c48d5685a7efa03d137dc8bbde7e3',
        ];

        $fakeElement1 = new ListElement($fakeUUid1 = new ListElementUuid(), 'lorem ipsum');
        $fakeElement2 = new ListElement($fakeUUid2 = new ListElementUuid(), 'dolor facium');
        $fakeElement3 = new ListElement($fakeUUid3 = new ListElementUuid(), 'ipso facto');
        $fakeElement4 = new ListElement($fakeUUid4 = new ListElementUuid(), 'ipse dixit');

        $collection = new ListCollection(new ListCollectionUuid());
        $collection->addItem($fakeElement1);
        $collection->addItem($fakeElement2);
        $collection->addItem($fakeElement3);
        $collection->addItem($fakeElement4);
        $collection->deleteElement($fakeElement4);
        $collection->setHeaders($headers);

        $this->assertEquals(3, $collection->count());

        $this->assertEquals($collection->getElement($fakeElement1->getUUid())->getUuid(), $fakeUUid1);
        $this->assertEquals($collection->getElement($fakeElement2->getUUid())->getUuid(), $fakeUUid2);
        $this->assertEquals($collection->getElement($fakeElement3->getUUid())->getUuid(), $fakeUUid3);
        $this->assertCount(2, $collection->getHeaders());
    }
}
