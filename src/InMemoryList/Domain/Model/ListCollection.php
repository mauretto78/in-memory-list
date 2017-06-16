<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Domain\Model;

use InMemoryList\Domain\Helper\ListElementConsistencyChecker;
use InMemoryList\Domain\Model\Exceptions\ListElementDuplicateKeyException;
use InMemoryList\Domain\Model\Exceptions\ListElementKeyDoesNotExistException;
use InMemoryList\Domain\Model\Exceptions\ListElementNotConsistentException;

class ListCollection implements \Countable
{
    /**
     * @var array
     */
    private $elements;

    /**
     * @var ListCollectionUuid
     */
    private $uuid;

    /**
     * @var array
     */
    private $headers;

    /**
     * IMListElementCollection constructor.
     *
     * @param ListCollectionUuid $uuid
     * @param array              $elements
     */
    public function __construct(ListCollectionUuid $uuid, array $elements = [])
    {
        $this->setUuid($uuid);
        $this->setElements($elements);
    }

    /**
     * @param ListCollectionUuid $uuid
     */
    private function setUuid(ListCollectionUuid $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return ListCollectionUuid
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param array $elements
     */
    private function setElements($elements)
    {
        $this->elements = $elements;
    }

    /**
     * @param ListElementUuid $uuid
     *
     * @return bool
     */
    public function hasElement(ListElementUuid $uuid)
    {
        return isset($this->elements[$uuid->getUuid()]);
    }

    /**
     * @param ListElement $element
     *
     * @throws ListElementDuplicateKeyException
     * @throws ListElementNotConsistentException
     */
    public function addElement(ListElement $element)
    {
        if ($this->hasElement($element->getUuid())) {
            throw new ListElementDuplicateKeyException('Key '.$element->getUuid()->getUuid().' already in use.');
        }

        if (!ListElementConsistencyChecker::isConsistent($element, $this->elements)) {
            throw new ListElementNotConsistentException('Element '.$element->getUuid()->getUuid().' is not consistent with list data.');
        }

        $this->elements[$element->getUuid()->getUuid()] = $element;
    }

    /**
     * @param ListElement $element
     *
     * @throws ListElementKeyDoesNotExistException
     */
    public function deleteElement(ListElement $element)
    {
        if (!$this->hasElement($element->getUuid())) {
            throw new ListElementKeyDoesNotExistException('Invalid key '.$element->getUuid()->getUuid());
        }

        unset($this->elements[$element->getUuid()->getUuid()]);
    }

    /**
     * @param ListElementUuid $uuid
     *
     * @return mixed
     *
     * @throws ListElementKeyDoesNotExistException
     */
    public function getElement(ListElementUuid $uuid)
    {
        if (!$this->hasElement($uuid)) {
            throw new ListElementKeyDoesNotExistException('Invalid key '.$uuid->getUuid());
        }

        return $this->elements[$uuid->getUuid()];
    }

    /**
     * @return array
     */
    public function getElements()
    {
        return $this->elements;
    }

    /**
     * @param $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->elements);
    }
}
