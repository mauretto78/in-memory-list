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
use InMemoryList\Domain\Helper\SerializeChecker;
use InMemoryList\Domain\Model\Contracts\ListRepositoryInterface;
use InMemoryList\Domain\Model\Exceptions\ListElementNotConsistentException;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListDoesNotExistsException;
use Predis\Client;

class PdoRepository extends AbstractRepository implements ListRepositoryInterface
{
    const LIST_COLLECTION_TABLE_NAME = 'list-collection';
    const LIST_ELEMENT_TABLE_NAME = 'list-element';

    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * PdoRepository constructor.
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->createListCollectionSchema();
        $this->createListElementSchema();
    }

    private function createListCollectionSchema()
    {
        $query = "CREATE TABLE IF NOT EXISTS `".self::LIST_COLLECTION_TABLE_NAME."` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `uuid` varchar(255) NOT NULL,
          `headers` text DEFAULT NULL,
          `created_at` datetime(6),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        $this->pdo->exec($query);
    }

    private function createListElementSchema()
    {
        $query = "CREATE TABLE IF NOT EXISTS `".self::LIST_ELEMENT_TABLE_NAME."` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `uuid` varchar(255) NOT NULL,
          `list` varchar(255) NOT NULL,
          `body` text DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        $this->pdo->exec($query);
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
        $sql = 'INSERT INTO `'.self::LIST_COLLECTION_TABLE_NAME.'` (
                    `uuid`,
                    `headers`,
                    `created_at`
                  ) VALUES (
                    :uuid,
                    :headers,
                    :created_at
            )';

        $data = [
            'uuid' => $list->getUuid(),
            'headers' => $list->getHeaders(),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u')
        ];

        $st[] = [
            'sql' => $sql,
            'data' => $data,
        ];

        foreach ($list->getElements() as $uuid => $element){
            /** @var ListElement $element */

            $sql = 'INSERT INTO `'.self::LIST_ELEMENT_TABLE_NAME.'` (
                    `uuid`,
                    `list`,
                    `body`
                  ) VALUES (
                    :uuid,
                    :list,
                    :body
            )';

            $data = [
                'uuid' => $uuid,
                'list' => $list->getUuid(),
                'body' => $element->getBody(),
            ];

            $st[] = [
                'sql' => $sql,
                'data' => $data,
            ];
        }

        $this->executeQueriesInATransaction($st);
    }

    /**
     * @param array $statements
     */
    private function executeQueriesInATransaction(array $statements)
    {
        try {
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);

            // beginTransaction
            $this->pdo->beginTransaction();

            foreach ($statements as $statement){
                if(isset($statement['sql'])){

                    $sql = $statement['sql'];
                    $data = isset($statement['data']) ? $statement['data'] : [];

                    $stmt = $this->pdo->prepare($sql);
                    if (!empty($data)) {
                        foreach ($data as $key => &$value){
                            if(is_array($value)){
                                $value = serialize($value);
                            }

                            $stmt->bindParam(':'.$key, $value);
                        }
                    }
                    $stmt->execute();
                }
            }

            // commit
            $this->pdo->commit();
        } catch(\PDOException $e){
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        $sql = 'DELETE FROM `'.self::LIST_ELEMENT_TABLE_NAME.'` WHERE `uuid` = :uuid AND `list` = :list';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':uuid', $elementUuid);
        $stmt->bindParam(':list', $listUuid);
        $stmt->execute();
    }

    /**
     * @param $listUuid
     *
     * @return bool
     */
    public function exists($listUuid)
    {
        return ($this->getCounter($listUuid) > 0) ? true : false;
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        $sql = 'SELECT 
                    `uuid`,
                    `list`,
                    `body`
                    FROM `'.self::LIST_ELEMENT_TABLE_NAME.'` 
                    WHERE `list` = :list';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam('list', $listUuid);
        $stmt->execute();

        $list = $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );

        if(count($list) === 0){
            return [];
        }

        foreach ($list as $item){
            $items[$item['uuid']] = unserialize($item['body']);
        }

        return $items;
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        $sql = 'DELETE FROM `'.self::LIST_COLLECTION_TABLE_NAME.'`';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $sql = 'DELETE FROM `'.self::LIST_ELEMENT_TABLE_NAME.'`';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
    }

    /**
     * @param $listUuid
     *
     * @return array
     */
    public function getHeaders($listUuid)
    {
        $sql = 'SELECT
                `headers`
                FROM `'.self::LIST_COLLECTION_TABLE_NAME.'` 
                WHERE `uuid` = :uuid';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':uuid', $listUuid);
        $stmt->execute();

        if($list = $stmt->fetchAll(\PDO::FETCH_ASSOC)){
            $headers = $list[0]['headers'];

            if (SerializeChecker::isSerialized($headers)) {
                return unserialize($headers);
            }

            return $headers;
        }

        return null;
    }

    /**
     * @param null $listUuid
     *
     * @return array
     */
    public function getIndex($listUuid = null)
    {
        $lt = self::LIST_ELEMENT_TABLE_NAME;
        $lc = self::LIST_COLLECTION_TABLE_NAME;

        $sql = 'SELECT
                `'.$lc.'`.`uuid`,
                `'.$lc.'`.`headers`,
                `'.$lc.'`.`created_at`
                FROM `'.$lt.'` 
                JOIN `'.$lc.'` 
                ON `'.$lt.'`.`list` = `'.$lc.'`.`uuid`';

        if($listUuid){
            $sql .= 'WHERE `'.$lc.'`.`uuid` = :list';
        }

        $stmt = $this->pdo->prepare($sql);
        if($listUuid){
            $stmt->bindParam(':list', $listUuid);
        }
        $stmt->execute();

        $list = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($list as $item){
            $index[$item['uuid']] = [
                'uuid' => $item['uuid'],
                'created_on' => \DateTime::createFromFormat('Y-m-d H:i:s.u', $item['created_at']),
                'size' => $stmt->rowCount(),
                'chunks' => 0,
                'chunk-size' => 0,
                'headers' => $this->getHeaders($item['uuid']),
                'ttl' => 0
            ];

            return $index;
        }

        return null;
    }

    /**
     * @return array
     */
    public function getStatistics()
    {
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

        $sql = 'INSERT INTO `'.self::LIST_ELEMENT_TABLE_NAME.'` (
                    `uuid`,
                    `list`,
                    `body`
                  ) VALUES (
                    :uuid,
                    :list,
                    :body
            )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':uuid', $elementUuid);
        $stmt->bindParam(':list', $listUuid);
        $stmt->bindParam(':body', $body);
        $stmt->execute();
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function removeListFromIndex($listUuid)
    {
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
        $listElement = $this->findElement(
            (string) $listUuid,
            (string) $elementUuid
        );

        $updatedElementBody = $this->updateListElementBody($listElement, $data);

        if (!ListElementConsistencyChecker::isConsistent($updatedElementBody, $this->findListByUuid($listUuid))) {
            throw new ListElementNotConsistentException('Element '.(string) $elementUuid.' is not consistent with list data.');
        }

        if(is_array($updatedElementBody) || is_object($updatedElementBody)){
            $updatedElementBody = serialize($updatedElementBody);
        }

        $sql = 'UPDATE `'.self::LIST_ELEMENT_TABLE_NAME.'` 
                    SET `body` = :body
                    WHERE `uuid` = :uuid';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':uuid', $elementUuid);
        $stmt->bindParam(':body', $updatedElementBody);
        $stmt->execute();
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
    }

    /**
     * @param $listUuid
     *
     * @return int
     */
    public function getCounter($listUuid)
    {
        $sql = 'SELECT `id` FROM `'.self::LIST_ELEMENT_TABLE_NAME.'` WHERE `list` = :list';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':list', $listUuid);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
