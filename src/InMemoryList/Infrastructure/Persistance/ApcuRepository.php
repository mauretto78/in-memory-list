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

        $arrayOfElements = [];
        $arrayOfElementsForStatistics = [];

        /** @var ListElement $element */
        foreach ($list->getItems() as $element) {
            $listElementUuid = $element->getUuid();
            $body = $element->getBody();

            $arrayOfElements[(string)$listElementUuid] = $body;
            $arrayOfElementsForStatistics[(string)$listElementUuid] = serialize([
                'created_on' => new \DateTimeImmutable(),
                'ttl' => $ttl,
                'size' => strlen($body)
            ]);
        }

        // list index
        apcu_store(
            (string)$list->getUuid(),
            $arrayOfElements,
            $ttl
        );

        // add elements to general index
        if ($index) {
            apcu_store(
                ListRepository::INDEX,
                $arrayOfElementsForStatistics
            );
        }

        // headers
        if ($list->getHeaders()) {
            apcu_store(
                (string)$list->getUuid().self::HEADERS_SEPARATOR.'headers',
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
        apcu_delete($listUuid);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param null $ttl
     */
    public function deleteElement($listUuid, $elementUuid, $ttl = null)
    {
        $arrayToReplace = $this->findListByUuid($listUuid);
        unset($arrayToReplace[(string) $elementUuid]);

        $this->delete($listUuid);

        apcu_store(
            (string)$listUuid,
            $arrayToReplace,
            $ttl
        );

        if ($this->_existsElementInIndex($elementUuid)) {
            $indexStatistics = $this->getIndex();
            unset($indexStatistics[(string) $elementUuid]);

            apcu_store(ListRepository::INDEX, $indexStatistics);
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
        return @isset(apcu_fetch($listUuid)[$elementUuid]);
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
        return apcu_fetch($listUuid);
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
        if (!$this->existsElement($listUuid, $elementUuid)) {
            throw new ListElementDoesNotExistsException('Cannot retrieve the element '.$elementUuid.' from the collection in memory.');
        }

        return apcu_fetch($listUuid)[(string) $elementUuid];
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
        return apcu_fetch($listUuid.self::HEADERS_SEPARATOR.'headers');
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
        $element = $this->findElement($listUuid, $elementUuid);
        $arrayStatistics = $this->getStatistics();

        $objMerged = (object) array_merge((array) $element, (array) $data);
        $arrayOfElements = apcu_fetch($listUuid);
        $updatedElement = new ListElement(
            new ListElementUuid($elementUuid),
            $objMerged
        );
        $arrayOfElements[(string) $elementUuid] = $updatedElement->getBody();
        $arrayStatistics[(string) $elementUuid] = serialize([
            'created_on' => new \DateTimeImmutable(),
            'ttl' => $ttl,
            'size' => strlen($updatedElement->getBody())
        ]);

        $this->delete($listUuid);

        apcu_store(
            (string)$listUuid,
            $arrayOfElements,
            $ttl
        );

        if ($this->_existsElementInIndex($elementUuid)) {
            $indexStatistics = $this->getIndex();
            $indexStatistics[(string) $elementUuid] = serialize([
                'created_on' => new \DateTimeImmutable(),
                'ttl' => $ttl,
                'size' => strlen($updatedElement->getBody())
            ]);

            apcu_store(
                (string)ListRepository::INDEX,
                $indexStatistics
            );
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
