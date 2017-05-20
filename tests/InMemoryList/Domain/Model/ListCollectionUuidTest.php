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
use InMemoryList\Domain\Model\ListCollectionUuid;
use PHPUnit\Framework\TestCase;

class ListCollectionUuidTest extends TestCase
{
    /**
     * @test
     * @expectedException \InMemoryList\Domain\Model\Exceptions\ListCollectionNotAllowedUuidException
     * @expectedExceptionMessage You can't use statistics in your uuid.
     */
    public function it_throws_ListCollectionNotAllowedUuidException_if_attempt_to_create_a_ListCollectionUuid_with_an_not_allowed_name()
    {
        new ListCollectionUuid(ListRepository::STATISTICS);
    }

    /**
     * @test
     */
    public function it_should_create_the_entity()
    {
        $imListElementCollectionUUId = new ListCollectionUuid();
        $imListElementCollectionUUIdToString = $imListElementCollectionUUId;

        $this->assertInstanceOf(ListCollectionUuid::class, $imListElementCollectionUUId);
        $this->assertEquals($imListElementCollectionUUIdToString, $imListElementCollectionUUId->getUuid());
    }
}
