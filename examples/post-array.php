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
use InMemoryList\Application\QueryBuilder;

include __DIR__.'/shared.php';

$postArray = json_decode(file_get_contents(__DIR__.'/files/posts.json'));
$client = new Client();
$client->flush();
$collection = $client->create($postArray, [], 'post-array', 'id');

$qb = new QueryBuilder($collection);
$qb
    ->addCriteria('title', 'est', 'IN')
    ->orderBy('title');

foreach ($qb->getResults() as $element) {
    $item = $client->item($element);

    echo '<p>';
    echo '<strong>id</strong>: '.$item->id.'<br>';
    echo '<strong>title</strong>: '.$item->title.'<br>';
    echo '</p>';
}

// find a single element
$item1 = $client->item($collection['1']);

echo '<h3>Single item(1)</h3>';
echo '<p>';
echo '<strong>id</strong>: '.$item1->id.'<br>';
echo '<strong>title</strong>: '.$item1->title.'<br>';
echo '</p>';
