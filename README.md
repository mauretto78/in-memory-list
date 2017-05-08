# In-memory List

**In-memory List** allows you to create and save your lists.

Grab your lists from your API or your database and store them in memory: then, you can quickly retrieve your data, sorting and performing queries on it.

This package requires:
 
* [Redis](https://redis.io/)
* [Memcached](http://php.net/manual/en/book.memcache.php)

## Basic Usage

To create and store in memory you list do the following:

```
use InMemoryList\Application\Client;

$array = [
    ...
]

$client = new Client();
$collection = $client->create($array, 'simple-array');

foreach ($collection as $element){
    $item = $client->item($element);
    // ...
}

```

## Drivers

Yuo can use `Redis` or `Memcached`. Please note that `Redis` is the default driver.
 
```
// Redis
$redis_params = [
    'params' => [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
    ],
    'options' => [
        'profile' => '3.0',
    ],
];

$client = new Client('redis', $redis_params);
// ..
```

```
// Memcached
$memcached_params = [
    ['localhost', 11211]
];

$client = new Client('memcached', $memcached_params);
// ..
```

Please refer to [official page or PRedis](https://github.com/nrk/predis) to get Redis connection details.

## Time to live (TTL)

You can specify a ttl (in seconds) for your lists:

```
// ..

$client = new Client();
$collection = $client->create($array, 'your-list-name', 'id', 3600);
// ..
```

## Assign unique IDs to your list elements

You can assign an unique ID to list elemens (instead, a [uuid](https://github.com/ramsey/uuid) will be generated). Consider this array:

```
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

```
use InMemoryList\Application\Client;

$client = new Client();
$collection = $client->create($simpleArray, 'simple-array', 'id');
```

And now to retrieve a single element, you can simply do:

```
$item1 = $client->item($collection['1']);
```

Please note that `id` must be a string. 

## Sorting and Quering

You can perform queries on your list. You can concatenate criteria:

```
use InMemoryList\Application\Client;
use InMemoryList\Application\QueryBuilder;

$array = [
    ...
]

$client = new Client();
$collection = $client->create($array, 'simple-array');
$qb = new QueryBuilder($collection);
$qb
    ->addCriteria('title', '...', 'IN')
    ->addCriteria('rate', '3', '>')
    ->orderBy('title');
    
foreach ($qb->getResults() as $element){
    $item = $client->item($element);
    // ...
}

```
You can use the following operators to perform your queries:

* '='
* '>'
* '<'
* '<='
* '>='
* '!='
* 'IN'

## Built With

* [PRedis](https://github.com/nrk/predis) - Flexible and feature-complete Redis client for PHP and HHVM

## Authors

* **Mauro Cassani** - [github](https://github.com/mauretto78)

See also the list of [contributors](https://github.com/mauretto78/in-memory-list/contributors.md) who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
