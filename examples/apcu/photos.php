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
$apiUrl = 'https://jsonplaceholder.typicode.com/photos';
$apiArray = json_decode(file_get_contents($apiUrl));

$client = new Client('apcu');
$collection = $client->getRepository()->findListByUuid('photos-list') ?: $client->create($apiArray, ['uuid' => 'photos-list', 'element-uuid' => 'id']);

// loop items
echo '<h3>Loop items</h3>';
foreach ($collection as $element) {
    echo '<p>';
    echo '<strong>albumId</strong>: '.$element->albumId.'<br>';
    echo '<strong>id</strong>: '.$element->id.'<br>';
    echo '<strong>title</strong>: '.$element->title.'<br>';
    echo '<strong>url</strong>: '.$element->url.'<br>';
    echo '<strong>thumbnailUrl</strong>: '.$element->thumbnailUrl.'<br>';
    echo '</p>';
}

echo ' ELAPSED TIME: '.$time_elapsed_secs = microtime(true) - $start;
