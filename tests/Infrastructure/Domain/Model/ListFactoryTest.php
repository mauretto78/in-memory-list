<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Infrastructure\Domain\Model\ListCollectionFactory;
use PHPUnit\Framework\TestCase;

class ListFactoryTest extends TestCase
{
    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Domain\Model\Exception\CreateListFromEmptyArrayException
     * @expectedExceptionMessage Try to create a collection from an empty array.
     */
    public function it_throws_CreateCollectionFromEmptyArrayException_if_empty_array_is_provided()
    {
        $emptyArray = [];

        $factory = new ListCollectionFactory();
        $factory->create($emptyArray, [], 'fake list');
    }

    /**
     * @test
     * @expectedException \InMemoryList\Infrastructure\Domain\Model\Exception\NotValidKeyElementInListException
     * @expectedExceptionMessage not-existing-id is not a valid key.
     */
    public function gdfgdfdgfgfd()
    {
        $simpleArray = [
            [
                'id' => 123,
                'title' => 'Lorem Ipsum',
                'category-id' => 27,
                'category' => 'holiday',
                'rate' => 4,
            ],
            [
                'id' => 124,
                'title' => 'Neque porro quisquam',
                'category-id' => 28,
                'category' => 'last minute',
                'rate' => 5,
            ],
            [
                'id' => 125,
                'title' => 'Ipso facto',
                'category-id' => 28,
                'category' => 'last minute',
                'rate' => 1,
            ],
            [
                'id' => 126,
                'title' => 'Ipse dixit',
                'category-id' => 27,
                'category' => 'holiday',
                'rate' => 3,
            ],
            [
                'id' => 127,
                'title' => 'Dolor facius',
                'category-id' => 27,
                'category' => 'holiday',
                'rate' => 5,
            ],
        ];

        $factory = new ListCollectionFactory();
        $factory->create($simpleArray, [], 'fake-list', 'not-existing-id');
    }

    /**
     * @test
     */
    public function it_creates_list_from_simple_array()
    {
        $simpleArray = [
            [
                'id' => 123,
                'title' => 'Lorem Ipsum',
                'category-id' => 27,
                'category' => 'holiday',
                'rate' => 4,
            ],
            [
                'id' => 124,
                'title' => 'Neque porro quisquam',
                'category-id' => 28,
                'category' => 'last minute',
                'rate' => 5,
            ],
            [
                'id' => 125,
                'title' => 'Ipso facto',
                'category-id' => 28,
                'category' => 'last minute',
                'rate' => 1,
            ],
            [
                'id' => 126,
                'title' => 'Ipse dixit',
                'category-id' => 27,
                'category' => 'holiday',
                'rate' => 3,
            ],
            [
                'id' => 127,
                'title' => 'Dolor facius',
                'category-id' => 27,
                'category' => 'holiday',
                'rate' => 5,
            ],
        ];
        $factory = new ListCollectionFactory();
        $imList = $factory->create($simpleArray, [], 'fake list');

        $this->assertInstanceOf(ListCollection::class, $imList);
        $this->assertEquals(5, $imList->count());
        $this->assertEquals('fake-list', $imList->getUuid());
        $this->assertNull($imList->getHeaders());
    }

    /**
     * @test
     */
    public function it_creates_list_from_a_parsed_json()
    {
        $headers = [
            'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
            'hash' => 'ec457d0a974c48d5685a7efa03d137dc8bbde7e3',
        ];

        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../../examples/files/users.json'));
        $factory = new ListCollectionFactory();
        $imList = $factory->create($parsedArrayFromJson, $headers, 'fake list from json object');

        $this->assertInstanceOf(ListCollection::class, $imList);
        $this->assertEquals(10, $imList->count());
        $this->assertEquals('fake-list-from-json-object', $imList->getUuid());
        $this->assertCount(2, $imList->getHeaders());
    }
}
