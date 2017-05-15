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
use InMemoryList\Infrastructure\Persistance\Exception\ListAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exception\ListDoesNotExistsException;
use InMemoryList\Infrastructure\Persistance\Exception\ListElementDoesNotExistsException;

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
     * @throws ListAlreadyExistsException
     */
    public function create(ListCollection $collection, $ttl = null)
    {
        if ($this->findByUuid($collection->getUuid())) {
            throw new ListAlreadyExistsException('List '.$collection->getUuid().' already exists in memory.');
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
                $collection->getUuid().self::HEADERS_SEPARATOR.'headers',
                $collection->getHeaders(),
                $ttl
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
     * @throws ListElementDoesNotExistsException
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
     * @throws ListElementDoesNotExistsException
     */
    public function findElement($collectionUuid, $elementUuid)
    {
        if (!$this->existsElement($collectionUuid, $elementUuid)) {
            throw new ListElementDoesNotExistsException('Cannot retrieve the element '.$elementUuid.' from the collection in memory.');
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
        return $this->memcached->get($collectionUuid.self::HEADERS_SEPARATOR.'headers');
    }

    /**
     * @return array
     */
    public function stats()
    {
        return $this->memcached->getStats();
    }

    /**
     * @param $collectionUuid
     *
     * @return int
     */
    public function ttl($collectionUuid)
    {
    }

    /**
     * @param $collectionUuid
     * @param $elementUuid
     * @param array $data
     *
     * @return mixed
     */
    public function updateElement($collectionUuid, $elementUuid, array $data = [])
    {
        $element = $this->findElement($collectionUuid, $elementUuid);
        $objMerged = (object)array_merge((array)$element->getBody(), (array)$data);
        $arrayOfElements = $this->memcached->get($collectionUuid);
        $updatedElement = new ListElement(
            new ListElementUuid($elementUuid),
            $objMerged
        );
        $arrayOfElements[(string) $elementUuid] = serialize($updatedElement);

        $this->memcached->replace(
            $collectionUuid,
            $arrayOfElements
        );
    }

    /**
     * @param $collectionUuid
     * @param null $ttl
     *
     * @return mixed
     *
     * @throws ListDoesNotExistsException
     */
    public function updateTtl($collectionUuid, $ttl = null)
    {
        if (!$this->findByUuid($collectionUuid)) {
            throw new ListDoesNotExistsException('List '.$collectionUuid.' does not exists in memory.');
        }

        $this->memcached->touch($collectionUuid, $ttl);

        return $this->findByUuid($collectionUuid);
    }
}
