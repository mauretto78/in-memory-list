<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Command;

use InMemoryList\Application\Client;
use Symfony\Component\Console\Command\Command;

class BaseCommand extends Command
{
    protected function createClient($driver, array $params = [])
    {
        return new Client($driver, $this->_convertParamsArray($params));
    }

    /**
     * @param array $params
     * @return array
     */
    private function _convertParamsArray(array $params = [])
    {
        $convertedParamsArray = [];

        foreach ($params as $param) {
            $param = str_replace(['[',']'], '', $param);
            $param = explode(',', $param);

            if (count($param)) {
                foreach ($param as $p) {
                    $p = explode(':', $p);
                    if (count($p)) {
                        $convertedParamsArray[@$p[0]] = @$p[1];
                    }
                }
            }
        }

        return $convertedParamsArray;
    }
}

// php bin/console iml:ca:stat redis [host:localhost,port:11211] [host:localhost,port:11212]
