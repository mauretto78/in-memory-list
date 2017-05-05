<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Domain\Model\Contracts;

use InMemoryList\Domain\Model\ListCollection;

interface ListFactory
{
    /**
     * @param array $elements
     * @param null  $uuid
     *
     * @return ListCollection
     */
    public function create(array $elements, $uuid = null);
}
