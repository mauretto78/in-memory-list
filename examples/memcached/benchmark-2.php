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

$from = (isset($_GET['from'])) ?: 1;
$to = (isset($_GET['to'])) ?: 5000;
$range = range($from, $to);
$chunk_size = 10000;

$array = [];

foreach ($range as $number) {
    $array[] = [
        'id' => $number,
        'name' => 'Name '.$number,
        'email' => 'Email'.$number,
    ];
}

$apiArray = json_encode($array);
$array = json_decode($apiArray);

$memcached = new \Memcached();
$memcached->addServer('localhost', 11211);

// set counter
$memcached->set('lista:counter', count($array));

if(!$memcached->get('lista:chunk-1')){
    foreach (array_chunk($array, $chunk_size) as $chunk_number => $item){
        $arrayToPersist = [];
        foreach ($item as $key => $value){
            $arrayToPersist[$key] = serialize($value);
        }

        $memcached->set('lista:chunk-'.($chunk_number+1), $arrayToPersist);
    }
}

$counter = $memcached->get('lista:counter');
$number = ceil($counter / $chunk_size);

// questo è tutto il metodo findListByUuid
$collection = [];

for ($i=1; $i<=$number; $i++){
    if(empty($collection)){
        $collection = $memcached->get('lista:chunk-1');
    } else {
        array_merge($collection, $memcached->get('lista:chunk-'.$i));
    }
}

foreach ($collection as $key => $item){
    $item = unserialize($item);

    echo '<p>';
    echo '<strong>id</strong>: '.$item->id.'<br>';
    echo '<strong>name</strong>: '.$item->name.'<br>';
    echo '<strong>email</strong>: '.$item->email.'<br>';
    echo '</p>';
}

echo ' ELAPSED TIME: '.$time_elapsed_secs = microtime(true) - $start;
