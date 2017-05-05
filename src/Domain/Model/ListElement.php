<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Domain\Model;

class ListElement
{
    /**
     * @var ListElementUuId
     */
    private $uuid;

    /**
     * @var mixed
     */
    private $body;

    /**
     * @var \DateTimeImmutable
     */
    private $created_at;

    /**
     * IMListElement constructor.
     *
     * @param ListElementUuId $uuid
     * @param $body
     */
    public function __construct(ListElementUuId $uuid, $body)
    {
        $this->_setUuid($uuid);
        $this->_setBody($body);
        $this->_setCreatedAt();
    }

    /**
     * @param ListElementUuId $uuid
     */
    private function _setUuid(ListElementUuId $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return ListElementUuId
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param $body
     */
    private function _setBody($body)
    {
        $this->body = serialize($body);
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return unserialize($this->body);
    }

    /**
     * set created at.
     */
    private function _setCreatedAt()
    {
        $this->created_at = new \DateTimeImmutable();
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }
}
