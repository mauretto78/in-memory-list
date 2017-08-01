<?php
/**
 * This file is part of the Simple EventStore Manager package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace InMemoryList\Infrastructure\Persistance;

use InMemoryList\Domain\Helper\ListElementConsistencyChecker;
use InMemoryList\Domain\Model\Contracts\ListRepositoryInterface;
use InMemoryList\Domain\Model\Exceptions\ListElementNotConsistentException;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListDoesNotExistsException;

class MemcachedRepository extends AbstractRepository implements ListRepositoryInterface
{
    /**
     * @var \Memcached
     */
    private $memcached;

    /**
     * ListMemcachedRepository constructor.
     *
     * @param \Memcached $memcached
     */
    public function __construct(\Memcached $memcached)
    {
        $this->memcached = $memcached;
    }

    /**
     * @param ListCollection $list
     * @param null           $ttl
     * @param null           $chunkSize
     *
     * @return mixed
     *
     * @throws ListAlreadyExistsException
     */
    public function create(ListCollection $list, $ttl = null, $chunkSize = null)
    {
        // check if list already exists in memory
        $listUuid = (string) $list->getUuid();
        if ($this->existsListInIndex($listUuid) && $this->exists($listUuid)) {
            throw new ListAlreadyExistsException('List '.$list->getUuid().' already exists in memory.');
        }

        if (!$chunkSize && !is_int($chunkSize)) {
            $chunkSize = self::CHUNKSIZE;
        }

        // create arrayOfElements
        $arrayOfElements = [];

        /** @var ListElement $element */
        foreach ($list->getElements() as $element) {
            $arrayOfElements[(string) $element->getUuid()] = $element->getBody();
        }

        // persist in memory array in chunks
        $arrayChunks = array_chunk($arrayOfElements, $chunkSize, true);
        foreach ($arrayChunks as $chunkNumber => $item) {
            $arrayToPersist = [];
            foreach ($item as $key => $element) {
                $arrayToPersist[$key] = $element;
            }

            $this->memcached->set(
                (string) $list->getUuid().self::SEPARATOR.'chunk-'.($chunkNumber + 1),
                $arrayToPersist,
                $ttl
            );
        }

        // set headers
        if ($list->getHeaders()) {
            $this->memcached->set(
                (string) $list->getUuid().self::SEPARATOR.self::HEADERS,
                $list->getHeaders(),
                $ttl
            );
        }

        // add list to index
        $this->addOrUpdateListToIndex(
            $listUuid,
            (int) count($list->getElements()),
            (int) count($arrayChunks),
            (int) $chunkSize,
            $ttl
        );
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        $numberOfChunks = $this->getNumberOfChunks($listUuid);
        $chunkSize = $this->getChunkSize($listUuid);

        for ($i = 1; $i <= $numberOfChunks; ++$i) {
            $chunkNumber = $listUuid.self::SEPARATOR.self::CHUNK.'-'.$i;
            $chunk = $this->memcached->get($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {
                // delete elements from chunk
                unset($chunk[(string) $elementUuid]);
                $this->memcached->replace($chunkNumber, $chunk);

                // update list index
                $prevIndex = $this->getIndex($listUuid);
                $this->addOrUpdateListToIndex(
                    $listUuid,
                    ($prevIndex['size'] - 1),
                    $numberOfChunks,
                    $chunkSize,
                    $prevIndex['ttl']
                );

                // delete headers if counter = 0
                $headersKey = $listUuid.self::SEPARATOR.self::HEADERS;

                if ($this->getCounter($listUuid) === 0) {
                    $this->memcached->delete($headersKey);
                }

                break;
            }
        }
    }

    /**
     * @param $listUuid
     *
     * @return bool
     */
    public function exists($listUuid)
    {
        $listFirstChunk = $this->memcached->get($listUuid.self::SEPARATOR.self::CHUNK.'-1');

        if(false === $listFirstChunk){
            return false;
        }

        return true;
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        $collection = ($this->memcached->get($listUuid.self::SEPARATOR.self::CHUNK.'-1')) ?: [];
        $numberOfChunks = $this->getNumberOfChunks($listUuid);

        for ($i = 2; $i <= $numberOfChunks; ++$i) {
            $collection = (array) array_merge($collection, $this->memcached->get($listUuid.self::SEPARATOR.self::CHUNK.'-'.$i));
        }

        return (array) array_map('unserialize', $collection);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        $this->memcached->flush();
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getHeaders($listUuid)
    {
        return $this->memcached->get($listUuid.self::SEPARATOR.self::HEADERS);
    }

    /**
     * @param null $listUuid
     *
     * @return mixed
     */
    public function getIndex($listUuid = null)
    {
        $indexKey = ListRepositoryInterface::INDEX;
        $index = $this->memcached->get($indexKey);
        $this->removeExpiredListsFromIndex($index);

        if ($listUuid) {
            return (isset($index[(string) $listUuid])) ? unserialize($index[(string) $listUuid]) : null;
        }

        return ($index) ? array_map('unserialize', $index) : [];
    }

    /**
     * @param $listUuid
     * @param $size
     * @param $numberOfChunks
     * @param null $ttl
     */
    private function addOrUpdateListToIndex($listUuid, $size, $numberOfChunks, $chunkSize, $ttl = null)
    {
        $indexKey = ListRepositoryInterface::INDEX;
        $indexArrayToUpdate = ($this->memcached->get($indexKey)) ?: [];

        $element = serialize([
            'uuid' => $listUuid,
            'created_on' => new \DateTimeImmutable(),
            'size' => $size,
            'chunks' => $numberOfChunks,
            'chunk-size' => $chunkSize,
            'headers' => $this->getHeaders($listUuid),
            'ttl' => $ttl,
        ]);

        $indexArrayToUpdate[(string) $listUuid] = $element;

        ($this->existsListInIndex($listUuid)) ? $this->memcached->replace($indexKey, $indexArrayToUpdate) : $this->memcached->set($indexKey, $indexArrayToUpdate);

        if ($size === 0) {
            $this->removeListFromIndex($listUuid);
        }
    }

    /**
     * @return array
     */
    public function getStatistics()
    {
        return $this->memcached->getStats();
    }

    /**
     * @param $listUuid
     * @param ListElement $listElement
     *
     * @throws ListElementNotConsistentException
     *
     * @return mixed
     */
    public function pushElement($listUuid, ListElement $listElement)
    {
        $elementUuid = $listElement->getUuid();
        $body = $listElement->getBody();

        if (!ListElementConsistencyChecker::isConsistent($listElement, $this->findListByUuid($listUuid))) {
            throw new ListElementNotConsistentException('Element '.(string) $listElement->getUuid().' is not consistent with list data.');
        }

        $numberOfChunks = $this->getNumberOfChunks($listUuid);
        $chunkSize = $this->getChunkSize($listUuid);
        $chunkNumber = $listUuid.self::SEPARATOR.self::CHUNK.'-'.$numberOfChunks;

        if ($chunkSize - count($this->memcached->get($chunkNumber)) === 0) {
            ++$numberOfChunks;
            $chunkNumber = $listUuid.self::SEPARATOR.self::CHUNK.'-'.$numberOfChunks;
        }

        $chunkValues = $this->memcached->get($chunkNumber);
        $chunkValues[(string) $elementUuid] = (string) $body;

        $this->memcached->set(
            (string) $chunkNumber,
            $chunkValues,
            $this->getTtl($listUuid)
        );

        // update list index
        $prevIndex = $this->getIndex($listUuid);
        $this->addOrUpdateListToIndex(
            $listUuid,
            ($prevIndex['size'] + 1),
            $numberOfChunks,
            $chunkSize,
            $this->getTtl($listUuid)
        );
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function removeListFromIndex($listUuid)
    {
        $index = $this->memcached->get(ListRepositoryInterface::INDEX);

        unset($index[(string) $listUuid]);
        $this->memcached->replace(ListRepositoryInterface::INDEX, $index);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     *
     * @throws ListElementNotConsistentException
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, $data)
    {
        $numberOfChunks = $this->getNumberOfChunks($listUuid);
        $ttl = ($this->getTtl($listUuid) > 0) ? $this->getTtl($listUuid) : null;

        for ($i = 1; $i <= $numberOfChunks; ++$i) {
            $chunkNumber = $listUuid.self::SEPARATOR.self::CHUNK.'-'.$i;
            $chunk = $this->memcached->get($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {
                $listElement = $this->findElement(
                    (string) $listUuid,
                    (string) $elementUuid
                );

                $updatedElementBody = $this->updateListElementBody($listElement, $data);
                if (!ListElementConsistencyChecker::isConsistent($updatedElementBody, $this->findListByUuid($listUuid))) {
                    throw new ListElementNotConsistentException('Element '.(string) $elementUuid.' is not consistent with list data.');
                }

                $arrayOfElements = $this->memcached->get($listUuid);
                $updatedElement = new ListElement(
                    new ListElementUuid($elementUuid),
                    $updatedElementBody
                );
                $body = $updatedElement->getBody();
                $arrayOfElements[(string) $elementUuid] = $body;

                $this->memcached->replace(
                    (string) $chunkNumber,
                    $arrayOfElements,
                    $ttl
                );

                break;
            }
        }
    }

    /**
     * @param $listUuid
     * @param null $ttl
     *
     * @return mixed
     *
     * @throws ListDoesNotExistsException
     */
    public function updateTtl($listUuid, $ttl)
    {
        if (!$this->findListByUuid($listUuid)) {
            throw new ListDoesNotExistsException('List '.$listUuid.' does not exists in memory.');
        }

        // update ttl of all chunks
        $numberOfChunks = $this->getNumberOfChunks($listUuid);
        for ($i = 1; $i <= $numberOfChunks; ++$i) {
            $this->memcached->touch(
                (string) $listUuid.self::SEPARATOR.self::CHUNK.'-'.$i,
                (int) $ttl
            );
        }

        // update ttl of headers array (if present)
        if ($this->getHeaders($listUuid)) {
            $this->memcached->touch(
                (string) $listUuid.self::SEPARATOR.self::HEADERS,
                (int) $ttl
            );
        }

        // update index
        $this->addOrUpdateListToIndex(
            $listUuid,
            $this->getCounter($listUuid),
            $this->getNumberOfChunks($listUuid),
            $this->getChunkSize($listUuid),
            $ttl
        );
    }
}
