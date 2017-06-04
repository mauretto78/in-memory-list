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

use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\Contracts\ListRepository;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListDoesNotExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListElementDoesNotExistsException;
use Predis\Client;

class ApcuRepository implements ListRepository
{
    /**
     * @var int
     */
    private $chunkSize;

    /**
     * ApcuRepository constructor.
     */
    public function __construct()
    {
        $this->chunkSize = self::CHUNKSIZE;
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
        if ($chunkSize and is_int($chunkSize)) {
            $this->chunkSize = $chunkSize;
        }

        $listUuid = (string) $list->getUuid();
        if ($this->findListByUuid($listUuid)) {
            throw new ListAlreadyExistsException('List '.$listUuid.' already exists in memory.');
        }

        // create arrayOfElements
        $arrayOfElements = [];

        /** @var ListElement $element */
        foreach ($list->getItems() as $element) {
            $arrayOfElements[(string) $element->getUuid()] = $element->getBody();
        }

        // set counter
        apcu_store(
            (string)$list->getUuid().self::SEPARATOR.self::COUNTER,
            count($list->getItems()),
            $ttl
        );

        // persist in memory array in chunks
        foreach (array_chunk($arrayOfElements, self::CHUNKSIZE, true) as $chunkNumber => $item) {
            $arrayToPersist = [];
            foreach ($item as $key => $element) {
                $arrayToPersist[$key] = $element;
            }

            apcu_store(
                (string)$list->getUuid().self::SEPARATOR.'chunk-'.($chunkNumber+1),
                $arrayToPersist,
                $ttl
            );
        }

        // add elements to general index
        $this->_addOrUpdateListToIndex($listUuid, count($list->getItems(), $ttl));

        // set headers
        if ($list->getHeaders()) {
            apcu_store(
                (string)$list->getUuid().self::SEPARATOR.self::HEADERS,
                $list->getHeaders(),
                $ttl
            );
        }

        return $this->findListByUuid($list->getUuid());
    }

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
     * @param null $ttl
     *
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid, $ttl = null)
    {
        $number = ceil($this->getCounter($listUuid) / self::CHUNKSIZE);

        for ($i=1; $i<=$number; $i++) {
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = apcu_fetch($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {

                // delete elements from chunk
                unset($chunk[(string) $elementUuid]);
                apcu_delete($chunkNumber);
                apcu_store($chunkNumber, $chunk);

                // decr counter and delete counter and headers if counter = 0
                $counterKey = $listUuid . self::SEPARATOR . self::COUNTER;
                $headersKey = $listUuid . self::SEPARATOR . self::HEADERS;
                $counter = apcu_dec($counterKey);

                if ($counter === 0) {
                    apcu_delete($headersKey);
                    apcu_delete($counterKey);
                }

                // update list index
                $prevIndex = apcu_store(ListRepository::INDEX)[$listUuid];
                $prevIndex = unserialize($prevIndex);
                $this->_addOrUpdateListToIndex($listUuid, ($prevIndex['size'] - 1), $prevIndex['ttl']);

                break;
            }
        }
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return bool
     */
    public function existsElement($listUuid, $elementUuid)
    {
        return @$this->findListByUuid($listUuid)[$elementUuid];
    }

    /**
     * @param $listUuid
     * @return bool
     */
    private function _existsListInIndex($listUuid)
    {
        return (isset(apcu_fetch(ListRepository::INDEX)[$listUuid]));
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        $collection = [];
        $number = ceil($this->getCounter($listUuid) / self::CHUNKSIZE);

        for ($i=1; $i<=$number; $i++) {
            if (empty($collection)) {
                $collection = apcu_fetch($listUuid.self::SEPARATOR.self::CHUNK.'-1');
            } else {
                $collection = array_merge($collection, apcu_fetch($listUuid.self::SEPARATOR.self::CHUNK.'-'.$i));
            }
        }

        return $collection;
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
     * @return mixed
     */
    public function flush()
    {
        apcu_clear_cache();
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function getCounter($listUuid)
    {
        return apcu_fetch($listUuid.self::SEPARATOR.self::COUNTER);
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
     * @return mixed
     */
    public function getIndex($listUuid = null)
    {
        $indexKey = ListRepository::INDEX;
        if ($listUuid) {
            return apcu_fetch($indexKey)[$listUuid];
        }

        return apcu_fetch($indexKey);
    }

    /**
     * @param $listUuid
     * @param int $listCount
     * @param null $ttl
     */
    private function _addOrUpdateListToIndex($listUuid, $listCount, $ttl = null)
    {
        $indexKey = ListRepository::INDEX;
        $indexArray = serialize([
            'uuid' => $listUuid,
            'created_on' => new \DateTimeImmutable(),
            'size' => $listCount,
            'ttl' => $ttl
        ]);

        if ($this->_existsListInIndex($listUuid)) {
            $index = $this->getIndex();
            $index[] = $indexArray;

            apcu_delete($indexKey);
            apcu_store($indexKey, $index);
        } else {
            apcu_store($indexKey, [$listUuid => $indexArray]);
        }

        if ($listCount === 0) {
            $this->_removeListFromIndex($listUuid);
        }
    }

    /**
     * @param $listUuid
     */
    private function _removeListFromIndex($listUuid)
    {
        $indexKey = ListRepository::INDEX;
        $index = $this->getIndex();
        unset($index[(string) $listUuid]);

        apcu_delete($indexKey);
        apcu_store($indexKey, $index);
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
     * @return mixed
     */
    public function pushElement($listUuid, ListElement $listElement)
    {
        // TODO: Implement pushElement() method.
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     * @param null $ttl
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, array $data = [], $ttl = null)
    {
        $number = ceil($this->getCounter($listUuid) / self::CHUNKSIZE);

        for ($i=1; $i<=$number; $i++) {
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = apcu_fetch($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {
                $element = $this->findElement($listUuid, $elementUuid);
                $objMerged = (object) array_merge((array) $element, (array) $data);
                $arrayOfElements = apcu_fetch($listUuid);
                $updatedElement = new ListElement(
                    new ListElementUuid($elementUuid),
                    $objMerged
                );
                $body = $updatedElement->getBody();
                $arrayOfElements[(string) $elementUuid] = $body;

                apcu_delete($chunkNumber);
                apcu_store(
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
        $list = $this->findListByUuid($listUuid);

        if (!$list) {
            throw new ListDoesNotExistsException('List '.$listUuid.' does not exists in memory.');
        }

        $this->_addOrUpdateListToIndex($listUuid, $ttl);
        apcu_delete($listUuid);
        apcu_store($listUuid, $list, $ttl);
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

            return  $diffSeconds;
        }

        return 0;
    }
}
