<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
require __DIR__.'/../vendor/autoload.php';

$redis_params = [
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
    'options' => [
        'profile' => '3.0',
    ],
];

$memcached_params = [
    [
        'host' => 'localhost',
        'port' => 11211
    ],
];
