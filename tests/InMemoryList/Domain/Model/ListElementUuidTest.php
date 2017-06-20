<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use InMemoryList\Domain\Model\ListElementUuid;
use PHPUnit\Framework\TestCase;

class ListElementUuidTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_create_the_entity()
    {
        $listElementUuid = new ListElementUuid('12345');

        $this->assertInstanceOf(ListElementUuid::class, $listElementUuid);
        $this->assertEquals((string) $listElementUuid, '12345');
    }
}
