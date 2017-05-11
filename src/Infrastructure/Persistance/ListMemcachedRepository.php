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
use InMemoryList\Infrastructure\Persistance\Exception\NotExistListElementException;

class ListMemcachedRepository implements ListRepository
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
     * @return array
     */
    public function all()
    {
        return $this->memcached->getAllKeys(); // Need a version of Memcached =< 1.4.23 see http://stackoverflow.com/questions/42504252/php-gets-all-the-keys-of-memcached-always-return-false
    }

    /**
     * @param ListCollection $collection
     *
     * @return mixed
     *
     * @throws CollectionAlreadyExistsException
     */
    public function create(ListCollection $collection, $ttl = null)
    {
        if ($this->findByUuid($collection->getUuid())) {
            throw new CollectionAlreadyExistsException('Collection '.$collection->getUuid().' already exists in memory.');
        }

        $arrayOfElements = [];

        /** @var ListElement $element */
        foreach ($collection->getItems() as $element) {
            $arrayOfElements[(string) $element->getUuid()] = serialize($element);
        }

        $this->memcached->set(
            $collection->getUuid(),
            $arrayOfElements,
            $ttl
        );

        if ($collection->getHeaders()) {
            $this->memcached->set(
                $collection->getUuid().'::headers',
                $collection->getHeaders()
            );
        }

        return $this->findByUuid($collection->getUuid());
    }

    /**
     * @param $collectionUuid
     *
     * @return mixed
     */
    public function delete($collectionUuid)
    {
        $this->memcached->delete($collectionUuid);
    }

    /**
     * @param $collectionUuid
     * @param $elementUuid
     *
     * @throws NotExistListElementException
     */
    public function deleteElement($collectionUuid, $elementUuid)
    {
        $arrayToReplace = $this->findByUuid($collectionUuid);
        unset($arrayToReplace[(string) $elementUuid]);

        $this->memcached->replace($collectionUuid, $arrayToReplace);
    }

    /**
     * @param $collectionUuid
     * @param $elementUuid
     *
     * @return bool
     */
    public function existsElement($collectionUuid, $elementUuid)
    {
        return @isset($this->findByUuid($collectionUuid)[$elementUuid]);
    }

    /**
     * @param $collectionUuid
     *
     * @return mixed
     */
    public function findByUuid($collectionUuid)
    {
        return $this->memcached->get($collectionUuid);
    }

    /**
     * @param $collectionUuid
     * @param $elementUuid
     *
     * @return mixed
     *
     * @throws NotExistListElementException
     */
    public function findElement($collectionUuid, $elementUuid)
    {
        if (!$this->existsElement($collectionUuid, $elementUuid)) {
            throw new NotExistListElementException('Cannot retrieve the element '.$elementUuid.' from the collection in memory.');
        }

        return unserialize($this->memcached->get($collectionUuid)[(string) $elementUuid]);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        $this->memcached->flush();
    }

    /**
     * @param $collectionUuid
     *
     * @return mixed
     */
    public function getHeaders($collectionUuid)
    {
        return $this->memcached->get($collectionUuid.'::headers');
    }

    /**
     * @return array
     */
    public function stats()
    {
        return $this->memcached->getStats();
    }
}
