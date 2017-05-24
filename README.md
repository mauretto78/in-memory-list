# In-memory List

[![Build Status](https://travis-ci.org/mauretto78/in-memory-list.svg?branch=master)](https://travis-ci.org/mauretto78/in-memory-list)
[![license](https://img.shields.io/github/license/mauretto78/in-memory-list.svg)]()
[![Packagist](https://img.shields.io/packagist/v/mauretto78/in-memory-list.svg)]()

**In-memory List** easily allows you to create and save your lists in memory.

If you are looking for a caching system for your lists this library is suitable for you.

Grab your lists from your API, your database or whatever you want and store them in memory: then, you can quickly retrieve your lists from cache, sorting and performing queries on them.

This package uses:
 
* [Apcu](http://php.net/manual/en/book.apcu.php)
* [Memcached](https://memcached.org/)
* [Redis](https://redis.io/)

## Basic Usage

To create and store in memory you list do the following:


```php
use InMemoryList\Application\Client;

$array = [
    ...
]

$client = new Client();
$collection = $client->create($array);

foreach ($collection as $element){
    $item = $client->item($element);
    // ...
}

```

## Drivers

Avaliable drivers:

* `apcu` 
* `memcached` 
* `redis` (default driver)
 
```php
use InMemoryList\Application\Client;

// Apcu, no configuration is needed
$client = new Client('apcu');
// ..
```

```php
use InMemoryList\Application\Client;

// Memcached, you can pass one or more servers
$memcached_parameters = [
    [
        'host' => 'localhost',
        'port' => 11211
    ],
    [
        'host' => 'localhost',
        'port' => 11222
    ],
    // etc..
];

$client = new Client('memcached', $memcached_parameters);
// ..
```  
 
```php
use InMemoryList\Application\Client;

// Redis, please refer to PRedis library
$redis_parameters = [
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
    'options' => [
        'profile' => '3.0',
    ],
];

$client = new Client('redis', $redis_parameters);
// ..
```

Please refer to [official page](https://github.com/nrk/predis) for more details on PRedis connection.

## Parameters

When use `create` method to a generate a list, you can provide to it a parameters array. The allowed keys are:

* `uuid` - uuid of list
* `headers` - headers array for the list
* `element-uuid` - uuid for the list elements
* `index` - add the list elements to cache index or not
* `ttl` - time to live of the list (in seconds)

### uuid

You can assign an uuid to your list (instead, a [uuid](https://github.com/ramsey/uuid) will be generated):

```php
use InMemoryList\Application\Client;

$array = [
    ...
]

$client = new Client();
$collection = $client->create($array, [
    'uuid' => 'simple-array'
]);

// And now you can retrive the list:
$simpleArray = $client->findListByUuid('simple-array');

//..

```

Please note that the unique ID **must be a string**. 

### headers

You can set a headers array to your list:

```php
use InMemoryList\Application\Client;

$array = [
    ...
]

$headers = [
    'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
    'hash' => 'ec457d0a974c48d5685a7efa03d137dc8bbde7e3'
];

$client = new Client();
$collection = $client->create($array, [
    'uuid' => 'simple-array',
    'headers' => $headers
]);

// get headers
var_dump($client->getHeaders('simple-array'));

// ...
```

### element-uuid

You can assign an uuid to list elemens (instead, a [uuid](https://github.com/ramsey/uuid) will be generated). Consider this array:

```php
$simpleArray = [
    [
        "userId" => 1,
        "id" => 1,
        "title" => "sunt aut facere repellat provident occaecati excepturi optio reprehenderit",
        "body" =>  "quia et suscipit\nsuscipit recusandae consequuntur expedita et cum\nreprehenderit molestiae ut ut quas totam\nnostrum rerum est autem sunt rem eveniet architecto"
    ],
    ...
]
```

Maybe you would use `id` key as uuid in your list:

```php
use InMemoryList\Application\Client;

$client = new Client();
$collection = $client->create($simpleArray, [
    'uuid' => 'simple-array',
    'element-uuid' => 'id'
]);

// now to retrieve a single element, you can simply do:
$item1 = $client->item($collection['1']);
```

Please note that the uuid **must be a string**. 

### index

You can specify if you want to include the list in the cache index:

```php
use InMemoryList\Application\Client;

$client = new Client();
$collection = $client->create($array, [
    'uuid' => 'simple-array',
    'element-uuid' => 'id',
    'index' => true
]);

// now your list will be present in the cache index

// ..
```

### ttl

You can specify a ttl (in seconds) for your lists:

```php
use InMemoryList\Application\Client;

$client = new Client();
$collection = $client->create($array, [
    'uuid' => 'simple-array',
    'element-uuid' => 'id',
    'ttl' => 3600
]);

// ..
```

## Update an element

To update an element in you list, you can simply do this:

```php
// ..
$client->updateElement(
    $listUuid, 
    $elementUuid, 
    [
        'id' => 4325,
        'title' => 'New Title',
        // ..
    ]
);
```

## Sorting and Quering

You can perform queries on your list. You can concatenate criteria:

```php
use InMemoryList\Application\Client;
use InMemoryList\Application\QueryBuilder;

$array = [
    ...
]

$client = new Client();
$collection = $client->create($array, [
    'uuid' => 'simple-array'
]);
$qb = new QueryBuilder($collection);
$qb
    ->addCriteria('title', '...', 'CONTAINS')
    ->addCriteria('rate', '3', '>')
    ->orderBy('title');
    
foreach ($qb->getResults() as $element){
    $item = $client->item($element);
    // ...
}
```

You can use the following operators to perform your queries:

* `=` (default operator)
* `>`
* `<`
* `<=`
* `>=`
* `!=`
* `ARRAY`
* `CONTAINS` (case insensitive)

## Limit and Offset

You can specify limit/offset on your query results:

```php
use InMemoryList\Application\Client;
use InMemoryList\Application\QueryBuilder;

$array = [
    ...
]

$client = new Client();
$collection = $client->create($array, [
    'uuid' => 'simple-array'
]);
$qb = new QueryBuilder($collection);
$qb
    ->addCriteria('title', [...], 'ARRAY')
    ->addCriteria('rate', '3', '>')
    ->orderBy('title')
    ->limit(0, 10);
    
foreach ($qb->getResults() as $element){
    $item = $client->item($element);
    // ...
}
```

## Commands

If you have an application which uses [Symfony Console](https://github.com/symfony/console), you have some commands avaliable:

* `iml:cache:flush` to flush the cache
* `iml:cache:index` to get full index of items stored in cache
* `iml:cache:statistics` to get cache statistics 

You can register the commands in your app, consider this example:

```php
#!/usr/bin/env php

<?php
// Example of a Silex Application 'bin/console' file
// we use \Knp\Provider\ConsoleServiceProvider as ConsoleServiceProvider, use what you want

set_time_limit(0);

require __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$app->register(new \Knp\Provider\ConsoleServiceProvider(), array(
    'console.name'              => '...',
    'console.version'           => '...',
    'console.project_directory' => __DIR__.'/..'
));

$console = $app['console'];

// add commands here
...
$console->add(new \InMemoryList\Command\FlushCommand());
$console->add(new \InMemoryList\Command\IndexCommand());
$console->add(new \InMemoryList\Command\StatisticsCommand());
$console->run();
```

You can pass to commands your driver and connection parameters array. Example:

```php
$console->add(new \InMemoryList\Command\FlushCommand('redis', [
    'host' => '127.0.0.1',
    'port' => 6379,
]));
```

If you prefer you can use this syntax on command line:

`iml:cache:COMMAND DRIVER [key=value,key2=value2,key3=value3]`

Each string in square brackets represents an array, so to get a multi-server connection you have to pass arrays separated by space. Example:

`iml:cache:statistics memcached [host=localhost,port=11211] [host=localhost,port=11222]`

## Performance

Consider this simple piece of code:

```php
$start = microtime(true);

// create an array with n elements
// example:
// $from = 1
// $to = 10000
foreach (range($from, $to) as $number) {
    $array[] = [
        'id' => $number,
        'name' => 'Name '.$number,
        'email' => 'Email'.$number,
    ];
}

$apiArray = json_encode($array);

$client = new Client($driver, $parameters);
$collection = $client->findListByUuid('range-list') ?:  $client->create(json_decode($apiArray), ['uuid' => 'range-list', 'element-uuid' => 'id']);

foreach ($collection as $element) {
    $item = $client->item($element);
    echo '<p>';
    echo '<strong>id</strong>: '.$item->id.'<br>';
    echo '<strong>name</strong>: '.$item->name.'<br>';
    echo '<strong>email</strong>: '.$item->email.'<br>';
    echo '</p>';
}

echo ' ELAPSED TIME: '.$time_elapsed_secs = microtime(true) - $start;
```

A list with `n` elements is persisted. It was measured separately the time for displaying a simple `var_dump($collection)` and the whole list.

Here are the results obtained:

![Alt text](https://raw.githubusercontent.com/mauretto78/in-memory-list/master/examples/img/banchmark-1.jpg "Benchmark")

## Testing

In order to run all the test, you need to install **all the drivers** on your machine:

* APCU - [install via PECL](https://pecl.php.net/package/APCu)
* MEMCACHED - [install via PECL](https://pecl.php.net/package/memcached)
* REDIS - [official install guide](https://redis.io/topics/quickstart)

## Built With

* [PRedis](https://github.com/nrk/predis) - Flexible and feature-complete Redis client for PHP and HHVM
* [ramsey/uuid](https://github.com/ramsey/uuid) - A PHP 5.4+ library for generating RFC 4122 version 1, 3, 4, and 5 universally unique identifiers (UUID).
* [Symfony Console](https://github.com/symfony/console) - Symfony Console Component

## Authors

* **Mauro Cassani** - [github](https://github.com/mauretto78)

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details