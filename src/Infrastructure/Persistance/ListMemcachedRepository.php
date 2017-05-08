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
use InMemoryList\Infrastructure\Persistance\Exception\CollectionAlreadyExistsException;
use InMemoryList\Infrastructure\Helper\ListMemcachedRepositoryUuid;
use InMemoryList\Infrastructure\Persistance\Exception\NotExistListElementException;

class ListMemcachedRepository implements ListRepository
{
    /**
     * @var \Memcached
     */
    private $memcached;

    /**
     * ListMemcachedRepository constructor.
     * @param \Memcached $memcached
     */
    public function __construct(\Memcached $memcached)
    {
        $this->memcached = $memcached;
    }

    /**
     * @param ListCollection $collection
     *
     * @return mixed
     *
     * @throws CollectionAlreadyExistsException
     */
    public function create(ListCollection $collection)
    {
        if ($this->findByUuid($collection->getUuid())) {
            throw new CollectionAlreadyExistsException('Collection '.$collection->getUuid().' already exists in memory.');
        }

        $arrayOfElements = [];

        /** @var ListElement $element */
        foreach ($collection->getAll() as $element) {
            $arrayOfElements[(string)$element->getUuid()] = serialize($element);
        }

        $this->memcached->set(
            $collection->getUuid(),
            $arrayOfElements
        );

        return $this->findByUuid($collection->getUuid());
    }

    /**
     * @param $collectionUUId
     *
     * @return mixed
     */
    public function delete($collectionUUId)
    {
        $this->memcached->delete($collectionUUId);
    }

    /**
     * @param $collectionUUId
     * @param $elementUUId
     * @throws NotExistListElementException
     */
    public function deleteElement($collectionUUId, $elementUUId)
    {
        $arr = $this->findByUuid($collectionUUId);
        unset($arr[(string)$elementUUId]);

        $this->memcached->replace($collectionUUId, $arr);
    }

    /**
     * @param $collectionUUId
     * @param $elementUUId
     *
     * @return bool
     */
    public function existsElement($collectionUUId, $elementUUId)
    {
        return @isset($this->findByUuid($collectionUUId)[$elementUUId]);
    }

    /**
     * @param $collectionUUId
     *
     * @return mixed
     */
    public function findByUuid($collectionUUId)
    {
        return $this->memcached->get($collectionUUId);
    }

    /**
     * @param $collectionUUId
     * @param $elementUUId
     *
     * @return mixed
     *
     * @throws NotExistListElementException
     */
    public function findElement($collectionUUId, $elementUUId)
    {
        if (!$this->existsElement($collectionUUId, $elementUUId)) {
            throw new NotExistListElementException('Cannot retrieve the element '.$elementUUId.' from the collection in memory.');
        }

        return unserialize($this->memcached->get($collectionUUId)[(string)$elementUUId]);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        $this->memcached->flush();
    }
}
