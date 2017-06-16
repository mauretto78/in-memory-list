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
        if (!count($list)) {
            return true;
        }

        $listElement = self::getBodyOfListElement($listElement);

        // list element is a string
        if (is_string($listElement)) {
            return true;
        }

        // list element is an array or an object
        if (is_array($listElement) || is_object($listElement)) {
            if (count(array_diff_key(
                (array) $listElement,
                (array) self::getBodyOfFirstElementOfList($list)
            ))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $list
     *
     * @return mixed
     */
    private static function getBodyOfFirstElementOfList($list)
    {
        $firstElementKey = array_keys($list)[0];
        $firstElement = self::getBodyOfListElement($list[$firstElementKey]);

        return $firstElement;
    }

    /**
     * @param $listElement
     * @return mixed
     */
    private static function getBodyOfListElement($listElement)
    {
        if (($listElement instanceof ListElement)) {
            return unserialize($listElement->getBody());
        }

        if (is_string($listElement)) {
            return (false !== @unserialize($listElement)) ? unserialize($listElement) : $listElement;
        }

        return $listElement;
    }
}
