<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Domain\Model\Contracts;

use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListElement;

interface ListRepositoryInterface
{
    const CHUNK = 'chunk';
    const CHUNKSIZE = 1000;
    const HEADERS = 'headers';
    const INDEX = 'index';
    const SEPARATOR = ':';
    const STATISTICS = 'statistics';

    /**
     * @param ListCollection $list
     * @param null           $ttl
     * @param null           $chunkSize
     *
     * @return mixed
     */
    public function create(ListCollection $list, $ttl = null, $chunkSize = null);

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function delete($listUuid);

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid);

    /**
     * @param $listUuid
     *
     * @return bool
     */
    public function exists($listUuid);

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return bool
     */
    public function existsElement($listUuid, $elementUuid);

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid);

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function findElement($listUuid, $elementUuid);

    /**
     * @return mixed
     */
    public function flush();

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getChunkSize($listUuid);

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getCounter($listUuid);

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getHeaders($listUuid);

    /**
     * @param null $listUuid
     *
     * @return mixed
     */
    public function getIndex($listUuid = null);

    /**
     * @param \Datetime $from
     * @param \Datetime $to
     *
     * @return mixed
     */
    public function getIndexInRangeDate(\Datetime $from = null, \Datetime $to = null);

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getNumberOfChunks($listUuid);

    /**
     * @return mixed
     */
    public function getStatistics();

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getTtl($listUuid);

    /**
     * @param $listUuid
     * @param ListElement $listElement
     *
     * @return mixed
     */
    public function pushElement($listUuid, ListElement $listElement);

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function removeListFromIndex($listUuid);

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param mixed $data
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, $data);

    /**
     * @param $listUuid
     * @param $ttl
     *
     * @return mixed
     */
    public function updateTtl($listUuid, $ttl);
}
