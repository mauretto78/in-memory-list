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
    protected $parameters;

    /**
     * BaseCommand constructor.
     *
     * @param null|string $name
     * @param null        $driver
     * @param array       $parameters
     */
    public function __construct($name, $driver = null, array $parameters = [])
    {
        parent::__construct($name);

        $this->driver = $driver;
        $this->parameters = $parameters;
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
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param $driver
     * @param array $parameters
     *
     * @return Client
     */
    protected function createClient($driver, array $parameters = [])
    {
        return new Client($driver, $parameters);
    }
}
