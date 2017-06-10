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

use InMemoryList\Domain\Model\Contracts\ListRepository;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListDoesNotExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListElementDoesNotExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\NotConformingElementStructure;

class MemcachedRepository extends AbstractRepository implements ListRepository
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
     * @param null $ttl
     * @param null $chunkSize
     *
     * @return mixed
     *
     * @throws ListAlreadyExistsException
     */
    public function create(ListCollection $list, $ttl = null, $chunkSize = null)
    {
        if (!$chunkSize and !is_int($chunkSize)) {
            $chunkSize = self::CHUNKSIZE;
        }

        $listUuid = (string)$list->getUuid();
        if ($this->findListByUuid($listUuid)) {
            throw new ListAlreadyExistsException('List '.$list->getUuid().' already exists in memory.');
        }

        // create arrayOfElements
        $arrayOfElements = [];

        /** @var ListElement $element */
        foreach ($list->getItems() as $element) {
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
                (string)$list->getUuid().self::SEPARATOR.'chunk-'.($chunkNumber+1),
                $arrayToPersist,
                $ttl
            );
        }

        // add list to index
        $this->_addOrUpdateListToIndex(
            $listUuid,
            (int)count($list->getItems()),
            (int)count($arrayChunks),
            (int)$chunkSize,
            $ttl
        );

        // set headers
        if ($list->getHeaders()) {
            $this->memcached->set(
                (string)$list->getUuid().self::SEPARATOR.self::HEADERS,
                $list->getHeaders(),
                $ttl
            );
        }

        return $this->findListByUuid($list->getUuid());
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        $numberOfChunks = $this->getNumberOfChunks($listUuid);
        $chunkSize = $this->getChunkSize($listUuid);

        for ($i=1; $i<=$numberOfChunks; $i++) {
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = $this->memcached->get($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {

                // delete elements from chunk
                unset($chunk[(string) $elementUuid]);
                $this->memcached->replace($chunkNumber, $chunk);

                // update list index
                $prevIndex = unserialize($this->getIndex($listUuid));
                $this->_addOrUpdateListToIndex(
                    $listUuid,
                    ($prevIndex['size'] - 1),
                    $numberOfChunks,
                    $chunkSize,
                    $prevIndex['ttl']
                );

                // delete headers if counter = 0
                $headersKey = $listUuid . self::SEPARATOR . self::HEADERS;

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
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        $collection = ($this->memcached->get($listUuid.self::SEPARATOR.self::CHUNK.'-1')) ?: [];
        $numberOfChunks = $this->getNumberOfChunks($listUuid);

        for ($i=2; $i<=$numberOfChunks; $i++) {
            $collection = array_merge($collection, $this->memcached->get($listUuid.self::SEPARATOR.self::CHUNK.'-'.$i));
        }

        return $collection;
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
     * @param null $flush
     *
     * @return mixed
     */
    public function getIndex($listUuid = null, $flush = null)
    {
        $indexKey = ListRepository::INDEX;
        $index = $this->memcached->get($indexKey);

        if ($flush and $index) {
            foreach(array_keys($index) as $key) {
                if (!$this->findListByUuid($key)) {
                    $this->removeListFromIndex($key);
                }
            }
        }

        if ($listUuid) {
            return $index[$listUuid];
        }

        return $index;
    }

    /**
     * @param $listUuid
     * @param $size
     * @param $numberOfChunks
     * @param null $ttl
     */
    private function _addOrUpdateListToIndex($listUuid, $size, $numberOfChunks, $chunkSize, $ttl = null)
    {
        $indexKey = ListRepository::INDEX;
        $indexArray = serialize([
            'uuid' => $listUuid,
            'created_on' => new \DateTimeImmutable(),
            'size' => $size,
            'chunks' => $numberOfChunks,
            'chunk-size' => $chunkSize,
            'ttl' => $ttl
        ]);

        ($this->_existsListInIndex($listUuid)) ? $this->memcached->replace($indexKey, [$listUuid => $indexArray]) : $this->memcached->set($indexKey, [$listUuid => $indexArray]);

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
     * @throws NotConformingElementStructure
     *
     * @return mixed
     */
    public function pushElement($listUuid, ListElement $listElement)
    {
        $elementUuid = $listElement->getUuid();
        $body = $listElement->getBody();

        if(!$this->_isListElementConforming($listUuid, unserialize($body))){
            throw new NotConformingElementStructure('The structure of the element '. (string)$elementUuid .' does not conform to that of the list.');
        }

        $numberOfChunks = $this->getNumberOfChunks($listUuid);
        $chunkSize = $this->getChunkSize($listUuid);
        $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $numberOfChunks;

        if ($chunkSize - count($this->memcached->get($chunkNumber)) === 0) {
            ++$numberOfChunks;
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $numberOfChunks;
        }

        $chunkValues = $this->memcached->get($chunkNumber);
        $chunkValues[(string)$elementUuid] = (string)$body;

        $this->memcached->set(
            (string)$chunkNumber,
            $chunkValues,
            $this->getTtl($listUuid)
        );

        // update list index
        $prevIndex = unserialize($this->getIndex($listUuid));
        $this->_addOrUpdateListToIndex(
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
        $index = $this->getIndex();

        unset($index[(string) $listUuid]);
        $this->memcached->replace(ListRepository::INDEX, $index);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     * @param null $ttl
     * @throws NotConformingElementStructure
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, array $data = [], $ttl = null)
    {
        if(!$this->_isListElementConforming($listUuid, $data)){
            throw new NotConformingElementStructure('The structure of the element '. (string)$elementUuid .' does not conform to that of the list.');
        }

        $number = ceil($this->getCounter($listUuid) / self::CHUNKSIZE);

        for ($i=1; $i<=$number; $i++) {
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = $this->memcached->get($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {
                $element = $this->findElement($listUuid, $elementUuid);
                $objMerged = (object) array_merge((array) $element, (array) $data);
                $arrayOfElements = $this->memcached->get($listUuid);
                $updatedElement = new ListElement(
                    new ListElementUuid($elementUuid),
                    $objMerged
                );
                $body = $updatedElement->getBody();
                $arrayOfElements[(string) $elementUuid] = $body;

                $this->memcached->replace(
                    (string)$chunkNumber,
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

        $this->_addOrUpdateListToIndex(
            $listUuid,
            $this->getCounter($listUuid),
            $this->getNumberOfChunks($listUuid),
            $this->getChunkSize($listUuid),
            $ttl
        );

        $this->memcached->touch($listUuid, $ttl);
    }
}
