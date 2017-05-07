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

$userArray = json_decode(file_get_contents(__DIR__.'/files/users.json'));
$client = new Client();
$client->flush();
$collection = $client->create($userArray, 'user-array');

$qb = new QueryBuilder($collection);
$qb->orderBy('id', 'DESC');

foreach ($qb->getResults() as $element) {
    $item = $client->item($element);

    echo '<p>';
    echo '<strong>id</strong>: '.$item->id.'<br>';
    echo '<strong>name</strong>: '.$item->name.'<br>';
    echo '<strong>username</strong>: '.$item->username.'<br>';
    echo '<strong>email</strong>: '.$item->email.'<br>';
    echo '<strong>address</strong>: '.$item->address->street.', '.$item->address->suite.' - '.$item->address->zipcode.'<br>';
    echo '</p>';
}
