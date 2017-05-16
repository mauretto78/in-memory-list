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
     * @param ListCollection $list
     *
     * @return mixed
     *
     * @throws ListAlreadyExistsException
     */
    public function create(ListCollection $list, $ttl = null)
    {
        if ($this->findListByUuid($list->getUuid())) {
            throw new ListAlreadyExistsException('List '.$list->getUuid().' already exists in memory.');
        }

        /** @var ListElement $element */
        foreach ($list->getItems() as $element) {
            $this->client->hset(
                $list->getUuid(),
                $element->getUuid(),
                $element->getBody()
            );

            if ($ttl) {
                $this->client->expire($list->getUuid(), $ttl);
            }
        }

        if ($list->getHeaders()) {
            foreach ($list->getHeaders() as $key => $header) {
                $this->client->hset(
                    $list->getUuid().self::HEADERS_SEPARATOR.'headers',
                    $key,
                    $header
                );
            }

            if ($ttl) {
                $this->client->expire($list->getUuid().self::HEADERS_SEPARATOR.'headers', $ttl);
            }
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
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        $this->client->hdel($listUuid, $elementUuid);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return bool
     */
    public function existsElement($listUuid, $elementUuid)
    {
        return @isset($this->findListByUuid($listUuid)[$elementUuid]);
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        return $this->client->hgetall($listUuid);
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

        return $this->client->hget($listUuid, $elementUuid);
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
     * @param $listUuid
     *
     * @return int
     */
    public function ttl($listUuid)
    {
        return $this->client->ttl($listUuid);
    }

    public function updateElement($listUuid, $elementUuid, array $data = [])
    {
        $element = $this->findElement($listUuid, $elementUuid);
        $objMerged = (object)array_merge((array)$element, (array)$data);
        $updatedElement = new ListElement(
            new ListElementUuid($elementUuid),
            $objMerged
        );

        $this->client->hset(
            $listUuid,
            $elementUuid,
            $updatedElement->getBody()
        );
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

        $this->client->expire($listUuid, $ttl);

        return $this->findListByUuid($listUuid);
    }
}
