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
use Predis\Collection\Iterator\HashKey;
use Predis\Collection\Iterator\Keyspace;

include __DIR__.'/../shared.php';

$start = microtime(true);
$from = (isset($_GET['from'])) ?: 1;
$to = (isset($_GET['to'])) ?: 10000;
$range = range($from, $to);
$array = [];

foreach ($range as $number){
    $array[] = [
        'id' => $number,
        'name' => 'Name '. rand(20, 99),
        'email' => 'Email' . $number,
        'age' => rand(20, 99),
    ];
}
$apiArray = json_encode($array);

$client = new Client('redis', $redis_params);
$list = $client->findListByUuid('range-list') ?:  $client->create(json_decode($apiArray), [], 'range-list', 'id');

// loop items
echo '<h3>Loop items</h3>';
foreach ($list as $element) {
    $item = $client->getItem($element);

    echo '<p>';
    echo '<strong>id</strong>: '.$item->id.'<br>';
    echo '<strong>name</strong>: '.$item->name.'<br>';
    echo '<strong>email</strong>: '.$item->email.'<br>';
    echo '</p>';
}

echo ' ELAPSED TIME: '.$time_elapsed_secs = microtime(true) - $start;
