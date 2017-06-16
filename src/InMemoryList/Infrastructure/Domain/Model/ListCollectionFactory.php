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
use InMemoryList\Infrastructure\Domain\Model\Exceptions\CreateListFromEmptyArrayException;
use InMemoryList\Infrastructure\Domain\Model\Exceptions\NotValidKeyElementInListException;

class ListCollectionFactory implements Factory
{
    /**
     * @param array $elements
     * @param array $headers
     * @param null  $uuid
     * @param null  $elementUuid
     *
     * @return ListCollection
     *
     * @throws CreateListFromEmptyArrayException
     */
    public function create(array $elements, array $headers = [], $uuid = null, $elementUuid = null)
    {
        if (empty($elements)) {
            throw new CreateListFromEmptyArrayException('Try to create a collection from an empty array.');
        }

        $listUuid = new ListCollectionUuid($uuid);
        $list = new ListCollection($listUuid);

        foreach ($elements as $element) {
            $newElementUuid = new ListElementUuid(($elementUuid) ? (string) $this->_getValueFromKey($element, $elementUuid) : null);
            $list->addElement(new ListElement($newElementUuid, $element));
        }

        if ($headers) {
            $list->setHeaders($headers);
        }

        return $list;
    }

    /**
     * @param $element
     * @param $key
     *
     * @return mixed
     *
     * @throws NotValidKeyElementInListException
     */
    private function _getValueFromKey($element, $key)
    {
        if ((is_object($element) && !$this->_getValueKeyFromObject($element, $key)) || (is_array($element) && !isset($element[$key]))) {
            $getterName = 'get'.str_replace(' ', '', ucwords($key));

            throw new NotValidKeyElementInListException($key.' is not a valid key. If your elements are Entities class, please check if you implement '.$getterName.'() method.');
        }

        return is_object($element) ? $this->_getValueKeyFromObject($element, $key) : $element[$key];
    }

    /**
     * @param $element
     * @param $key
     *
     * @return bool
     */
    private function _getValueKeyFromObject($element, $key)
    {
        if (!$element instanceof \stdClass) {
            $getterName = 'get'.str_replace(' ', '', ucwords($key));

            return (method_exists($element, $getterName)) ? $element->$getterName() : false;
        }

        return (isset($element->{$key})) ? $element->{$key} : false;
    }
}
