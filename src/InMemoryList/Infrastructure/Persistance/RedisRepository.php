<?php
/**
 * This file is part of the Simple EventStore Manager package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace InMemoryList\Infrastructure\Persistance;

use InMemoryList\Domain\Helper\ListElementConsistencyChecker;
use InMemoryList\Domain\Model\Contracts\ListRepositoryInterface;
use InMemoryList\Domain\Model\Exceptions\ListElementNotConsistentException;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListDoesNotExistsException;
use Predis\Client;

class RedisRepository extends AbstractRepository implements ListRepositoryInterface
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
     * @param null           $ttl
     * @param null           $chunkSize
     *
     * @return mixed
     *
     * @throws ListAlreadyExistsException
     */
    public function create(ListCollection $list, $ttl = null, $chunkSize = null)
    {
        // check if list already exists in memory
        $listUuid = (string) $list->getUuid();
        if ($this->existsListInIndex($listUuid) && $this->exists($listUuid)) {
            throw new ListAlreadyExistsException('List '.$list->getUuid().' already exists in memory.');
        }

        if (!$chunkSize && !is_int($chunkSize)) {
            $chunkSize = self::CHUNKSIZE;
        }

        $items = $list->getElements();

        // persist in memory array in chunks
        $arrayChunks = array_chunk($items, $chunkSize, true);

        $options = [
            'cas' => true,
            'watch' => $this->getArrayChunksKeys($listUuid, count($arrayChunks)),
            'retry' => 3,
        ];

        // persist all in a transaction
        $this->client->transaction($options, function ($tx) use ($arrayChunks, $list, $ttl, $listUuid, $items, $chunkSize) {
            foreach ($arrayChunks as $chunkNumber => $item) {
                foreach ($item as $key => $element) {
                    $listChunkUuid = $listUuid.self::SEPARATOR.self::CHUNK.'-'.($chunkNumber + 1);
                    $elementUuid = $element->getUuid();
                    $body = $element->getBody();

                    $tx->hset(
                        (string) $listChunkUuid,
                        (string) $elementUuid,
                        (string) $body
                    );

                    // set ttl
                    if ($ttl) {
                        $tx->expire(
                            (string) $listChunkUuid,
                            $ttl
                        );
                    }
                }
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

            // add list to index
            $this->addOrUpdateListToIndex(
                $listUuid,
                (int) count($items),
                (int) count($arrayChunks),
                (int) $chunkSize,
                $ttl
            );
        });
    }

    /**
     * @param $listUuid
     * @param $numberOfChunks
     * @return array
     */
    private function getArrayChunksKeys($listUuid, $numberOfChunks)
    {
        $arrayChunksKeys = [];
        for($i=0;$i<$numberOfChunks;$i++){
            $arrayChunksKeys[] = $listUuid.self::SEPARATOR.self::CHUNK.'-'.($i + 1);
        }

        return $arrayChunksKeys;
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        $numberOfChunks = $this->getNumberOfChunks($listUuid);
        $chunkSize = $this->getChunkSize($listUuid);

        $options = [
            'cas' => true,
            'watch' => $this->getArrayChunksKeys($listUuid, $numberOfChunks),
            'retry' => 3,
        ];

        // delete in a transaction
        $this->client->transaction($options, function ($tx) use ($listUuid, $numberOfChunks, $chunkSize, $elementUuid) {
            for ($i = 1; $i <= $numberOfChunks; ++$i) {
                $chunkNumber = $listUuid.self::SEPARATOR.self::CHUNK.'-'.$i;
                $chunk = $this->client->hgetall($chunkNumber);

                if (array_key_exists($elementUuid, $chunk)) {
                    // delete elements from chunk
                    $tx->hdel($chunkNumber, $elementUuid);

                    // update list index
                    $prevIndex = $this->getIndex($listUuid);
                    $this->addOrUpdateListToIndex(
                        $listUuid,
                        ($prevIndex['size'] - 1),
                        $numberOfChunks,
                        $chunkSize,
                        $prevIndex['ttl']
                    );

                    // delete headers if counter = 0
                    $headersKey = $listUuid.self::SEPARATOR.self::HEADERS;
                    if ($this->getCounter($listUuid) === 0) {
                        $tx->del($headersKey);
                    }

                    break;
                }
            }
        });
    }

    /**
     * @param $listUuid
     * @param $size
     * @param $numberOfChunks
     * @param null $ttl
     */
    private function addOrUpdateListToIndex($listUuid, $size, $numberOfChunks, $chunkSize, $ttl = null)
    {
        $indexKey = ListRepositoryInterface::INDEX;
        $this->client->hset(
            $indexKey,
            (string) $listUuid,
            serialize([
                'uuid' => $listUuid,
                'created_on' => new \DateTimeImmutable(),
                'size' => $size,
                'chunks' => $numberOfChunks,
                'chunk-size' => $chunkSize,
                'headers' => $this->getHeaders($listUuid),
                'ttl' => $ttl,
            ])
        );

        if ($size === 0) {
            $this->removeListFromIndex((string) $listUuid);
        }
    }

    /**
     * @param $listUuid
     *
     * @return bool
     */
    public function exists($listUuid)
    {
        $listFirstChunk = $this->client->hgetall($listUuid.self::SEPARATOR.self::CHUNK.'-1');

        return (count($listFirstChunk) === 0) ? false : true;
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        $collection = $this->client->hgetall($listUuid.self::SEPARATOR.self::CHUNK.'-1');
        $number = $this->getNumberOfChunks($listUuid);

        for ($i = 2; $i <= $number; ++$i) {
            $collection = array_merge($collection, $this->client->hgetall($listUuid.self::SEPARATOR.self::CHUNK.'-'.$i));
        }

        return array_map('unserialize', $collection);
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
     *
     * @return array
     */
    public function getIndex($listUuid = null)
    {
        $indexKey = ListRepositoryInterface::INDEX;
        $index = $this->client->hgetall($indexKey);
        $this->removeExpiredListsFromIndex($index);

        if ($listUuid) {
            return (isset($index[(string) $listUuid])) ? unserialize($this->client->hget($indexKey, $listUuid)) : null;
        }

        return array_map('unserialize', $this->client->hgetall($indexKey));
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
     *
     * @throws ListElementNotConsistentException
     *
     * @return mixed
     */
    public function pushElement($listUuid, ListElement $listElement)
    {
        $elementUuid = $listElement->getUuid();
        $body = $listElement->getBody();

        if (!ListElementConsistencyChecker::isConsistent($listElement, $this->findListByUuid($listUuid))) {
            throw new ListElementNotConsistentException('Element '.(string) $listElement->getUuid().' is not consistent with list data.');
        }

        $numberOfChunks = $this->getNumberOfChunks($listUuid);
        $chunkSize = $this->getChunkSize($listUuid);

        $chunkNumber = $listUuid.self::SEPARATOR.self::CHUNK.'-'.$numberOfChunks;

        $options = [
            'cas' => true,
            'watch' => $this->getArrayChunksKeys($listUuid, $numberOfChunks),
            'retry' => 3,
        ];

        // persist in a transaction
        $this->client->transaction($options, function ($tx) use ($chunkNumber, $numberOfChunks, $chunkSize, $listUuid, $elementUuid, $body) {
            if (($chunkSize - count($tx->hgetall($chunkNumber))) === 0) {
                ++$numberOfChunks;
                $chunkNumber = $listUuid.self::SEPARATOR.self::CHUNK.'-'.$numberOfChunks;
            }

            $tx->hset(
                (string) $chunkNumber,
                (string) $elementUuid,
                (string) $body
            );

            // update list index
            $prevIndex = $this->getIndex($listUuid);
            $this->addOrUpdateListToIndex(
                $listUuid,
                ($prevIndex['size'] + 1),
                $numberOfChunks,
                $chunkSize,
                $this->getTtl($listUuid)
            );
        });
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function removeListFromIndex($listUuid)
    {
        $this->client->hdel(
            ListRepositoryInterface::INDEX,
            $listUuid
        );
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     *
     * @throws ListElementNotConsistentException
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, $data)
    {
        $numberOfChunks = $this->getNumberOfChunks($listUuid);

        $options = [
            'cas' => true,
            'watch' => $this->getArrayChunksKeys($listUuid, $numberOfChunks),
            'retry' => 3,
        ];

        // persist in a transaction
        $this->client->transaction($options, function ($tx) use ($numberOfChunks, $listUuid, $elementUuid, $data) {
            for ($i = 1; $i <= $numberOfChunks; ++$i) {
                $chunkNumber = $listUuid.self::SEPARATOR.self::CHUNK.'-'.$i;
                $chunk = $this->client->hgetall($chunkNumber);

                if (array_key_exists($elementUuid, $chunk)) {
                    $listElement = $this->findElement(
                        (string) $listUuid,
                        (string) $elementUuid
                    );

                    $updatedElementBody = $this->updateListElementBody($listElement, $data);
                    if (!ListElementConsistencyChecker::isConsistent($updatedElementBody, $this->findListByUuid($listUuid))) {
                        throw new ListElementNotConsistentException('Element '.(string) $elementUuid.' is not consistent with list data.');
                    }

                    $updatedElement = new ListElement(
                        new ListElementUuid($elementUuid),
                        $updatedElementBody
                    );
                    $body = $updatedElement->getBody();

                    $tx->hset(
                        $chunkNumber,
                        $elementUuid,
                        $body
                    );

                    break;
                }
            }
        });
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
        if (!$this->findListByUuid($listUuid)) {
            throw new ListDoesNotExistsException('List '.$listUuid.' does not exists in memory.');
        }

        // update ttl of all chunks
        $numberOfChunks = $this->getNumberOfChunks($listUuid);

        $options = [
            'cas' => true,
            'watch' => $this->getArrayChunksKeys($listUuid, $numberOfChunks),
            'retry' => 3,
        ];

        // persist in a transaction
        $this->client->transaction($options, function ($tx) use ($numberOfChunks, $listUuid, $ttl) {
            for ($i = 1; $i <= $numberOfChunks; ++$i) {
                $tx->expire(
                    (string) $listUuid.self::SEPARATOR.self::CHUNK.'-'.$i,
                    (int) $ttl
                );
            }

            // update ttl of headers array (if present)
            if ($this->getHeaders($listUuid)) {
                $tx->expire($listUuid.self::SEPARATOR.self::HEADERS, $ttl);
            }

            // update index
            $this->addOrUpdateListToIndex(
                $listUuid,
                $this->getCounter($listUuid),
                $this->getNumberOfChunks($listUuid),
                $this->getChunkSize($listUuid),
                $ttl
            );
        });
    }
}
