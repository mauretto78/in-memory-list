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

class MemcachedRepository implements ListRepository
{
    /**
     * @var int
     */
    private $chunkSize;

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
        $this->chunkSize = self::CHUNKSIZE;
    }

    /**
     * @param ListCollection $list
     * @param null $ttl
     * @param null $index
     * @param null $chunkSize
     *
     * @return mixed
     *
     * @throws ListAlreadyExistsException
     */
    public function create(ListCollection $list, $ttl = null, $index = null, $chunkSize = null)
    {
        if ($chunkSize and is_int($chunkSize)) {
            $this->chunkSize = $chunkSize;
        }

        if ($this->findListByUuid($list->getUuid())) {
            throw new ListAlreadyExistsException('List '.$list->getUuid().' already exists in memory.');
        }

        // create arrayOfElements
        $arrayOfElements = [];

        /** @var ListElement $element */
        foreach ($list->getItems() as $element) {
            $arrayOfElements[(string) $element->getUuid()] = $element->getBody();
        }

        // set counter
        $this->memcached->set(
            (string)$list->getUuid().self::SEPARATOR.self::COUNTER,
            count($list->getItems()),
            $ttl
        );

        // persist in memory array in chunks
        foreach (array_chunk($arrayOfElements, $this->chunkSize, true) as $chunkNumber => $item) {
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

        // add elements to general index
        if ($index) {
            $arrayOfElementsForStatistics = [];

            /** @var ListElement $element */
            foreach ($list->getItems() as $element) {
                $arrayOfElementsForStatistics[(string) $element->getUuid()] = serialize([
                    'created_on' => new \DateTimeImmutable(),
                    'ttl' => $ttl,
                    'size' => strlen($element->getBody())
                ]);
            }

            $this->memcached->set(
                ListRepository::INDEX,
                $arrayOfElementsForStatistics
            );
        }

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
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        $number = ceil($this->getCounter($listUuid) / self::CHUNKSIZE);

        for ($i=1; $i<=$number; $i++) {
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = $this->memcached->get($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {
                unset($chunk[(string) $elementUuid]);
                $this->memcached->replace($chunkNumber, $chunk);

                if ($this->_existsElementInIndex($elementUuid)) {
                    $indexStatistics = $this->getIndex();
                    unset($indexStatistics[(string) $elementUuid]);

                    $this->memcached->replace(ListRepository::INDEX, $indexStatistics);
                }

                $this->memcached->decrement($this->getCounter($listUuid));
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
     * @param $elementUuid
     * @return string
     */
    private function _existsElementInIndex($elementUuid)
    {
        return (isset($this->memcached->get(ListRepository::INDEX)[$elementUuid]));
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
                $collection = $this->memcached->get($listUuid.self::SEPARATOR.self::CHUNK.'-1');
            } else {
                $collection = array_merge($collection, $this->memcached->get($listUuid.self::SEPARATOR.self::CHUNK.'-'.$i));
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
        $this->memcached->flush();
    }

    /**
     * @param $listUuid
     * @return mixed
     */
    public function getCounter($listUuid)
    {
        return $this->memcached->get($listUuid.self::SEPARATOR.self::COUNTER);
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
     * @return array
     */
    public function getStatistics()
    {
        return $this->memcached->getStats();
    }

    /**
     * @return array
     */
    public function getIndex()
    {
        return $this->memcached->get(ListRepository::INDEX);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, array $data = [], $ttl = null)
    {
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

                if ($this->_existsElementInIndex($elementUuid)) {
                    $indexStatistics = $this->getIndex();
                    $indexStatistics[(string) $elementUuid] = serialize([
                        'created_on' => new \DateTimeImmutable(),
                        'ttl' => $ttl,
                        'size' => strlen($body)
                    ]);

                    $this->memcached->replace(
                        (string)ListRepository::INDEX,
                        $indexStatistics
                    );
                }

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
    public function updateTtl($listUuid, $ttl = null)
    {
        if (!$this->findListByUuid($listUuid)) {
            throw new ListDoesNotExistsException('List '.$listUuid.' does not exists in memory.');
        }

        $this->memcached->touch($listUuid, $ttl);

        return $this->findListByUuid($listUuid);
    }
}
