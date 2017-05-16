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

interface ListRepository
{
    const HASH_SEPARATOR = ':';
    const HEADERS_SEPARATOR = '#';

    /**
     * @return mixed
     */
    public function all();

    /**
     * @param ListCollection $list
     * @param null           $ttl
     *
     * @return mixed
     */
    public function create(ListCollection $list, $ttl = null);

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function deleteList($listUuid);

    /**
     * @param $listUuid
     * @param $elementUuid
     */
    public function deleteElement($listUuid, $elementUuid);

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return bool
     */
    public function existsElement($listUuid, $elementUuid);

    /**
     * @param $listUuid
     * @return bool
     */
    public function existsList($listUuid);

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
     * @param $completeListElementUuid
     *
     * @return mixed
     */
    public function findElementByCompleteCollectionElementUuid($completeListElementUuid);

    /**
     * @return mixed
     */
    public function flush();

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getHeaders($listUuid);

    /**
     * @return mixed
     */
    public function stats();

    /**
     * @param $key
     *
     * @return mixed
     */
    public function ttl($key);

    /**
     * @param $listUuid
     * @param null $ttl
     *
     * @return mixed
     */
    public function updateTtl($listUuid, $ttl = null);
}
