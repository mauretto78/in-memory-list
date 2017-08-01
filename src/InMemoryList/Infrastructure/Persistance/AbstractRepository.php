<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Infrastructure\Persistance;

use InMemoryList\Infrastructure\Persistance\Exceptions\ListElementDoesNotExistsException;

abstract class AbstractRepository
{
    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function delete($listUuid)
    {
        $list = $this->findListByUuid($listUuid);

        foreach (array_keys($list) as $elementUuid) {
            $this->deleteElement($listUuid, $elementUuid);
        }
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function existsElement($listUuid, $elementUuid)
    {
        return isset($this->findListByUuid($listUuid)[$elementUuid]);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     *
     * @throws ListElementDoesNotExistsException
     */
    public function findElement($listUuid, $elementUuid)
    {
        if (!$this->existsElement($listUuid, $elementUuid)) {
            throw new ListElementDoesNotExistsException('Cannot retrieve the element '.$elementUuid.' from the collection in memory.');
        }

        return $this->findListByUuid($listUuid)[$elementUuid];
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getChunkSize($listUuid)
    {
        if ($this->existsListInIndex($listUuid)) {
            $index = $this->getIndex($listUuid);

            return $index['chunk-size'];
        }

        return 0;
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getCounter($listUuid)
    {
        if ($this->existsListInIndex($listUuid)) {
            $index = $this->getIndex($listUuid);

            return $index['size'];
        }

        return 0;
    }

    /**
     * @param $listUuid
     *
     * @return bool
     */
    public function existsListInIndex($listUuid)
    {
        return ($this->getIndex($listUuid)) ? true : false;
    }

    /**
     * @param $listUuid
     *
     * @return float
     */
    public function getNumberOfChunks($listUuid)
    {
        if ($this->existsListInIndex($listUuid)) {
            $index = $this->getIndex($listUuid);

            return $index['chunks'];
        }

        return 0;
    }

    /**
     * @param \Datetime|null $from
     * @param \Datetime|null $to
     *
     * @return array
     */
    public function getIndexInRangeDate(\Datetime $from = null, \Datetime $to = null)
    {
        $results = [];

        if ($index = $this->getIndex()) {
            foreach ($index as $key => $item) {
                $unserializedItem = $item;
                $createdOn = $unserializedItem['created_on']->format('Y-m-d');
                $from = $from ? $from->format('Y-m-d') : null;
                $to = $to ? $to->format('Y-m-d') : null;
                $firstStatement = ($from) ? $createdOn >= $from : true;
                $secondStatement = ($to) ? $createdOn <= $to : true;

                if ($firstStatement && $secondStatement) {
                    $results[$key] = $item;
                }
            }
        }

        return $results;
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getTtl($listUuid)
    {
        $index = $this->getIndex($listUuid);
        if ($index['ttl'] && $index['ttl'] > 0) {
            $now = new \DateTime('NOW');
            $expire_date = $index['created_on']->add(new \DateInterval('PT'.$index['ttl'].'S'));
            $diffSeconds = $expire_date->getTimestamp() - $now->getTimestamp();

            return $diffSeconds;
        }

        return -1;
    }

    /**
     * @param array $index
     */
    protected function removeExpiredListsFromIndex($index)
    {
        if (is_array($index)) {
            foreach (array_keys($index) as $key) {
                if (false === $this->exists($key) && isset($index[$key])) {
                    $this->removeListFromIndex($key);
                }
            }
        }
    }

    /**
     * @param $listElement
     * @param $data
     *
     * @return array|object
     */
    protected function updateListElementBody($listElement, $data)
    {
        $listElement = $listElement;

        if (is_string($listElement)) {
            return $data;
        }

        if (is_array($listElement)) {
            return array_merge((array) $listElement, (array) $data);
        }

        return (object) array_merge((array) $listElement, (array) $data);
    }
}
