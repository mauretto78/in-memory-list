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

$apiUrl = 'https://jsonplaceholder.typicode.com/photos';
$apiArray = json_decode(file_get_contents($apiUrl));

$client = new Client('redis', $redis_params);
$list = $client->findListByUuid('photos-list') ?:  $client->create($apiArray, [], 'photos-list', 'id');

// loop items
$start = microtime(true);

echo '<h3>Loop items</h3>';
foreach ($list as $element) {
    echo '<p>';
    echo '<strong>albumId</strong>: '.$element->albumId.'<br>';
    echo '<strong>id</strong>: '.$element->id.'<br>';
    echo '<strong>title</strong>: '.$element->title.'<br>';
    echo '<strong>url</strong>: '.$element->url.'<br>';
    echo '<strong>thumbnailUrl</strong>: '.$element->thumbnailUrl.'<br>';
    echo '</p>';
}

echo ' ELAPSED TIME: '.$time_elapsed_secs = microtime(true) - $start;
