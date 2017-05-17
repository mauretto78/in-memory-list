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

include __DIR__.'/../shared.php';

$start = microtime(true);
$apiUrl = 'https://jsonplaceholder.typicode.com/comments';
$apiArray = json_decode(file_get_contents($apiUrl));

$client = new Client('memcached', $memcached_params);
$collection = $client->findListByUuid('comments-list') ?:  $client->create($apiArray, [], 'comments-list', 'id');

// loop items
echo '<h3>Loop items</h3>';
foreach ($collection as $element) {
    $item = $client->item($element);

    echo '<p>';
    echo '<strong>postId</strong>: '.$item->postId.'<br>';
    echo '<strong>id</strong>: '.$item->id.'<br>';
    echo '<strong>name</strong>: '.$item->name.'<br>';
    echo '<strong>email</strong>: '.$item->email.'<br>';
    echo '<strong>body</strong>: '.$item->body.'<br>';
    echo '</p>';
}

echo ' ELAPSED TIME: '.$time_elapsed_secs = microtime(true) - $start;
