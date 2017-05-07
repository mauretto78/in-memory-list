<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use PHPUnit\Framework\TestCase;
use InMemoryList\Domain\Model\ListElementUuId;

class ListElementUuIdTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_create_the_entity()
    {
        $imListElementUUId = new ListElementUuId('12345');
        $imListElementUUIdToString = $imListElementUUId;

        $this->assertInstanceOf(ListElementUuId::class, $imListElementUUId);
        $this->assertEquals($imListElementUUIdToString, '12345');
    }
}
