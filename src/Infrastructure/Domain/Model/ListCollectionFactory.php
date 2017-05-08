<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
namespace InMemoryList\Infrastructure\Domain\Model;

use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListCollectionUuid;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Domain\Model\Contracts\ListFactory as Factory;
use InMemoryList\Infrastructure\Domain\Model\Exception\CreateCollectionFromEmptyArrayException;
use InMemoryList\Infrastructure\Domain\Model\Exception\NotValidKeyElementInCollectionException;

class ListCollectionFactory implements Factory
{
    /**
     * @param array $elements
     * @param null  $uuid
     * @param null  $elementId
     *
     * @return ListCollection
     *
     * @throws CreateCollectionFromEmptyArrayException
     */
    public function create(array $elements, $uuid = null, $elementId = null)
    {
        if (empty($elements)) {
            throw new CreateCollectionFromEmptyArrayException('Try to create a collection from an empty array.');
        }

        $collectionUuid = new ListCollectionUuid($uuid);
        $collection = new ListCollection($collectionUuid);

        foreach ($elements as $element) {
            $e = ($elementId) ? (string) $this->_getValueFromKey($element, $elementId) : null;
            $elementUuid = new ListElementUuid($e);
            $collection->addItem(new ListElement($elementUuid, $element));
        }

        return $collection;
    }

    /**
     * @param $element
     * @param $key
     *
     * @return mixed
     *
     * @throws NotValidKeyElementInCollectionException
     */
    private function _getValueFromKey($element, $key)
    {
        if ((is_object($element) and !isset($element->{$key})) or (is_array($element) and !isset($element[$key]))) {
            throw new NotValidKeyElementInCollectionException($key.' is not a valid key.');
        }

        return is_object($element) ? $element->{$key} : $element[$key];
    }
}
