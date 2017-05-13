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
use InMemoryList\Application\QueryBuilder;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    /**
     * @var array
     */
    private $parsedUserArray;

    /**
     * @var array
     */
    private $parsedPostsArray;

    /**
     * @var Client
     */
    private $client;

    public function setUp()
    {
        $this->client = new Client();
        $this->parsedPostsArray = json_decode(file_get_contents(__DIR__.'/../../examples/files/posts.json'));
        $this->parsedUserArray = json_decode(file_get_contents(__DIR__.'/../../examples/files/users.json'));
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exception\EmptyListException
     */
    public function it_throws_EmptyCollectionException_if_an_empty_collection_is_provided()
    {
        new QueryBuilder([]);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exception\NotValidOperatorException
     * @expectedExceptionMessage wrong operator is not a valid operator.
     */
    public function it_throws_NotValidOperatorQueryBuilderException_if_an_invalid_operator_is_provided()
    {
        $this->client->flush();
        $this->client->create($this->parsedUserArray, [], 'user list', 'id');

        $qb = new QueryBuilder($this->client->findByUuid('user-list'));
        $qb->addCriteria('name', 'Ervin Howell', 'wrong operator');
        $this->assertCount(1, $qb->getResults());

        $this->client->flush();
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exception\NotValidKeyElementInListException
     * @expectedExceptionMessage not-existing-key is not a valid key.
     */
    public function it_throws_NotValidKeyElementInCollectionException_if_a_not_valid_element_key_is_provided()
    {
        $this->client->flush();
        $list = $this->client->create($this->parsedUserArray, [], 'user list');

        var_dump($list); die();

        $qb = new QueryBuilder($this->client->findByUuid('user-list'));
        $qb->addCriteria('not-existing-key', 'Ervin Howell');
        $this->assertCount(1, $qb->getResults());

        $this->client->flush();
    }
//
//    /**
//     * @test
//     * @expectedException \InMemoryList\Application\Exception\NotValidSortingOperatorException
//     * @expectedExceptionMessage not wrong sorting operator is not a valid sorting operator.
//     */
//    public function it_throws_NotValidSortingOperatorException_if_an_invalid_sorting_operator_is_provided()
//    {
//        $this->client->flush();
//        $this->client->create($this->parsedUserArray, [], 'user list');
//
//        $qb = new QueryBuilder($this->client->findByUUid('user-list'));
//        $qb
//            ->addCriteria('name', 'Ervin Howell')
//            ->orderBy('name', 'not wrong sorting operator');
//        $this->assertCount(1, $qb->getResults());
//
//        $this->client->flush();
//    }
//
//    /**
//     * @test
//     * @expectedException \InvalidArgumentException
//     * @expectedExceptionMessage string must be an integer.
//     */
//    public function it_throws_InvalidArgumentException_if_an_invalid_offset_is_provided()
//    {
//        $this->client->flush();
//        $this->client->create($this->parsedUserArray, [], 'user list', 'id');
//
//        $qb = new QueryBuilder($this->client->findByUUid('user-list'));
//        $qb->limit(123, 'string');
//
//        $this->client->flush();
//    }
//
//    /**
//     * @test
//     * @expectedException \InvalidArgumentException
//     * @expectedExceptionMessage string must be an integer.
//     */
//    public function it_throws_InvalidArgumentException_if_an_invalid_length_is_provided()
//    {
//        $this->client->flush();
//        $this->client->create($this->parsedUserArray, [], 'user list', 'id');
//
//        $qb = new QueryBuilder($this->client->findByUUid('user-list'));
//        $qb->limit('string', 13);
//
//        $this->client->flush();
//    }
//
//    /**
//     * @test
//     * @expectedException \InvalidArgumentException
//     * @expectedExceptionMessage 432 must be an < than 13.
//     */
//    public function it_throws_InvalidArgumentException_if_an_offset_is_grater_than_length_is_provided()
//    {
//        $this->client->flush();
//        $this->client->create($this->parsedUserArray, [], 'user list', 'id');
//
//        $qb = new QueryBuilder($this->client->findByUUid('user-list'));
//        $qb->limit(432, 13);
//
//        $this->client->flush();
//    }
//
//    /**
//     * @test
//     */
//    public function it_should_query_sorting_and_retrieve_data_from_in_memory_collection()
//    {
//        $this->client->flush();
//        $userCollection = $this->client->create($this->parsedUserArray, [], 'user list', 'id');
//
//        // perform a simple query
//        $qb = new QueryBuilder($userCollection);
//        $qb->addCriteria('name', 'Ervin Howell');
//        $this->assertCount(1, $qb->getResults());
//
//        // perform a > query
//        $qb2 = new QueryBuilder($userCollection);
//        $qb2->addCriteria('id', '3', '>');
//        $this->assertCount(7, $qb2->getResults());
//
//        // perform a < query
//        $qb3 = new QueryBuilder($userCollection);
//        $qb3->addCriteria('id', '3', '<');
//        $this->assertCount(2, $qb3->getResults());
//
//        // perform a <= query
//        $qb4 = new QueryBuilder($userCollection);
//        $qb4->addCriteria('id', '3', '<=');
//        $this->assertCount(3, $qb4->getResults());
//
//        // perform a >= query
//        $qb5 = new QueryBuilder($userCollection);
//        $qb5->addCriteria('id', '3', '>=');
//        $this->assertCount(8, $qb5->getResults());
//
//        // perform a != query
//        $qb6 = new QueryBuilder($userCollection);
//        $qb6->addCriteria('name', 'Ervin Howell', '!=');
//        $this->assertCount(9, $qb6->getResults());
//
//        // perform a CONTAINS query
//        $qb7 = new QueryBuilder($userCollection);
//        $qb7->addCriteria('name', 'Clement', 'CONTAINS');
//        $this->assertCount(2, $qb7->getResults());
//
//        // perform a ARRAY query
//        $qb8 = new QueryBuilder($userCollection);
//        $qb8->addCriteria('name', ['Leanne Graham', 'Ervin Howell', 'Clementine Bauch'], 'ARRAY');
//        $this->assertCount(3, $qb8->getResults());
//
//        // perform a concatenated query
//        $qb9 = new QueryBuilder($userCollection);
//        $qb9
//            ->addCriteria('name', 'Clement', 'CONTAINS')
//            ->addCriteria('id', '6', '>=')
//        ;
//        $this->assertCount(1, $qb9->getResults());
//
//        // perform a concatenated query with order by and check that first element of array is the expected one
//        $qb10 = new QueryBuilder($userCollection);
//        $qb10->orderBy('id', 'DESC');
//        $results = $qb10->getResults();
//        $firstResult = $this->client->item($results[0]);
//        $this->assertEquals($firstResult->id, '10');
//
//        // perform a concatenated query with order by and check that first element of array is the expected one
//        $postCollection = $this->client->create($this->parsedPostsArray, [], 'post-list', 'id');
//        $qb11 = new QueryBuilder($postCollection);
//        $qb11->orderBy('userId');
//        $results = $qb11->getResults();
//
//        // perform a concatenated query with limit
//        $qb12 = new QueryBuilder($userCollection);
//        $qb12->limit(0, 5);
//        $this->assertCount(5, $qb12->getResults());
//
//        $this->client->flush();
//    }
}
