<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<assistenza@easy-grafica.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

use InMemoryList\Domain\Model\ListCollectionUuId;
use PHPUnit\Framework\TestCase;

class ListElementCollectionUuIdTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_create_the_entity()
    {
        $imListElementCollectionUUId = new ListCollectionUuId();
        $imListElementCollectionUUIdToString = $imListElementCollectionUUId;

        $this->assertInstanceOf(ListCollectionUuId::class, $imListElementCollectionUUId);
        $this->assertEquals($imListElementCollectionUUIdToString, $imListElementCollectionUUId->getUuid());
    }
}
