<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<assistenza@easy-grafica.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Application;

use InMemoryList\Application\Exception\EmptyCollectionException;
use InMemoryList\Application\Exception\NotValidKeyElementInCollectionException;
use InMemoryList\Application\Exception\NotValidOperatorException;
use InMemoryList\Application\Exception\NotValidSortingOperatorException;
use InMemoryList\Domain\Model\ListElement;

class QueryBuilder
{
    /**
     * @var array
     */
    private $query;

    /**
     * @var array
     */
    private $orderBy;

    /**
     * @var array
     */
    private $collection;

    /**
     * IMListElementCollectionQueryBuilder constructor.
     *
     * @param $collection
     */
    public function __construct($collection)
    {
        $this->_setCollection($collection);
    }

    /**
     * @param $collection
     *
     * @throws EmptyCollectionException
     */
    public function _setCollection($collection)
    {
        if (empty($collection)) {
            throw new EmptyCollectionException('Empty collection provided.');
        }

        $this->collection = $collection;
    }

    /**
     * @param $key
     * @param $value
     * @param string $operator
     *
     * @return $this
     *
     * @throws NotValidOperatorException
     */
    public function addCriteria($key, $value, $operator = '=')
    {
        $allowedOperators = ['=', '>', '<', '<=', '>=', '!=', 'IN'];

        if (!in_array($operator, $allowedOperators)) {
            throw new NotValidOperatorException($operator.' is not a valid operator.');
        }

        $this->query[] = [
            'key' => $key,
            'value' => $value,
            'operator' => $operator,
        ];

        return $this;
    }

    /**
     * @param $key
     * @param string $order
     *
     * @return $this
     *
     * @throws NotValidSortingOperatorException
     */
    public function orderBy($key, $order = 'ASC')
    {
        $allowedSortingOperators = ['ASC', 'DESC'];

        if (!in_array($order, $allowedSortingOperators)) {
            throw new NotValidSortingOperatorException($order.' is not a valid sorting operator.');
        }

        $this->orderBy = [
            'key' => $key,
            'order' => $order,
        ];

        return $this;
    }

    /**
     * @return array|mixed
     */
    public function getResults()
    {
        $results = [];
        $singleQueryResults = [];
        $i = 0;

        if (count($this->query)) {
            foreach ($this->query as $criteria) {
                $singleQueryResults[] = $this->_filter(
                    function ($element) use ($criteria) {
                        $value = $this->_getListElementValueFromKey(unserialize($element), $criteria['key']);

                        switch ($criteria['operator']) {
                            case '>':
                                return $value > $criteria['value'];
                                break;

                            case '<':
                                return $value < $criteria['value'];
                                break;

                            case '<=':
                                return $value <= $criteria['value'];
                                break;

                            case '>=':
                                return $value >= $criteria['value'];
                                break;

                            case '!=':
                                return $value !== $criteria['value'];
                                break;

                            case 'IN':
                                return strpos($value, $criteria['value']) !== false;
                                break;

                            default:
                                return $value === $criteria['value'];
                                break;
                        }

                    }
                );

                // use array_intersect_key
                if ($i > 0) {
                    $results = array_intersect_key($singleQueryResults[$i], $singleQueryResults[$i - 1]);
                } else {
                    $results = $singleQueryResults[0];
                }

                ++$i;
            }
        } else {
            $results = $this->collection;
        }

        if (count($this->orderBy)) {
            usort($results, [$this, '_compareStrings']);

            if ($this->orderBy['order'] === 'DESC') {
                $results = array_reverse($results);
            }
        }

        return $results;
    }

    /**
     * @param ListElement $element
     * @param $key
     *
     * @return mixed
     *
     * @throws NotValidKeyElementInCollectionException
     */
    private function _getListElementValueFromKey(ListElement $element, $key)
    {
        if ((is_object($element->getBody()) and !isset($element->getBody()->{$key})) or (is_array($element->getBody()) and !isset($element->getBody()[$key]))) {
            throw new NotValidKeyElementInCollectionException($key.' is not a valid key.');
        }

        return is_object($element->getBody()) ? $element->getBody()->{$key} : $element->getBody()[$key];
    }

    /**
     * @param $a
     * @param $b
     *
     * @return int
     */
    private function _compareStrings($a, $b)
    {
        $valueA = $this->_getListElementValueFromKey(unserialize($a), $this->orderBy['key']);
        $valueB = $this->_getListElementValueFromKey(unserialize($b), $this->orderBy['key']);

        if ($valueA === $valueB) {
            return 0;
        }

        return ($valueA < $valueB) ? -1 : 1;
    }

    /**
     * @param callable $fn
     *
     * @return array|ø
     */
    private function _filter(callable $fn)
    {
        return array_filter(
            array_map(
                function ($data) {
                    return $data;
                },
                $this->collection
            ),
            $fn
        );
    }
}
