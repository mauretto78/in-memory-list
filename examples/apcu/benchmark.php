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

$apiArray = json_encode($array);

$client = new Client('apcu');
$collection = $client->getRepository()->findListByUuid('range-list') ?: $client->create(json_decode($apiArray), ['uuid' => 'range-list', 'element-uuid' => 'id']);

// loop items
echo '<h3>Loop items</h3>';
foreach ($collection as $element) {
    echo '<p>';
    echo '<strong>id</strong>: '.$element->id.'<br>';
    echo '<strong>name</strong>: '.$element->name.'<br>';
    echo '<strong>email</strong>: '.$element->email.'<br>';
    echo '</p>';
}

echo ' ELAPSED TIME: '.$time_elapsed_secs = microtime(true) - $start;
