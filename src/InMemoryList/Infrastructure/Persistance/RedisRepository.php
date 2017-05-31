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

class RedisRepository implements ListRepository
{
    /**
     * @var int
     */
    private $chunkSize;

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

        $listUuid = $list->getUuid();
        $counterUuid = (string)$list->getUuid() . self::SEPARATOR . self::COUNTER;

        if ($this->findListByUuid($listUuid)) {
            throw new ListAlreadyExistsException('List '.$list->getUuid().' already exists in memory.');
        }

        // set counter
        $items = $list->getItems();
        $this->client->set(
            $counterUuid,
            (int)count($items)
        );

        // persist in memory array in chunks
        foreach (array_chunk($items, $this->chunkSize, true) as $chunkNumber => $item) {
            foreach ($item as $key => $element) {
                $listChunkUuid = $list->getUuid().self::SEPARATOR.self::CHUNK.'-'.($chunkNumber+1);
                $elementUuid = $element->getUuid();
                $body = $element->getBody();

                $this->client->hset(
                    (string)$listChunkUuid,
                    (string)$elementUuid,
                    (string)$body
                );

                // set ttl
                if ($ttl) {
                    $this->client->expire(
                        (string)$listChunkUuid,
                        $ttl
                    );
                    $this->client->expire(
                        (string)$counterUuid,
                        $ttl
                    );
                }
            }
        }

        // add list to general index
        if ($index) {
            $this->_addOrUpdateListToIndex($listUuid, count($items), $ttl);
        }

        // set headers
        if ($list->getHeaders()) {
            foreach ($list->getHeaders() as $key => $header) {
                $this->client->hset(
                    $listUuid.self::SEPARATOR.self::HEADERS,
                    $key,
                    $header
                );
            }

            if ($ttl) {
                $this->client->expire($listUuid.self::SEPARATOR.self::HEADERS, $ttl);
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
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        $number = ceil($this->getCounter($listUuid) / self::CHUNKSIZE);

        for ($i=1; $i<=$number; $i++) {
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = $this->client->hgetall($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {

                // delete elements from chunk
                $this->client->hdel($chunkNumber, $elementUuid);

                // decr counter and delete counter and headers if counter = 0
                $indexKey = $listUuid . self::SEPARATOR . self::COUNTER;
                $counter = $this->client->decr($indexKey);
                if($counter === 0){
                    $this->client->del([
                        $indexKey,
                        $listUuid.self::SEPARATOR.self::HEADERS
                    ]);
                }

                // update list index
                if ($this->_existsListInIndex($listUuid)) {
                    $prevIndex = $this->client->hget(ListRepository::INDEX, $listUuid);
                    $prevIndex = unserialize($prevIndex);
                    $this->_addOrUpdateListToIndex($listUuid, ($prevIndex['size'] - 1), $prevIndex['ttl']);
                }

                break;
            }
        }
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function existsElement($listUuid, $elementUuid)
    {
        return @$this->findListByUuid($listUuid)[$elementUuid];
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        $collection = [];
        $number = ceil($this->getCounter($listUuid) / $this->chunkSize);

        for ($i=1; $i<=$number; $i++) {
            if (empty($collection)) {
                $collection = $this->client->hgetall($listUuid.self::SEPARATOR.self::CHUNK.'-1');
            } else {
                $collection = array_merge($collection, $this->client->hgetall($listUuid.self::SEPARATOR.self::CHUNK.'-'.$i));
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
        $this->client->flushall();
    }

    /**
     * @param $listUuid
     * @return mixed
     */
    public function getCounter($listUuid)
    {
        return $this->client->get($listUuid.self::SEPARATOR.self::COUNTER);
    }

    /**
     * @param $listUuid
     *
     * @return array
     */
    public function getHeaders($listUuid)
    {
        return $this->client->hgetall($listUuid.self::SEPARATOR.self::HEADERS);
    }

    /**
     * @return array
     */
    public function getIndex()
    {
        return $this->client->hgetall(ListRepository::INDEX);
    }

    /**
     * @param $listUuid
     * @param int $listCount
     * @param null $ttl
     */
    private function _addOrUpdateListToIndex($listUuid, $listCount, $ttl = null)
    {
        $indexKey = ListRepository::INDEX;
        $this->client->hset(
            $indexKey,
            $listUuid,
            serialize([
                'uuid' => $listUuid,
                'created_on' => new \DateTimeImmutable(),
                'size' => $listCount,
                'ttl' => $ttl
            ])
        );

        if($ttl){
            $this->client->expire($indexKey, $ttl);
        }

        if($listCount === 0) {
            $this->_removeListFromIndex($listUuid);
        }
    }

    /**
     * @param $listUuid
     */
    private function _removeListFromIndex($listUuid)
    {
        $this->client->hdel(
            ListRepository::INDEX,
            $listUuid
        );
    }

    /**
     * @param $listUuid
     * @return bool
     */
    private function _existsListInIndex($listUuid)
    {
        return $this->client->hget(ListRepository::INDEX, $listUuid) !== null;
    }

    /**
     * @return array
     */
    public function getStatistics()
    {
        return $this->client->info();
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, array $data = [])
    {
        $number = ceil($this->getCounter($listUuid) / $this->chunkSize);

        for ($i=1; $i<=$number; $i++) {
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = $this->client->hgetall($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {
                $element = $this->findElement($listUuid, $elementUuid);
                $objMerged = (object) array_merge((array) $element, (array) $data);
                $updatedElement = new ListElement(
                    new ListElementUuid($elementUuid),
                    $objMerged
                );
                $body = $updatedElement->getBody();

                $this->client->hset(
                    $chunkNumber,
                    $elementUuid,
                    $body
                );

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
        $list = $this->findListByUuid($listUuid);

        if (!$list) {
            throw new ListDoesNotExistsException('List '.$listUuid.' does not exists in memory.');
        }

        if($this->_existsListInIndex($listUuid)){
            $this->_addOrUpdateListToIndex($listUuid, $ttl);
        }

        $this->client->expire($listUuid, $ttl);

        return $this->findListByUuid($listUuid);
    }
}
