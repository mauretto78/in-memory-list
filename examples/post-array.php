<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<assistenza@easy-grafica.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

use InMemoryList\Application\Client;
use InMemoryList\Application\QueryBuilder;

include __DIR__.'/shared.php';

$postArray = json_decode(file_get_contents(__DIR__.'/files/posts.json'));
$client = new Client();
$client->flush();
$collection = $client->create($postArray, 'post-array');

$qb = new QueryBuilder($collection);
$qb
    ->addCriteria('title', 'est', 'IN')
    ->orderBy('title');

foreach ($qb->getResults() as $element){
    $item = $client->item($element);

    echo '<p>';
    echo '<strong>id</strong>: ' . $item->id . '<br>';
    echo '<strong>title</strong>: ' . $item->title . '<br>';
    echo '</p>';
}