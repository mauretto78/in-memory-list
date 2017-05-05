<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<assistenza@easy-grafica.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Domain\Model\Contracts;

use InMemoryList\Domain\Model\ListCollection;

interface ListRepository
{
    /**
     * @param ListCollection $collection
     *
     * @return mixed
     */
    public function create(ListCollection $collection);

    /**
     * @param $collectionUUId
     *
     * @return mixed
     */
    public function delete($collectionUUId);

    /**
     * @param $collectionUUId
     * @param $elementUUId
     */
    public function deleteElement($collectionUUId, $elementUUId);

    /**
     * @param $collectionUUId
     * @param $elementUUId
     *
     * @return bool
     */
    public function existsElement($collectionUUId, $elementUUId);

    /**
     * @param $collectionUUId
     *
     * @return mixed
     */
    public function findByUuid($collectionUUId);

    /**
     * @param $collectionUUId
     * @param $elementUUId
     *
     * @return mixed
     */
    public function findElement($collectionUUId, $elementUUId);

    /**
     * @return mixed
     */
    public function flush();
}
