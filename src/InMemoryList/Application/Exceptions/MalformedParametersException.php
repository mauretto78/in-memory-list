<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
namespace InMemoryList\Application\Exceptions;

class MalformedParametersException extends \Exception
{
    public function __construct()
    {
        parent::__construct("Malformed parameters array provided to Client create function.");
    }
}
