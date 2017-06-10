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
     * @var ListElementUuid
     */
    private $uuid;

    /**
     * @var mixed
     */
    private $body;

    /**
     * IMListElement constructor.
     *
     * @param ListElementUuid $uuid
     * @param $body
     */
    public function __construct(ListElementUuid $uuid, $body)
    {
        $this->_setUuid($uuid);
        $this->_setBody($body);
    }

    /**
     * @param ListElementUuid $uuid
     */
    private function _setUuid(ListElementUuid $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return ListElementUuid
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
        return $this->body;
    }
}
