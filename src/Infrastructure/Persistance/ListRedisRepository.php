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
use InMemoryList\Infrastructure\Persistance\Exception\ListAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exception\ListDoesNotExistsException;
use InMemoryList\Infrastructure\Persistance\Exception\ListElementDoesNotExistsException;
use Predis\Client;

class ListRedisRepository implements ListRepository
{
    /**
     * @var Client
     */
    private $client;

    /**
     * IMListRedisRepository constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->client->keys('*');
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

        /** @var ListElement $element */
        foreach ($collection->getItems() as $element) {
            $this->client->hmset(
                $collection->getUuid().self::HASH_SEPARATOR.$element->getUuid(),
                [
                    'body' => serialize($element->getBody()),
                    'created_at' => serialize($element->getCreatedAt()),
                ]
            );

            if ($ttl) {
                $this->client->expire($collection->getUuid().self::HASH_SEPARATOR.$element->getUuid(), $ttl);
            }
        }

        if ($collection->getHeaders()) {
            foreach ($collection->getHeaders() as $key => $header) {
                $this->client->hset(
                    $collection->getUuid().self::HEADERS_SEPARATOR.'headers',
                    $key,
                    $header
                );
            }

            if ($ttl) {
                $this->client->expire($collection->getUuid().self::HEADERS_SEPARATOR.'headers', $ttl);
            }
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
        $collection = $this->findByUuid($listUuid);

        foreach ($collection as $elementUuid) {
            $element = explode(self::HASH_SEPARATOR, $elementUuid);
            $this->deleteElement($listUuid, $element[1]);
        }
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        $this->client->del([$listUuid.self::HASH_SEPARATOR.$elementUuid]);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return array
     */
    public function existsElement($listUuid, $elementUuid)
    {
        return $this->client->keys($listUuid.self::HASH_SEPARATOR.$elementUuid);
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findByUuid($listUuid)
    {
        $a = [];
        $list = $this->client->keys($listUuid.self::HASH_SEPARATOR.'*');;

        foreach ($list as $elementUuid){
            $a[$elementUuid] = $this->findElement($listUuid, $elementUuid);
        }

        return $a;
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

        return unserialize($this->client->hget($listUuid.self::HASH_SEPARATOR.$elementUuid, 'body'));
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

        return unserialize($this->client->hget($listUuid.self::HASH_SEPARATOR.$elementUuid, 'created_at'));
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
        $this->client->flushall();
    }

    /**
     * @param $listUuid
     *
     * @return array
     */
    public function getHeaders($listUuid)
    {
        return $this->client->hgetall($listUuid.self::HEADERS_SEPARATOR.'headers');
    }

    /**
     * @return array
     */
    public function stats()
    {
        return $this->client->info();
    }

    /**
     * @param $key
     *
     * @return int
     */
    public function ttl($key)
    {
        return $this->client->ttl($key);
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
        $collection = $this->findByUuid($listUuid);

        if (!$collection) {
            throw new ListDoesNotExistsException('List '.$listUuid.' does not exists in memory.');
        }

        foreach ($collection as $elementUuid) {
            $this->client->expire($elementUuid, $ttl);
        }
    }
}
