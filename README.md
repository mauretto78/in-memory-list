# In-memory List

[![Build Status](https://travis-ci.org/mauretto78/in-memory-list.svg?branch=master)](https://travis-ci.org/mauretto78/in-memory-list)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/84d8322496cb4a11bdc0ca01a4271b52)](https://www.codacy.com/app/mauretto78/in-memory-list?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=mauretto78/in-memory-list&amp;utm_campaign=Badge_Grade)
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

// you have to use arrays
// you can't use URI string like 'tcp://10.0.0.1:6379'
// please refer to PRedis library documentation
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

Refer to [official page](https://github.com/nrk/predis) for more details on PRedis connection.

## Parameters

When use `create` method to a generate a list, you can provide to it a parameters array. The allowed keys are:

* `uuid` - uuid of list
* `element-uuid` - uuid for the list elements
* `headers` - headers array for the list
* `chunk-size` - the chunks size in which the array will be splitted (integer)
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

### chunk-size

You can specify the number of elements of each chunk in which the original array will be splitted. The default value is `1000`.

```php
use InMemoryList\Application\Client;

$client = new Client();
$collection = $client->create($array, [
    'uuid' => 'simple-array',
    'element-uuid' => 'id',
    'chunk-size' => 1500
]);

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

## Delete an element

To delete an element in you list do this:

```php
// ..
$client->deleteElement(
    $listUuid, 
    $elementUuid,
);
```

## Push an element

To push an element in you list, use `pushElement` function. You must provide the list uuid, the element uuid and element data (data must be consistent - see Validation). Look at this example:

```php
// ..
$client->pushElement(
    'fake-list-uuid',
    5001,
    [
        'id' => 5001,
        'name' => 'Name 5001',
        'email' => 'Email 5001',
    ]
);
```

## Update an element

To update an element in you list, use `updateElement` function. You must provide the list uuid, the element uuid and element data (data must be consistent - see Validation). Look at this example:

```php
// ..
$client->updateElement(
    'list-to-update', 
    4325, 
    [
        'id' => 4325,
        'title' => 'New Title',
        // ..
    ]
);
```

## Ttl

You can update ttl of a persisted list with `updateTtl` method, and retrive the ttl with `getTtl` function:

```php
// ...
$client->updateTtl(
    'your-list-uuid',
    3600 // ttl in seconds
);

// get Ttl of the list
$client->getTtl('your-list-uuid'); // 3600
```

## Validation (Data consistency)

Please note that your data **must be consistent**:

```php
// simple string list
$stringArray = [
    'Lorem Ipsum',
    'Ipse Dixit',
    'Dolor facium',
];

$collection = $client->create($stringArray, [
    'uuid' => 'string-array',
    'ttl' => 3600
]);

// array list, you must provide elements with consistent structure
$listArray[] = [
    'id' => 1,
    'title' => 'Lorem Ipsum',
];
$listArray[] = [
    'id' => 2,
    'title' => 'Ipse Dixit',
];
$listArray[] = [
    'id' => 3,
    'title' => 'Dolor facium',
];

$collection = $client->create($listArray, [
    'uuid' => 'simple-array',
    'element-uuid' => 'id',
    'ttl' => 3600
]);

// entity list, the objects must have the same properties 
$entityArray[] = new User(1, 'Mauro');
$entityArray[] = new User(2, 'Cristina');
$entityArray[] = new User(3, 'Lilli');

$collection = $client->create($entityArray, [
    'uuid' => 'entity-array',
    'element-uuid' => 'id',
    'ttl' => 3600
]);

```

Instead, a `ListElementNotConsistentException` will be thrown. Example:

```php

// ListElementNotConsistentException will be thrown
$listArray[] = [
    'id' => 1,
    'title' => 'Lorem Ipsum',
];
$listArray[] = [
    'id' => 2,
    'non-consistent-key' => 'Ipse Dixit',
];
$listArray[] = [
    'id' => 3,
    'title' => 'Dolor facium',
];

$collection = $client->create($listArray, [
    'uuid' => 'simple-array',
    'element-uuid' => 'id',
    'ttl' => 3600
]);

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
* `ARRAY_INVERSED`
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

`iml:cache:COMMAND DRIVER key=value,key2=value2,key3=value3`

Each string represents an array, so to get a multi-server connection you have to pass arrays separated by space. Example:

`iml:cache:statistics memcached host=localhost,port=11211 host=localhost,port=11222`

## Testing

In order to run all the test, you need to install **all the drivers** on your machine:

* APCU - [(install via PECL)](https://pecl.php.net/package/APCu) 
* MEMCACHED - [(install via PECL)](https://pecl.php.net/package/memcached) 
* REDIS - [(official install guide)](https://redis.io/topics/quickstart)

Once installed all the drivers, create a file called `config/parameters.yml` and paste in the content of `config/parameters.dist.yml`. Finally, change your configuration if needed:

```yaml
redis_parameters:
  scheme: 'tcp'
  host: '127.0.0.1'
  port: '6379'
  options:
    profile: '3.2'

memcached_parameters:
  -
    host: 'localhost'
    port: '11211'
```

## Built With

* [PRedis](https://github.com/nrk/predis) - Flexible and feature-complete Redis client for PHP and HHVM
* [ramsey/uuid](https://github.com/ramsey/uuid) - A PHP 5.4+ library for generating RFC 4122 version 1, 3, 4, and 5 universally unique identifiers (UUID).
* [Symfony Console](https://github.com/symfony/console) - Symfony Console Component

## Authors

* **Mauro Cassani** - [github](https://github.com/mauretto78)

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details