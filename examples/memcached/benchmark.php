<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use InMemoryList\Application\Client;

include __DIR__.'/../../app/bootstrap.php';

$start = microtime(true);

$from = (isset($_GET['from'])) ?: 1;
$to = (isset($_GET['to'])) ?: 5000;
$range = range($from, $to);
$array = [];

foreach ($range as $number) {
    $array[] = [
        'id' => $number,
        'name' => 'Name '.$number,
        'email' => 'Email'.$number,
    ];
}

$client = new Client('memcached', $config['memcached_parameters']);

if(!$client->getRepository()->existsListInIndex('range-list')){
    $client->create($array, [
        'uuid' => 'range-list',
        'element-uuid' => 'id'
    ]);
}

$collection = $client->getRepository()->findListByUuid('range-list');

// loop items
echo '<h3>Loop items</h3>';
foreach ($collection as $element) {
    echo '<p>';
    echo '<strong>id</strong>: '.$element['id'].'<br>';
    echo '<strong>name</strong>: '.$element['name'].'<br>';
    echo '<strong>email</strong>: '.$element['email'].'<br>';
    echo '</p>';
}

echo ' ELAPSED TIME: '.$time_elapsed_secs = microtime(true) - $start;
