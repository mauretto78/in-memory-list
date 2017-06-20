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
use InMemoryList\Domain\Model\ListElementUuid;
use PHPUnit\Framework\TestCase;

class ListElementTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_create_the_entity()
    {
        $body = [
            'test' => 'Lorem ipsum',
            'test2' => 'Dolor facet',
            'test3' => 'Ipsum facium',
        ];

        $listElementUuid = new ListElementUuid();
        $listElement = new ListElement($listElementUuid, $body);

        $this->assertInstanceOf(ListElement::class, $listElement);
        $this->assertArrayHasKey('test', unserialize($listElement->getBody()));
        $this->assertArrayHasKey('test2', unserialize($listElement->getBody()));
        $this->assertArrayHasKey('test3', unserialize($listElement->getBody()));
    }
}
