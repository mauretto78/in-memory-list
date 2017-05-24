<?php

use InMemoryList\Application\Client;
use InMemoryList\Command\IndexCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;

class IndexCommandTest extends TestCase
{
    /**
     * @var Application
     */
    private $app;

    private $array;

    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->app = new Application();
        $this->app->add(new IndexCommand());

        $this->array = json_encode([
            [
                'userId' => 1,
                'id' => 1,
                'title' => 'sunt aut facere repellat provident occaecati excepturi optio reprehenderit',
                'body' => "quia et suscipit\nsuscipit recusandae consequuntur expedita et cum\nreprehenderit molestiae ut ut quas totam\nnostrum rerum est autem sunt rem eveniet architecto",
            ],
            [
                'userId' => 1,
                'id' => 2,
                'title' => 'qui est esse',
                'body' => "est rerum tempore vitae\nsequi sint nihil reprehenderit dolor beatae ea dolores neque\nfugiat blanditiis voluptate porro vel nihil molestiae ut reiciendis\nqui aperiam non debitis possimus qui neque nisi nulla",
            ],
        ]);
    }

    /**
     * @test
     */
    public function it_displays_correctly_empty_apcu_cache()
    {
        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'driver' => 'apcu',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('[apcu] Empty Index.', $output);
    }

    /**
     * @test
     */
    public function it_displays_correctly_index_apcu_cache()
    {
        $client = new Client('apcu');
        $client->create(json_decode($this->array), [
            'uuid' => 'simple-list',
            'element-uuid' => 'id',
            'index' => true
        ]);

        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'driver' => 'apcu',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('1', $output);
        $this->assertContains('2', $output);

        $client->flush();
    }

    /**
     * @test
     */
    public function it_displays_correctly_empty_memcached_cache()
    {
        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'driver' => 'memcached',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('[memcached] Empty Index.', $output);
    }

    /**
     * @test
     */
    public function it_displays_correctly_index_memcached_cache()
    {
        $client = new Client('memcached');
        $client->create(json_decode($this->array), [
            'uuid' => 'simple-list',
            'element-uuid' => 'id',
            'index' => true
        ]);

        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'driver' => 'memcached',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('1', $output);
        $this->assertContains('2', $output);

        $client->flush();
    }

    /**
     * @test
     */
    public function it_displays_correctly_empty_redis_cache()
    {
        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'driver' => 'redis',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('[redis] Empty Index.', $output);
    }

    /**
     * @test
     */
    public function it_displays_correctly_index_redis_cache()
    {
        $client = new Client('redis');
        $client->create(json_decode($this->array), [
            'uuid' => 'simple-list',
            'element-uuid' => 'id',
            'index' => true
        ]);

        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'driver' => 'redis',
            'parameters' => [
                'host=127.0.0.1,port=6379'
            ]
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('1', $output);
        $this->assertContains('2', $output);

        $client->flush();
    }
}
