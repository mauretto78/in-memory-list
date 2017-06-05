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

        foreach ($list as $elementUuid => $element) {
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
        return @$this->findListByUuid($listUuid)[$elementUuid];
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
        if (!$element = $this->existsElement($listUuid, $elementUuid)) {
            throw new ListElementDoesNotExistsException('Cannot retrieve the element '.$elementUuid.' from the collection in memory.');
        }

        return $element;
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getChunkSize($listUuid)
    {
        if($this->_existsListInIndex($listUuid)){
            $index = unserialize($this->getIndex($listUuid));

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
        if($this->_existsListInIndex($listUuid)){
            $index = unserialize($this->getIndex($listUuid));

            return $index['size'];
        }

        return 0;
    }

    /**
     * @param $listUuid
     * @return bool
     */
    protected function _existsListInIndex($listUuid)
    {
        $index = @$this->getIndex($listUuid);

        return isset($index);
    }

    /**
     * @param $listUuid
     *
     * @return float
     */
    public function getNumberOfChunks($listUuid)
    {
        if($this->_existsListInIndex($listUuid)){
            $index = unserialize($this->getIndex($listUuid));

            return $index['chunks'];
        }

        return 0;
    }

    /**
     * @param $listUuid
     * @return mixed
     */
    public function getTtl($listUuid)
    {
        $index = unserialize($this->getIndex($listUuid));
        if ($index['ttl'] and $index['ttl'] > 0) {
            $now = new \DateTime('NOW');
            $expire_date = $index['created_on']->add(new \DateInterval('PT'.$index['ttl'].'S'));
            $diffSeconds =  $expire_date->getTimestamp() - $now->getTimestamp();

            return $diffSeconds;
        }

        return -1;
    }
}