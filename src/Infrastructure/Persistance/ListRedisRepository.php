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
use InMemoryList\Infrastructure\Persistance\Exception\CollectionAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exception\NotExistListElementException;
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

        /** @var ListElement $element */
        foreach ($collection->getItems() as $element) {
            $this->client->hset(
                $collection->getUuid(),
                $element->getUuid(),
                serialize($element)
            );

            if ($ttl) {
                $this->client->expire($collection->getUuid(), $ttl);
            }
        }

        if ($collection->getHeaders()) {
            foreach ($collection->getHeaders() as $key => $header) {
                $this->client->hset(
                    $collection->getUuid().'::headers',
                    $key,
                    $header
                );
            }
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
        $collection = $this->findByUuid($collectionUuid);

        foreach ($collection as $element) {
            /** @var ListElement $element */
            $element = unserialize($element);
            $this->deleteElement($collectionUuid, $element->getUuid());
        }
    }

    /**
     * @param $collectionUuid
     * @param $elementUuid
     */
    public function deleteElement($collectionUuid, $elementUuid)
    {
        $this->client->hdel($collectionUuid, $elementUuid);
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
        return $this->client->hgetall($collectionUuid);
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

        return unserialize($this->findByUuid($collectionUuid)[$elementUuid]);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        $this->client->flushall();
    }

    /**
     * @param $collectionUuid
     *
     * @return array
     */
    public function getHeaders($collectionUuid)
    {
        return $this->client->hgetall($collectionUuid.'::headers');
    }

    /**
     * @param $collectionUuid
     *
     * @return int
     */
    public function ttl($collectionUuid)
    {
        return $this->client->ttl($collectionUuid);
    }
}
