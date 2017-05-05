<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<assistenza@easy-grafica.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Domain\Model;

use Ramsey\Uuid\Uuid;

class ListElementUuId
{
    /**
     * @var string
     */
    private $uuid;

    /**
     * ListElementUUId constructor.
     *
     * @param null $uuid
     */
    public function __construct($uuid = null)
    {
        $this->_setUUid($uuid);
    }

    /**
     * @param null $uuid
     */
    public function _setUUid($uuid = null)
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
