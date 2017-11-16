<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Infrastructure\Drivers\Exceptions;

class PdoMalformedConfigException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Malformed PDO config parameters provided.');
    }
}
