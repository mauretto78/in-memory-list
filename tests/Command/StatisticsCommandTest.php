<?php

use InMemoryList\Command\StatisticsCommand;
use InMemoryList\Tests\BaseTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class StatisticsCommandTest extends BaseTestCase
{
    /**
     * @var Application
     */
    private $app;

    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->app = new Application();
        $this->app->add(new StatisticsCommand());
    }

    /**
     * @test
     */
    public function it_displays_correctly_apcu_statistics()
    {
        $this->app->add(new StatisticsCommand('apcu'));
        $command = $this->app->find('iml:cache:statistics');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('num_slots', $output);
        $this->assertContains('ttl', $output);
        $this->assertContains('num_hits', $output);
        $this->assertContains('num_misses', $output);
        $this->assertContains('num_inserts', $output);
        $this->assertContains('num_entries', $output);
        $this->assertContains('expunges', $output);
        $this->assertContains('start_time', $output);
        $this->assertContains('start_time', $output);
        $this->assertContains('mem_size', $output);
        $this->assertContains('memory_type', $output);
        $this->assertContains('deleted_list', $output);
        $this->assertContains('slot_distribution', $output);
    }

    /**
     * @test
     */
    public function it_displays_correctly_memcached_statistics()
    {
        $this->app->add(new StatisticsCommand('memcached', $this->memcached_parameters));
        $command = $this->app->find('iml:cache:statistics');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();

        // if $this->memcached_parameters is a monodimensional array convert to multidimensional
        if(!isset($this->memcached_parameters[0])){
            $this->memcached_parameters = [$this->memcached_parameters];
        }

        $this->assertContains($this->memcached_parameters[0]['host'], $output);
        $this->assertContains($this->memcached_parameters[0]['port'], $output);
    }

    /**
     * @test
     */
    public function it_displays_correctly_redis_statistics()
    {
        $this->app->add(new StatisticsCommand('redis', $this->redis_parameters));
        $command = $this->app->find('iml:cache:statistics');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('Clients', $output);
        $this->assertContains('CPU', $output);
        $this->assertContains('Memory', $output);
        $this->assertContains('Persistence', $output);
        $this->assertContains('Replication', $output);
        $this->assertContains('Server', $output);
        $this->assertContains('Stats', $output);
    }
}
