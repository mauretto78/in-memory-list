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
    /**
     * @var
     */
    protected $driver;

    /**
     * @var array
     */
    protected $defaultParameters;

    /**
     * BaseCommand constructor.
     * @param null|string $name
     * @param null $driver
     * @param array $defaultParameters
     */
    public function __construct($name, $driver = null, array $defaultParameters = [])
    {
        parent::__construct($name);

        $this->driver = $driver;
        $this->defaultParameters = $defaultParameters;
    }

    /**
     * @return mixed
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return array
     */
    public function getDefaultParameters()
    {
        return $this->defaultParameters;
    }

    /**
     * @param $driver
     * @param array $parameters
     * @return Client
     */
    protected function createClient($driver, array $parameters = [])
    {
        return new Client($driver, $parameters);
    }

    /**
     * @param array $parameters
     * @return array
     */
    protected function convertParametersArray(array $parameters = [])
    {
        $convertedArray = [];
        $array = [];

        foreach ($parameters as $param) {
            $param = explode(',', $param);

            if (count($param)) {
                foreach ($param as $p) {
                    $p = explode('=', $p);
                    if (count($p)) {
                        $array[@$p[0]] = @$p[1];
                    }
                }
            }

            $convertedArray[] = $array;
        }

        return $convertedArray;
    }
}
