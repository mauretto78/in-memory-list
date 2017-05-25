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
     * @param ListCollection $list
     * @param null $ttl
     * @param null $index
     *
     * @return mixed
     *
     * @throws ListAlreadyExistsException
     */
    public function create(ListCollection $list, $ttl = null, $index = null)
    {
        $listUuid = $list->getUuid();

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

            apcu_store(
                ListRepository::INDEX,
                $arrayOfElementsForStatistics
            );
        }

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
                unset($chunk[(string) $elementUuid]);
                apcu_delete($chunkNumber);
                apcu_store($chunkNumber, $chunk);

                if ($this->_existsElementInIndex($elementUuid)) {
                    $indexStatistics = $this->getIndex();
                    unset($indexStatistics[(string) $elementUuid]);

                    apcu_delete(ListRepository::INDEX);
                    apcu_store(ListRepository::INDEX, $indexStatistics);
                }

                apcu_dec($this->getCounter($listUuid));
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
        return (isset(apcu_fetch(ListRepository::INDEX)[$elementUuid]));
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
     * @return array
     */
    public function getIndex()
    {
        return apcu_fetch(ListRepository::INDEX);
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

                if ($this->_existsElementInIndex($elementUuid)) {
                    $indexStatistics = $this->getIndex();
                    $indexStatistics[(string) $elementUuid] = serialize([
                        'created_on' => new \DateTimeImmutable(),
                        'ttl' => $ttl,
                        'size' => strlen($body)
                    ]);

                    apcu_delete(ListRepository::INDEX);
                    apcu_store(
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
    }
}
