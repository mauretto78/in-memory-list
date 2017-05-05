<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<assistenza@easy-grafica.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Domain\Model;

use InMemoryList\Domain\Model\Exception\ListElementDuplicateKeyException;
use InMemoryList\Domain\Model\Exception\ListElementKeyDoesNotExistException;

class ListCollection implements \Countable
{
    /**
     * @var array
     */
    private $items;

    /**
     * @var ListCollectionUuId
     */
    private $uuid;

    /**
     * IMListElementCollection constructor.
     *
     * @param ListCollectionUuId $uuid
     * @param array              $items
     */
    public function __construct(ListCollectionUuId $uuid, array $items = [])
    {
        $this->_setUuid($uuid);
        $this->_setItems($items);
    }

    /**
     * @param ListCollectionUuId $uuid
     */
    private function _setUuid(ListCollectionUuId $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return ListCollectionUuId
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param array $items
     */
    private function _setItems($items)
    {
        $this->items = $items;
    }

    /**
     * @param ListElementUuId $uuid
     *
     * @return bool
     */
    public function hasItem(ListElementUuId $uuid)
    {
        return isset($this->items[$uuid->getUuid()]);
    }

    /**
     * @param ListElement $element
     *
     * @throws ListElementDuplicateKeyException
     */
    public function addItem(ListElement $element)
    {
        if ($this->hasItem($element->getUuid())) {
            throw new ListElementDuplicateKeyException('Key '.$element->getUuid()->getUuid().' already in use.');
        }

        $this->items[$element->getUuid()->getUuid()] = $element;
    }

    /**
     * @param ListElement $element
     *
     * @throws ListElementKeyDoesNotExistException
     */
    public function deleteElement(ListElement $element)
    {
        if (!$this->hasItem($element->getUuid())) {
            throw new ListElementKeyDoesNotExistException('Invalid key '.$element->getUuid()->getUuid());
        }

        unset($this->items[$element->getUuid()->getUuid()]);
    }

    /**
     * @param ListElementUuId $uuid
     *
     * @return mixed
     *
     * @throws ListElementKeyDoesNotExistException
     */
    public function getElement(ListElementUuId $uuid)
    {
        if (!$this->hasItem($uuid)) {
            throw new ListElementKeyDoesNotExistException('Invalid key '.$uuid->getUuid());
        }

        return $this->items[$uuid->getUuid()];
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->items;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }
}
