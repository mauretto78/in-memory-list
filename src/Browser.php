<?php

namespace RedisBrowser;

use Predis\Client;

class Browser
{
    /**
     * @var Client
     */
    private $client;

    /**
     * Browser constructor.
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }
}
