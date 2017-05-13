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

$apiUrl = 'https://jsonplaceholder.typicode.com/comments';
$apiArray = json_decode(file_get_contents($apiUrl));

$client = new Client('redis', $redis_params);
$list = $client->findListByUuid('comments-list') ?:  $client->create($apiArray, [], 'comments-list', 'id');

// loop items
$start = microtime(true);

echo '<h3>Loop items</h3>';
foreach ($list as $element) {
    echo '<p>';
    echo '<strong>postId</strong>: '.$element->postId.'<br>';
    echo '<strong>id</strong>: '.$element->id.'<br>';
    echo '<strong>name</strong>: '.$element->name.'<br>';
    echo '<strong>email</strong>: '.$element->email.'<br>';
    echo '<strong>body</strong>: '.$element->body.'<br>';
    echo '</p>';
}

echo ' ELAPSED TIME: '.$time_elapsed_secs = microtime(true) - $start;
