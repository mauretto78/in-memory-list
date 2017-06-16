<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Application;

use InMemoryList\Application\Exceptions\EmptyListException;
use InMemoryList\Application\Exceptions\NotValidKeyElementInListException;
use InMemoryList\Application\Exceptions\NotValidOperatorException;
use InMemoryList\Application\Exceptions\NotValidSortingOperatorException;

class QueryBuilder
{
    /**
     * @var array
     */
    private $criteria;

    /**
     * @var array
     */
    private $limit;

    /**
     * @var array
     */
    private $orderBy;

    /**
     * IMListElementCollectionQueryBuilder constructor.
     *
     * @param $list
     */
    public function __construct($list)
    {
        $this->setCollection($list);
    }

    /**
     * @param $list
     *
     * @throws EmptyListException
     */
    public function setCollection($list)
    {
        if (empty($list)) {
            throw new EmptyListException('Empty collection provided.');
        }

        $this->collection = $list;
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
        $allowedOperators = ['=', '>', '<', '<=', '>=', '!=', 'ARRAY', 'ARRAY_INVERSED', 'CONTAINS'];

        if (!in_array($operator, $allowedOperators)) {
            throw new NotValidOperatorException($operator.' is not a valid operator.');
        }

        $this->criteria[] = [
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
        $allowedOperators = ['ASC', 'DESC'];

        if (!in_array($order, $allowedOperators)) {
            throw new NotValidSortingOperatorException($order.' is not a valid sorting operator.');
        }

        $this->orderBy = [
            'key' => $key,
            'order' => $order,
        ];

        return $this;
    }

    /**
     * @param $offset
     * @param $length
     *
     * @return $this
     */
    public function limit($offset, $length)
    {
        if (!is_integer($offset)) {
            throw new \InvalidArgumentException($offset.' must be an integer.');
        }

        if (!is_integer($length)) {
            throw new \InvalidArgumentException($length.' must be an integer.');
        }

        if ($offset > $length) {
            throw new \InvalidArgumentException($offset.' must be an < than '.$length.'.');
        }

        $this->limit = [
            'offset' => $offset,
            'lenght' => $length,
        ];

        return $this;
    }

    /**
     * @return array|mixed
     */
    public function getResults()
    {
        $results = $this->collection;
        $singleQueryResults = [];
        $counter = 0;

        if (count($this->criteria)) {
            foreach ($this->criteria as $criterion) {
                $singleQueryResults[] = $this->filter(
                    function ($element) use ($criterion) {
                        $value = $this->getListElementValueFromKey(unserialize($element), $criterion['key']);

                        switch ($criterion['operator']) {
                            case '>':
                                return $value > $criterion['value'];
                                break;

                            case '<':
                                return $value < $criterion['value'];
                                break;

                            case '<=':
                                return $value <= $criterion['value'];
                                break;

                            case '>=':
                                return $value >= $criterion['value'];
                                break;

                            case '!=':
                                return $value !== $criterion['value'];
                                break;

                            case 'ARRAY':
                                return in_array($value, (array) $criterion['value']);
                                break;

                            case 'ARRAY_INVERSED':
                                return in_array($criterion['value'], (array) $value);
                                break;

                            case 'CONTAINS':
                                return stripos($value, $criterion['value']) !== false;
                                break;

                            default:
                                return $value === $criterion['value'];
                                break;
                        }
                    }
                );

                $results = $this->returnSingleQueryResult($counter, $singleQueryResults);
                ++$counter;
            }
        }

        if (count($this->orderBy)) {
            usort($results, [$this, 'compareStrings']);

            if ($this->orderBy['order'] === 'DESC') {
                $results = array_reverse($results);
            }
        }

        if (count($this->limit)) {
            $results = array_slice($results, $this->limit['offset'], $this->limit['lenght']);
        }

        return $results;
    }

    /**
     * @param $counter
     * @param $singleQueryResults
     *
     * @return array
     */
    private function returnSingleQueryResult($counter, $singleQueryResults)
    {
        if ($counter > 0) {
            return  array_intersect_key($singleQueryResults[$counter], $singleQueryResults[$counter - 1]);
        }

        return $singleQueryResults[0];
    }

    /**
     * @param $element
     * @param $key
     *
     * @return mixed
     *
     * @throws NotValidKeyElementInListException
     */
    private function getListElementValueFromKey($element, $key)
    {
        if ((is_object($element) && !isset($element->{$key})) || (is_array($element) && !isset($element[$key]))) {
            throw new NotValidKeyElementInListException($key.' is not a valid key.');
        }

        return is_object($element) ? $element->{$key} : $element[$key];
    }

    /**
     * @param $first
     * @param $second
     *
     * @return int
     */
    private function compareStrings($first, $second)
    {
        $valueA = $this->getListElementValueFromKey(unserialize($first), $this->orderBy['key']);
        $valueB = $this->getListElementValueFromKey(unserialize($second), $this->orderBy['key']);

        if ($valueA === $valueB) {
            return 0;
        }

        return ($valueA < $valueB) ? -1 : 1;
    }

    /**
     * @param callable $function
     *
     * @return array|ø
     */
    private function filter(callable $function)
    {
        return array_filter(
            array_map(
                function ($data) {
                    return $data;
                },
                $this->collection
            ),
            $function
        );
    }
}
