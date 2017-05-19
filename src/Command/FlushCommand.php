<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Command;

use InMemoryList\Application\Client;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FlushCommand extends BaseCommand
{
    /**
     * StatisticsCommand constructor.
     */
    public function __construct()
    {
        parent::__construct('iml_cache_flush');
    }

    protected function configure()
    {
        $this
            ->setName('iml:cache:flush')
            ->setDescription('Flush all data stored in cache.')
            ->setHelp('This command flushes all data stored in cache.')
            ->addArgument('driver', InputArgument::OPTIONAL, 'driver [apcu, memcached, redis]')
            ->addArgument(
                'params',
                InputArgument::IS_ARRAY,
                'Insert here connection params'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $driver = $input->getArgument('driver') ?: 'redis';
        $params = $input->getArgument('params') ?: [];

        $cache = $this->createClient($driver, $params);
        $cache->flush();

        $output->writeln('<fg=red>['.$driver.'] Cache was successful flushed.</>');
    }
}