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
            $key = $collection->getUuid().self::HASH_SEPARATOR.$element->getUuid();
            $arrayOfElements[(string) $element->getUuid()] = $key;

            $this->memcached->set(
                $key,
                [
                    'body' => serialize($element->getBody()),
                    'created_at' => serialize($element->getCreatedAt()),
                ],
                $ttl
            );
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
     * @param $listUuid
     *
     * @return mixed
     */
    public function delete($listUuid)
    {
        foreach ($this->findByUuid($listUuid) as $elementUuid){
            $this->memcached->delete($elementUuid);
        }

        $this->memcached->delete($listUuid);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @throws ListElementDoesNotExistsException
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        $key = $listUuid.self::HASH_SEPARATOR.$elementUuid;
        $collection = $this->findByUuid($listUuid);
        unset($collection[(string)$elementUuid]);

        $this->memcached->replace($listUuid, $collection);
        $this->memcached->delete($key);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return bool
     */
    public function existsElement($listUuid, $elementUuid)
    {
        $key = $listUuid.self::HASH_SEPARATOR.$elementUuid;

        return $this->memcached->get($key);
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findByUuid($listUuid)
    {
        return $this->memcached->get($listUuid);
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

        $key = $listUuid.self::HASH_SEPARATOR.$elementUuid;

        return unserialize($this->memcached->get($key)['body']);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @return mixed
     * @throws ListElementDoesNotExistsException
     */
    public function findCreationDateOfElement($listUuid, $elementUuid)
    {
        if (!$this->existsElement($listUuid, $elementUuid)) {
            throw new ListElementDoesNotExistsException('Cannot retrieve the element '.$elementUuid.' from the collection in memory.');
        }

        $key = $listUuid.self::HASH_SEPARATOR.$elementUuid;

        return unserialize($this->memcached->get($key)['created_at']);
    }

    /**
     * @param $completeCollectionElementUuid
     * @return mixed
     */
    public function findElementByCompleteCollectionElementUuid($completeCollectionElementUuid)
    {
        $completeCollectionElementUuidArray = explode(self::HASH_SEPARATOR, $completeCollectionElementUuid);
        $listUuid = $completeCollectionElementUuidArray[0];
        $elementUuid = $completeCollectionElementUuidArray[1];

        return $this->findElement($listUuid, $elementUuid);
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
        return $this->memcached->get($listUuid.self::HEADERS_SEPARATOR.'headers');
    }



    /**
     * @return array
     */
    public function stats()
    {
        return $this->memcached->getStats();
    }

    /**
     * @param $listUuid
     *
     * @return int
     */
    public function ttl($listUuid)
    {
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
        if (!$this->findByUuid($listUuid)) {
            throw new ListDoesNotExistsException('List '.$listUuid.' does not exists in memory.');
        }

        foreach ($this->findByUuid($listUuid) as $elementUuid){
            $this->memcached->touch($elementUuid, $ttl);
        }
        $this->memcached->touch($listUuid, $ttl);

        return $this->findByUuid($listUuid);
    }
}
