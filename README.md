# In-memory List

**In-memory List** easily allows you to create and save your lists in memory.

If you are looking for a caching system for your lists this library is suitable for you.

Grab your lists from your API, your database or whatever you want and store them in memory: then, you can quickly retrieve your lists from cache, sorting and performing queries on them.

This package uses:
 
* [Redis](https://redis.io/)
* [Memcached](http://php.net/manual/en/book.memcache.php)

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

You can use `Redis` or `Memcached`. Please note that `Redis` is the default driver.
 
```php
use InMemoryList\Application\Client;

// Redis
$redis_params = [
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
    'options' => [
        'profile' => '3.0',
    ],
];

$client = new Client('redis', $redis_params);
// ..
```

```php
use InMemoryList\Application\Client;

// Memcached
$memcached_params = [
    ['localhost', 11211]
];

$client = new Client('memcached', $memcached_params);
// ..
```

Please refer to [official page](https://github.com/nrk/predis) for more details on PRedis connection.

## Headers

You can set a `headers` array to you list.

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
$collection = $client->create($array, $headers, 'simple-array');
$headers = $client->getHeaders('simple-array');

// ...
```

## Assign an unique ID to your list

Please note that you can set an unique ID for your list. If the ID is already taken, an Exception will be thrown.

```php
use InMemoryList\Application\Client;

$array = [
    ...
]

$client = new Client();
$collection = $client->create($array, [], 'simple-array');

// ..
```

And now you can retrive the list:

```php
//..
$simpleArray = $client->findByUuid('simple-array');

//..

```

Please note that the unique ID **must be a string**. 

## Assign unique IDs to your list elements

You can assign an unique ID to list elemens (instead, a [uuid](https://github.com/ramsey/uuid) will be generated). Consider this array:

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

Maybe you would use `id` key as unique ID in your list:

```php
use InMemoryList\Application\Client;

$client = new Client();
$collection = $client->create($simpleArray, [], 'simple-array', 'id');
```

And now to retrieve a single element, you can simply do:

```php
$item1 = $client->item($collection['1']);
```

Please note that the unique ID **must be a string**. 

## Time to live (TTL)

You can specify a ttl (in seconds) for your lists:

```php
use InMemoryList\Application\Client;

$client = new Client();
$collection = $client->create($array, [], 'your-list-name', 'id', 3600);
// ..
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
$collection = $client->create($array, 'simple-array');
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

* '=' (default operator)
* '>'
* '<'
* '<='
* '>='
* '!='
* 'ARRAY'
* 'CONTAINS' (case insensitive)

## Limit and Offset

You can specify limit/offset on your query results:

```php
use InMemoryList\Application\Client;
use InMemoryList\Application\QueryBuilder;

$array = [
    ...
]

$client = new Client();
$collection = $client->create($array, 'simple-array');
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

## Built With

* [PRedis](https://github.com/nrk/predis) - Flexible and feature-complete Redis client for PHP and HHVM

## Authors

* **Mauro Cassani** - [github](https://github.com/mauretto78)

See also the list of [contributors](https://github.com/mauretto78/in-memory-list/contributors.md) who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
