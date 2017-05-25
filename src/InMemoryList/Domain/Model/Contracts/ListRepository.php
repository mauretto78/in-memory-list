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
    const CHUNK = 'chunk';
    const CHUNKSIZE = 1000;
    const COUNTER = 'counter';
    const HEADERS = 'headers';
    const INDEX = 'index';
    const SEPARATOR = ':';
    const STATISTICS = 'statistics';

    /**
     * @param ListCollection $list
     * @param null $ttl
     * @param null $index
     *
     * @return mixed
     */
    public function create(ListCollection $list, $ttl = null, $index = null);

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function delete($listUuid);

    /**
     * @param $listUuid
     * @param $elementUuid
     * @return mixed
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
    public function getHeaders($listUuid);

    /**
     * @return mixed
     */
    public function getIndex();

    /**
     * @return mixed
     */
    public function getStatistics();

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, array $data = []);

    /**
     * @param $listUuid
     * @param null $ttl
     *
     * @return mixed
     */
    public function updateTtl($listUuid, $ttl = null);
}
