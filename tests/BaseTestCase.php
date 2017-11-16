<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\tests;

use PHPUnit\Framework\TestCase;

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
     * @var
     */
    protected $pdo_parameters;

    /**
     * setup configuration.
     */
    public function setUp()
    {
        $config = require __DIR__.'/../app/bootstrap.php';
        $this->memcached_parameters = $config['memcached_parameters'];
        $this->redis_parameters = $config['redis_parameters'];
        $this->pdo_parameters = $config['pdo_parameters'];
    }
}
