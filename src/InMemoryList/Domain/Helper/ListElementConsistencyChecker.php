<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Domain\Helper;

use InMemoryList\Domain\Model\ListElement;

class ListElementConsistencyChecker
{
    /**
     * @param $listElement
     * @param array $list
     * @return bool
     */
    public static function isConsistent($listElement, array $list = [])
    {
        // empty list
        if(!count($list)) {
            return true;
        }

        $listElement = self::_getBodyOfListElement($listElement);

        // list element is a string
        if(is_string($listElement)){
            return true;
        }

        // list element is an array or an object
        if(is_array($listElement) or is_object($listElement)){
            if (count(array_diff_key(
                (array)$listElement,
                (array) self::_getBodyOfFirstElementOfList($list)
            ))) {
                return false;
            }

            return true;
        }
    }

    /**
     * @param $list
     * @return mixed
     */
    private static function _getBodyOfFirstElementOfList($list)
    {
        $firstElementKey = @array_keys($list)[0];
        $firstElement = self::_getBodyOfListElement(@$list[$firstElementKey]);

        return $firstElement;
    }

    /**
     * @param $listElement
     * @return mixed
     */
    private static function _getBodyOfListElement($listElement)
    {
        if(($listElement instanceof ListElement)) {
            return unserialize($listElement->getBody());
        }

        if(is_object($listElement)) {
            return $listElement;
        }

        if(is_string($listElement)) {
            return unserialize($listElement);
        }
    }
}
