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
        QueryBuilder::create([]);
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exceptions\NotValidOperatorException
     * @expectedExceptionMessage wrong operator is not a valid operator.
     */
    public function it_throws_NotValidOperatorQueryBuilderException_if_an_invalid_operator_is_provided()
    {
        $this->client->getRepository()->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
            'element-uuid' => 'id',
        ]);

        $queryBuilder = QueryBuilder::create($this->client->getRepository()->findListByUuid('user-list'));
        $queryBuilder->addCriteria('name', 'Ervin Howell', 'wrong operator');
        $this->assertCount(1, $queryBuilder->getResults());

        $this->client->getRepository()->flush();
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exceptions\NotValidKeyElementInListException
     * @expectedExceptionMessage not-existing-key is not a valid key.
     */
    public function it_throws_NotValidKeyElementInCollectionException_if_a_not_valid_element_key_is_provided()
    {
        $this->client->getRepository()->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
        ]);

        $queryBuilder = QueryBuilder::create($this->client->getRepository()->findListByUuid('user-list'));
        $queryBuilder->addCriteria('not-existing-key', 'Ervin Howell');
        $this->assertCount(1, $queryBuilder->getResults());

        $this->client->getRepository()->flush();
    }

    /**
     * @test
     * @expectedException \InMemoryList\Application\Exceptions\NotValidSortingOperatorException
     * @expectedExceptionMessage not wrong sorting operator is not a valid sorting operator.
     */
    public function it_throws_NotValidSortingOperatorException_if_an_invalid_sorting_operator_is_provided()
    {
        $this->client->getRepository()->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
        ]);

        $queryBuilder = QueryBuilder::create($this->client->getRepository()->findListByUuid('user-list'));
        $queryBuilder
            ->addCriteria('name', 'Ervin Howell')
            ->orderBy('name', 'not wrong sorting operator');
        $this->assertCount(1, $queryBuilder->getResults());

        $this->client->getRepository()->flush();
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage string must be an integer.
     */
    public function it_throws_InvalidArgumentException_if_an_invalid_offset_is_provided()
    {
        $this->client->getRepository()->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
            'element-uuid' => 'id',
        ]);

        $queryBuilder = QueryBuilder::create($this->client->getRepository()->findListByUuid('user-list'));
        $queryBuilder->limit(123, 'string');

        $this->client->getRepository()->flush();
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage string must be an integer.
     */
    public function it_throws_InvalidArgumentException_if_an_invalid_length_is_provided()
    {
        $this->client->getRepository()->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
            'element-uuid' => 'id',
        ]);

        $queryBuilder = QueryBuilder::create($this->client->getRepository()->findListByUuid('user-list'));
        $queryBuilder->limit('string', 13);

        $this->client->getRepository()->flush();
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage 432 must be an < than 13.
     */
    public function it_throws_InvalidArgumentException_if_an_offset_is_grater_than_length_is_provided()
    {
        $this->client->getRepository()->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
            'element-uuid' => 'id',
        ]);

        $queryBuilder = QueryBuilder::create($this->client->getRepository()->findListByUuid('user-list'));
        $queryBuilder->limit(432, 13);

        $this->client->getRepository()->flush();
    }

    /**
     * @test
     */
    public function it_should_query_sorting_and_retrieve_data_from_in_memory_collection()
    {
        $this->client->getRepository()->flush();
        $this->client->create($this->parsedUserArray, [
            'uuid' => 'user list',
            'element-uuid' => 'id',
       ]);

        $userCollection = $this->client->getRepository()->findListByUuid('user-list');

        // perform a simple query
        $queryBuilder = QueryBuilder::create($userCollection);
        $queryBuilder->addCriteria('name', 'Ervin Howell');
        $this->assertCount(1, $queryBuilder->getResults());

        // perform a > query
        $queryBuilder2 = QueryBuilder::create($userCollection);
        $queryBuilder2->addCriteria('id', '3', '>');
        $this->assertCount(7, $queryBuilder2->getResults());

        // perform a < query
        $queryBuilder3 = QueryBuilder::create($userCollection);
        $queryBuilder3->addCriteria('id', '3', '<');
        $this->assertCount(2, $queryBuilder3->getResults());

        // perform a <= query
        $queryBuilder4 = QueryBuilder::create($userCollection);
        $queryBuilder4->addCriteria('id', '3', '<=');
        $this->assertCount(3, $queryBuilder4->getResults());

        // perform a >= query
        $queryBuilder5 = QueryBuilder::create($userCollection);
        $queryBuilder5->addCriteria('id', '3', '>=');
        $this->assertCount(8, $queryBuilder5->getResults());

        // perform a != query
        $queryBuilder6 = QueryBuilder::create($userCollection);
        $queryBuilder6->addCriteria('name', 'Ervin Howell', '!=');
        $this->assertCount(9, $queryBuilder6->getResults());

        // perform a CONTAINS query
        $queryBuilder7 = QueryBuilder::create($userCollection);
        $queryBuilder7->addCriteria('name', 'clement', 'CONTAINS');
        $this->assertCount(2, $queryBuilder7->getResults());

        // perform a ARRAY query
        $queryBuilder8 = QueryBuilder::create($userCollection);
        $queryBuilder8->addCriteria('name', ['Leanne Graham', 'Ervin Howell', 'Clementine Bauch'], 'ARRAY');
        $this->assertCount(3, $queryBuilder8->getResults());

        // perform a ARRAY_INVERSED query
        $queryBuilder9 = QueryBuilder::create($userCollection);
        $queryBuilder9->addCriteria('tags', 'pinapple', 'ARRAY_INVERSED');
        $this->assertCount(9, $queryBuilder9->getResults());

        // perform a concatenated query
        $queryBuilder10 = QueryBuilder::create($userCollection);
        $queryBuilder10
            ->addCriteria('name', 'Clement', 'CONTAINS')
            ->addCriteria('id', '6', '>=')
        ;

        $this->assertCount(1, $queryBuilder10->getResults());

        // perform a concatenated query with order by and check that first element of array is the expected one
        $queryBuilder11 = QueryBuilder::create($userCollection);
        $queryBuilder11->orderBy('id', 'DESC');
        $results = $queryBuilder11->getResults();
        $firstResult = $results[0];
        $this->assertEquals($firstResult->id, '10');

        // perform a concatenated query with order by and check that first element of array is the expected one
        $this->client->create($this->parsedPostsArray, [
            'uuid' => 'post-list',
            'element-uuid' => 'id',
        ]);

        $postCollection = $this->client->getRepository()->findListByUuid('post-list');

        $queryBuilder12 = QueryBuilder::create($postCollection);
        $queryBuilder12->orderBy('userId');
        $results = $queryBuilder12->getResults();

        // perform a concatenated query with limit
        $queryBuilder13 = QueryBuilder::create($userCollection);
        $queryBuilder13->limit(0, 5);
        $this->assertCount(5, $queryBuilder13->getResults());

        $this->client->getRepository()->flush();
    }
}
