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

class RedisRepository extends AbstractRepository implements ListRepository
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
     * @param ListCollection $list
     * @param null $ttl
     * @param null $chunkSize
     *
     * @return mixed
     *
     * @throws ListAlreadyExistsException
     */
    public function create(ListCollection $list, $ttl = null, $chunkSize = null)
    {
        if (!$chunkSize and !is_int($chunkSize)) {
            $chunkSize = self::CHUNKSIZE;
        }

        $listUuid = (string)$list->getUuid();
        if ($this->findListByUuid($listUuid)) {
            throw new ListAlreadyExistsException('List '.$list->getUuid().' already exists in memory.');
        }

        $items = $list->getItems();

        // persist in memory array in chunks
        $arrayChunks = array_chunk($items, $chunkSize, true);
        foreach ($arrayChunks as $chunkNumber => $item) {
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
                }
            }
        }

        // add list to index
        $this->_addOrUpdateListToIndex(
            $listUuid,
            (int)count($items),
            (int)count($arrayChunks),
            (int)$chunkSize,
            $ttl
        );

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
     * @param $elementUuid
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        $numberOfChunks = $this->getNumberOfChunks($listUuid);
        $chunkSize = $this->getChunkSize($listUuid);

        for ($i=1; $i<=$numberOfChunks; $i++) {
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = $this->client->hgetall($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {

                // delete elements from chunk
                $this->client->hdel($chunkNumber, $elementUuid);

                // update list index
                $prevIndex = unserialize($this->getIndex($listUuid));
                $this->_addOrUpdateListToIndex(
                    $listUuid,
                    ($prevIndex['size'] - 1),
                    $numberOfChunks,
                    $chunkSize,
                    $prevIndex['ttl']
                );

                // delete headers if counter = 0
                $headersKey = $listUuid . self::SEPARATOR . self::HEADERS;
                if ($this->getCounter($listUuid) === 0) {
                    $this->client->del($headersKey);
                }

                break;
            }
        }
    }

    /**
     * @param $listUuid
     * @param $size
     * @param $numberOfChunks
     * @param null $ttl
     */
    private function _addOrUpdateListToIndex($listUuid, $size, $numberOfChunks, $chunkSize, $ttl = null)
    {
        $indexKey = ListRepository::INDEX;
        $this->client->hset(
            $indexKey,
            (string)$listUuid,
            serialize([
                'uuid' => $listUuid,
                'created_on' => new \DateTimeImmutable(),
                'size' => $size,
                'chunks' => $numberOfChunks,
                'chunk-size' => $chunkSize,
                'ttl' => $ttl
            ])
        );

        if ($size === 0) {
            $this->_removeListFromIndex((string)$listUuid);
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
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        $collection = [];
        $number = $this->getNumberOfChunks($listUuid);

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
        return $this->client->hgetall($listUuid.self::SEPARATOR.self::HEADERS);
    }

    /**
     * @param null $listUuid
     * @return array|string
     */
    public function getIndex($listUuid = null)
    {
        $indexKey = ListRepository::INDEX;
        if ($listUuid) {
            return $this->client->hget($indexKey, $listUuid);
        }

        return $this->client->hgetall($indexKey);
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
     * @param ListElement $listElement
     * @return mixed
     */
    public function pushElement($listUuid, ListElement $listElement)
    {
        $number = $this->getNumberOfChunks($listUuid);
        $chunkSize = $this->getChunkSize($listUuid);
        $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $number;

        if(($chunkSize - count($this->client->hgetall($chunkNumber))) === 0){
            ++$number;
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $number;
        }

        $elementUuid = $listElement->getUuid();
        $body = $listElement->getBody();

        $this->client->hset(
            (string)$chunkNumber,
            (string)$elementUuid,
            (string)$body
        );

        // update list index
        $prevIndex = unserialize($this->getIndex($listUuid));
        $this->_addOrUpdateListToIndex(
            $listUuid,
            ($prevIndex['size'] + 1),
            $number,
            $chunkSize,
            $this->getTtl($listUuid)
        );
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
        $number = $this->getNumberOfChunks($listUuid);

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
    public function updateTtl($listUuid, $ttl)
    {
        $list = $this->findListByUuid($listUuid);

        if (!$list) {
            throw new ListDoesNotExistsException('List '.$listUuid.' does not exists in memory.');
        }

        $this->_addOrUpdateListToIndex(
            $listUuid,
            $this->getCounter($listUuid),
            $this->getNumberOfChunks($listUuid),
            $ttl
        );

        $this->client->expire($listUuid, $ttl);
    }
}
