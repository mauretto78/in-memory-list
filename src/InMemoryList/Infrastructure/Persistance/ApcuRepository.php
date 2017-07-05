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

use InMemoryList\Domain\Helper\ListElementConsistencyChecker;
use InMemoryList\Domain\Model\Contracts\ListRepositoryInterface;
use InMemoryList\Domain\Model\Exceptions\ListElementNotConsistentException;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListDoesNotExistsException;

class ApcuRepository extends AbstractRepository implements ListRepositoryInterface
{
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
        if (!$chunkSize && !is_int($chunkSize)) {
            $chunkSize = self::CHUNKSIZE;
        }

        $listUuid = (string) $list->getUuid();
        if ($this->findListByUuid($listUuid)) {
            throw new ListAlreadyExistsException('List '.$listUuid.' already exists in memory.');
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

            apcu_store(
                (string) $list->getUuid().self::SEPARATOR.'chunk-'.($chunkNumber + 1),
                $arrayToPersist,
                $ttl
            );
        }

        // set headers
        if ($list->getHeaders()) {
            apcu_store(
                (string) $list->getUuid().self::SEPARATOR.self::HEADERS,
                $list->getHeaders(),
                $ttl
            );
        }

        // add list to index
        $this->addOrUpdateListToIndex(
            (string) $listUuid,
            (int) count($list->getElements()),
            (int) count($arrayChunks),
            (int) $chunkSize,
            $ttl
        );

        return $this->findListByUuid($list->getUuid());
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
            $chunk = apcu_fetch($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {
                // delete elements from chunk
                unset($chunk[(string) $elementUuid]);
                apcu_delete($chunkNumber);
                apcu_store($chunkNumber, $chunk);

                // update list index
                $prevIndex = unserialize($this->getIndex($listUuid));
                $this->addOrUpdateListToIndex(
                    $listUuid,
                    ($prevIndex['size'] - 1),
                    $numberOfChunks,
                    $chunkSize,
                    $prevIndex['ttl']
                );

                // delete headers if counter = 0
                $counter = $this->getCounter($listUuid);
                $headersKey = $listUuid.self::SEPARATOR.self::HEADERS;

                if ($counter === 0) {
                    apcu_delete($headersKey);
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
        $listFirstChunk = apcu_fetch($listUuid.self::SEPARATOR.self::CHUNK.'-1');

        return isset($listFirstChunk);
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        $collection = (apcu_fetch($listUuid.self::SEPARATOR.self::CHUNK.'-1')) ?: [];
        $numberOfChunks = $this->getNumberOfChunks($listUuid);

        for ($i = 2; $i <= $numberOfChunks; ++$i) {
            $collection = array_merge($collection, apcu_fetch($listUuid.self::SEPARATOR.self::CHUNK.'-'.$i));
        }

        return $collection;
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        apcu_clear_cache();
    }

    /**
     * @param $listUuid
     *
     * @return array
     */
    public function getHeaders($listUuid)
    {
        return apcu_fetch($listUuid.self::SEPARATOR.'headers');
    }

    /**
     * @param null $listUuid
     *
     * @return mixed
     */
    public function getIndex($listUuid = null)
    {
        $indexKey = ListRepositoryInterface::INDEX;
        $index = apcu_fetch($indexKey);

        $this->removeExpiredListsFromIndex($index);

        if ($listUuid) {
            return (isset($index[(string) $listUuid])) ? $index[(string) $listUuid] : null;
        }

        return $index;
    }

    /**
     * @param $listUuid
     * @param $size
     * @param $numberOfChunks
     * @param $chunkSize
     * @param null $ttl
     */
    private function addOrUpdateListToIndex($listUuid, $size, $numberOfChunks, $chunkSize, $ttl = null)
    {
        $indexKey = ListRepositoryInterface::INDEX;
        $indexArray = serialize([
            'uuid' => $listUuid,
            'created_on' => new \DateTimeImmutable(),
            'size' => $size,
            'chunks' => $numberOfChunks,
            'chunk-size' => $chunkSize,
            'headers' => $this->getHeaders($listUuid),
            'ttl' => $ttl,
        ]);

        $indexArrayToUpdate[(string) $listUuid] = $indexArray;

        if ($this->existsListInIndex($listUuid)) {
            $index = apcu_fetch((string) $indexKey);
            $index[$listUuid] = $indexArray;
            $indexArrayToUpdate = $index;
            apcu_delete($indexKey);
        }

        apcu_store((string) $indexKey, $indexArrayToUpdate);

        if ($size === 0) {
            $this->removeListFromIndex($listUuid);
        }
    }

    /**
     * @return array
     */
    public function getStatistics()
    {
        return (array) apcu_cache_info();
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
        $ttl = ($this->getTtl($listUuid) > 0) ? $this->getTtl($listUuid) : null;

        if ($chunkSize - count(apcu_fetch($chunkNumber)) === 0) {
            ++$numberOfChunks;
            $chunkNumber = $listUuid.self::SEPARATOR.self::CHUNK.'-'.$numberOfChunks;
        }

        $chunkValues = apcu_fetch($chunkNumber);
        $chunkValues[(string) $elementUuid] = (string) $body;

        apcu_delete($chunkNumber);
        apcu_store(
            $chunkNumber,
            $chunkValues,
            $ttl
        );

        // update list index
        $prevIndex = unserialize($this->getIndex($listUuid));
        $this->addOrUpdateListToIndex(
            $listUuid,
            ($prevIndex['size'] + 1),
            $numberOfChunks,
            $chunkSize,
            $ttl
        );
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function removeListFromIndex($listUuid)
    {
        $indexKey = ListRepositoryInterface::INDEX;

        $index = apcu_fetch($indexKey);
        unset($index[(string) $listUuid]);

        apcu_delete($indexKey);
        apcu_store($indexKey, $index);
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
            $chunk = apcu_fetch($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {
                $listElement = $this->findElement(
                    (string) $listUuid,
                    (string) $elementUuid
                );

                $updatedElementBody = $this->updateListElementBody($listElement, $data);
                if (!ListElementConsistencyChecker::isConsistent($updatedElementBody, $this->findListByUuid($listUuid))) {
                    throw new ListElementNotConsistentException('Element '.(string) $elementUuid.' is not consistent with list data.');
                }

                $arrayOfElements = apcu_fetch($listUuid);
                $updatedElement = new ListElement(
                    new ListElementUuid($elementUuid),
                    $updatedElementBody
                );
                $body = $updatedElement->getBody();
                $arrayOfElements[(string) $elementUuid] = $body;

                apcu_delete($chunkNumber);
                apcu_store(
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

        $ttl = ($ttl > 0) ? $ttl : null;

        // update ttl of all chunks
        $numberOfChunks = $this->getNumberOfChunks($listUuid);
        for ($i = 1; $i <= $numberOfChunks; ++$i) {
            $chunkNumber = (string) $listUuid.self::SEPARATOR.self::CHUNK.'-'.$i;
            $storedListInChunk = apcu_fetch($chunkNumber);
            apcu_delete((string) $chunkNumber);
            apcu_store(
                (string) $chunkNumber,
                $storedListInChunk,
                $ttl
            );
        }

        // update ttl of headers array (if present)
        if ($this->getHeaders($listUuid)) {
            $headers = (string) $listUuid.self::SEPARATOR.self::HEADERS;
            $storedHeaders = apcu_fetch($headers);
            apcu_delete((string) $headers);
            apcu_store(
                (string) $headers,
                $storedHeaders,
                $ttl
            );
        }

        // update index
        $this->addOrUpdateListToIndex(
            (string) $listUuid,
            $this->getCounter((string) $listUuid),
            $this->getNumberOfChunks((string) $listUuid),
            $this->getChunkSize((string) $listUuid),
            $ttl
        );
    }
}
