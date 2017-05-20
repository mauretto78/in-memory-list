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
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Domain\Model\ListElement;

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

        $cacheListElementUniquieId = new ListElementUuid();
        $cacheListElement = new ListElement($cacheListElementUniquieId, $body);

        $this->assertInstanceOf(ListElement::class, $cacheListElement);
        $this->assertArrayHasKey('test', unserialize($cacheListElement->getBody()));
        $this->assertArrayHasKey('test2', unserialize($cacheListElement->getBody()));
        $this->assertArrayHasKey('test3', unserialize($cacheListElement->getBody()));
    }
}
