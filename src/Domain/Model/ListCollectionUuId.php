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

use Ramsey\Uuid\Uuid;

class ListCollectionUuId
{
    /**
     * @var string
     */
    private $uuid;

    /**
     * ListCollectionUUId constructor.
     *
     * @param null $uuid
     */
    public function __construct($uuid = null)
    {
        $this->_setUuid($uuid);
    }

    /**
     * @return mixed
     */
    public function _setUuid($uuid = null)
    {
        $this->uuid = $uuid ?: Uuid::uuid4()->toString();
    }

    /**
     * @return mixed
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getUuid();
    }
}
