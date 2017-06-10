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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FlushCommand extends BaseCommand
{
    /**
     * FlushCommand constructor.
     *
     * @param null  $driver
     * @param array $defaultParameters
     */
    public function __construct($driver = null, array $defaultParameters = [])
    {
        parent::__construct(
            'iml_cache_flush',
            $driver,
            $defaultParameters
        );
    }

    protected function configure()
    {
        $this
            ->setName('iml:cache:flush')
            ->setDescription('Flush all data stored in cache.')
            ->setHelp('This command flushes all data stored in cache.')
            ->addArgument('driver', InputArgument::OPTIONAL, 'driver [apcu, memcached, redis]')
            ->addArgument(
                'parameters',
                InputArgument::IS_ARRAY,
                'Insert here connection parameters'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $driver = $input->getArgument('driver') ?: $this->driver;
        $parameters = $this->convertParametersArray($input->getArgument('parameters')) ?: $this->defaultParameters;

        $cache = $this->createClient($driver, $parameters);
        $cache->flush();

        $output->writeln('<fg=red>['.$driver.'] Cache was successful flushed.</>');
    }
}
