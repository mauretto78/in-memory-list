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

use InMemoryList\Domain\Model\Contracts\ListRepository;
use InMemoryList\Domain\Model\Exceptions\ListCollectionNotAllowedUuidException;
use Ramsey\Uuid\Uuid;

class ListCollectionUuid
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
     * @param null $uuid
     * @throws ListCollectionNotAllowedUuidException
     */
    public function _setUuid($uuid = null)
    {
        $notAllowedNames = [
            ListRepository::HASH_SEPARATOR,
            ListRepository::HEADERS_SEPARATOR,
            ListRepository::STATISTICS,
        ];

        foreach ($notAllowedNames as $notAllowedName) {
            if (strpos($uuid, $notAllowedName) !== false) {
                throw new ListCollectionNotAllowedUuidException('You can\'t use '. $uuid . ' in your uuid.');
            }
        }

        $this->uuid = str_replace(' ', '-', $uuid) ?: Uuid::uuid4()->toString();
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
