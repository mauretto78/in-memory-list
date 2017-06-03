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
use InMemoryList\Tests\BaseTestCase;

class QueryBuilderTest extends BaseTestCase
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
        parent::setUp();
        $this->client = new Client('redis', $this->redis_parameters);
        $this->parsedPostsArray = json_decode(file_get_contents(__DIR__.'/../../../examples/files/posts.json'));
        $this->parsedUserArray = json_decode(file_get_contents(__DIR__.'/../../../examples/files/users.json'));
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exceptions\EmptyListException
     */
    public function it_throws_EmptyCollectionException_if_an_empty_collection_is_provided()
    {
        new QueryBuilder([]);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exceptions\NotValidOperatorException
     * @expectedExceptionMessage wrong operator is not a valid operator.
     */
    public function it_throws_NotValidOperatorQueryBuilderException_if_an_invalid_operator_is_provided()
    {
        $this->client->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
            'element-uuid' => 'id'
        ]);

        $qb = new QueryBuilder($this->client->findListByUuid('user-list'));
        $qb->addCriteria('name', 'Ervin Howell', 'wrong operator');
        $this->assertCount(1, $qb->getResults());

        $this->client->flush();
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exceptions\NotValidKeyElementInListException
     * @expectedExceptionMessage not-existing-key is not a valid key.
     */
    public function it_throws_NotValidKeyElementInCollectionException_if_a_not_valid_element_key_is_provided()
    {
        $this->client->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list'
        ]);

        $qb = new QueryBuilder($this->client->findListByUuid('user-list'));
        $qb->addCriteria('not-existing-key', 'Ervin Howell');
        $this->assertCount(1, $qb->getResults());

        $this->client->flush();
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exceptions\NotValidSortingOperatorException
     * @expectedExceptionMessage not wrong sorting operator is not a valid sorting operator.
     */
    public function it_throws_NotValidSortingOperatorException_if_an_invalid_sorting_operator_is_provided()
    {
        $this->client->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list'
        ]);

        $qb = new QueryBuilder($this->client->findListByUuid('user-list'));
        $qb
            ->addCriteria('name', 'Ervin Howell')
            ->orderBy('name', 'not wrong sorting operator');
        $this->assertCount(1, $qb->getResults());

        $this->client->flush();
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage string must be an integer.
     */
    public function it_throws_InvalidArgumentException_if_an_invalid_offset_is_provided()
    {
        $this->client->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
            'element-uuid' => 'id'
        ]);

        $qb = new QueryBuilder($this->client->findListByUuid('user-list'));
        $qb->limit(123, 'string');

        $this->client->flush();
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage string must be an integer.
     */
    public function it_throws_InvalidArgumentException_if_an_invalid_length_is_provided()
    {
        $this->client->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
            'element-uuid' => 'id'
        ]);

        $qb = new QueryBuilder($this->client->findListByUuid('user-list'));
        $qb->limit('string', 13);

        $this->client->flush();
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage 432 must be an < than 13.
     */
    public function it_throws_InvalidArgumentException_if_an_offset_is_grater_than_length_is_provided()
    {
        $this->client->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
            'element-uuid' => 'id'
        ]);

        $qb = new QueryBuilder($this->client->findListByUuid('user-list'));
        $qb->limit(432, 13);

        $this->client->flush();
    }

    /**
     * @test
     */
    public function it_should_query_sorting_and_retrieve_data_from_in_memory_collection()
    {
        $this->client->flush();
        $userCollection = $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
            'element-uuid' => 'id'
        ]);

        // perform a simple query
        $qb = new QueryBuilder($userCollection);
        $qb->addCriteria('name', 'Ervin Howell');
        $this->assertCount(1, $qb->getResults());

        // perform a > query
        $qb2 = new QueryBuilder($userCollection);
        $qb2->addCriteria('id', '3', '>');
        $this->assertCount(7, $qb2->getResults());

        // perform a < query
        $qb3 = new QueryBuilder($userCollection);
        $qb3->addCriteria('id', '3', '<');
        $this->assertCount(2, $qb3->getResults());

        // perform a <= query
        $qb4 = new QueryBuilder($userCollection);
        $qb4->addCriteria('id', '3', '<=');
        $this->assertCount(3, $qb4->getResults());

        // perform a >= query
        $qb5 = new QueryBuilder($userCollection);
        $qb5->addCriteria('id', '3', '>=');
        $this->assertCount(8, $qb5->getResults());

        // perform a != query
        $qb6 = new QueryBuilder($userCollection);
        $qb6->addCriteria('name', 'Ervin Howell', '!=');
        $this->assertCount(9, $qb6->getResults());

        // perform a CONTAINS query
        $qb7 = new QueryBuilder($userCollection);
        $qb7->addCriteria('name', 'clement', 'CONTAINS');
        $this->assertCount(2, $qb7->getResults());

        // perform a ARRAY query
        $qb8 = new QueryBuilder($userCollection);
        $qb8->addCriteria('name', ['Leanne Graham', 'Ervin Howell', 'Clementine Bauch'], 'ARRAY');
        $this->assertCount(3, $qb8->getResults());

        // perform a ARRAY_INVERSED query
        $qb9 = new QueryBuilder($userCollection);
        $qb9->addCriteria('tags', 'pinapple', 'ARRAY_INVERSED');
        $this->assertCount(9, $qb9->getResults());

        // perform a concatenated query
        $qb10 = new QueryBuilder($userCollection);
        $qb10
            ->addCriteria('name', 'Clement', 'CONTAINS')
            ->addCriteria('id', '6', '>=')
        ;
        $this->assertCount(1, $qb10->getResults());

        // perform a concatenated query with order by and check that first element of array is the expected one
        $qb11 = new QueryBuilder($userCollection);
        $qb11->orderBy('id', 'DESC');
        $results = $qb11->getResults();
        $firstResult = $this->client->item($results[0]);
        $this->assertEquals($firstResult->id, '10');

        // perform a concatenated query with order by and check that first element of array is the expected one
        $postCollection = $this->client->create($this->parsedPostsArray, [], 'post-list', 'id');
        $qb12 = new QueryBuilder($postCollection);
        $qb12->orderBy('userId');
        $results = $qb12->getResults();

        // perform a concatenated query with limit
        $qb13 = new QueryBuilder($userCollection);
        $qb13->limit(0, 5);
        $this->assertCount(5, $qb13->getResults());

        $this->client->flush();
    }
}
