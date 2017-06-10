<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class BaseTestCase extends TestCase
{
    /**
     * @var array
     */
    protected $redis_parameters;

    /**
     * @var array
     */
    protected $memcached_parameters;

    /**
     * setup configuration.
     */
    public function setUp()
    {
        $config = require __DIR__.'/../tests/bootstrap.php';
        $this->memcached_parameters = $config['memcached_parameters'];
        $this->redis_parameters = $config['redis_parameters'];
    }
}
