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
     * @param null $index
     *
     * @return mixed
     *
     * @throws ListAlreadyExistsException
     */
    public function create(ListCollection $list, $ttl = null, $index = null)
    {
        $listUuid = $list->getUuid();

        if ($this->findListByUuid($listUuid)) {
            throw new ListAlreadyExistsException('List '.$list->getUuid().' already exists in memory.');
        }

        // set counter
        $this->client->set(
            (string)$list->getUuid().self::SEPARATOR.self::COUNTER,
            count($list->getItems())
        );

        // persist in memory array in chunks
        foreach (array_chunk($list->getItems(), self::CHUNKSIZE) as $chunkNumber => $item){
            foreach ($item as $key => $element){

                $listChunkUuid = $list->getUuid().self::SEPARATOR.self::CHUNK.'-'.($chunkNumber+1);
                $elementUuid = $element->getUuid();
                $body = $element->getBody();

                $this->client->hset(
                    (string)$listChunkUuid,
                    (string)$elementUuid,
                    (string)$body
                );

                // add elements to general index
                if($index){
                    $this->_addOrUpdateElementToIndex(
                        $elementUuid,
                        strlen($body),
                        $ttl
                    );
                }

                // set ttl
                if ($ttl) {
                    $this->client->expire(
                        (string)$listChunkUuid,
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
                $this->client->expire($listUuid.self::SEPARATOR.self::COUNTER, $ttl);
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

        for ($i=1; $i<=$number; $i++){
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = $this->client->hgetall($chunkNumber);

            if(array_key_exists($elementUuid, $chunk)){
                $this->client->hdel($chunkNumber, $elementUuid);

                if($this->_existsElementInIndex($elementUuid)){
                    $this->_removeElementToIndex($elementUuid);
                }

                $this->client->decr($this->getCounter($listUuid));
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
        $number = ceil($this->getCounter($listUuid) / self::CHUNKSIZE);

        for ($i=1; $i<=$number; $i++){
            if(empty($collection)){
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
     * @param $elementUuid
     * @param $size
     * @param null $ttl
     */
    private function _addOrUpdateElementToIndex($elementUuid, $size, $ttl = null)
    {
        $this->client->hset(
            ListRepository::INDEX,
            $elementUuid,
            serialize([
                'uuid' => $elementUuid,
                'created_on' => new \DateTimeImmutable(),
                'ttl' => $ttl,
                'size' => $size
            ])
        );
    }

    /**
     * @param $elementUuid
     */
    private function _removeElementToIndex($elementUuid)
    {
        $this->client->hdel(
            ListRepository::INDEX,
            $elementUuid
        );
    }

    /**
     * @param $elementUuid
     * @return string
     */
    private function _existsElementInIndex($elementUuid)
    {
        return (!$this->client->hget(ListRepository::INDEX, $elementUuid)) ? false : true;
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
     * @param null $ttl
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, array $data = [], $ttl = null)
    {
        $number = ceil($this->getCounter($listUuid) / self::CHUNKSIZE);

        for ($i=1; $i<=$number; $i++){
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = $this->client->hgetall($chunkNumber);

            if(array_key_exists($elementUuid, $chunk)){

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

                if($this->_existsElementInIndex($elementUuid)){
                    $this->_addOrUpdateElementToIndex(
                        $elementUuid,
                        strlen($body),
                        $ttl
                    );
                }

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

        foreach ($list as $elementUuid => $element) {
            if($this->_existsElementInIndex($elementUuid)){
                $this->_addOrUpdateElementToIndex($elementUuid, $ttl);
            }
        }

        $this->client->expire($listUuid, $ttl);

        return $this->findListByUuid($listUuid);
    }
}
