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
    /**
     * @return mixed
     */
    public function all();

    /**
     * @param ListCollection $collection
     * @param null           $ttl
     *
     * @return mixed
     */
    public function create(ListCollection $collection, $ttl = null);

    /**
     * @param $collectionUuid
     *
     * @return mixed
     */
    public function delete($collectionUuid);

    /**
     * @param $collectionUuid
     * @param $elementUuid
     */
    public function deleteElement($collectionUuid, $elementUuid);

    /**
     * @param $collectionUuid
     * @param $elementUuid
     *
     * @return bool
     */
    public function existsElement($collectionUuid, $elementUuid);

    /**
     * @param $collectionUuid
     *
     * @return mixed
     */
    public function findByUuid($collectionUuid);

    /**
     * @param $collectionUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function findElement($collectionUuid, $elementUuid);

    /**
     * @return mixed
     */
    public function flush();

    /**
     * @param $collectionUuid
     *
     * @return mixed
     */
    public function getHeaders($collectionUuid);

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
     * @param $collectionUuid
     * @param null $ttl
     *
     * @return mixed
     */
    public function updateTtl($collectionUuid, $ttl = null);
}
