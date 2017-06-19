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
     * @param array $parameters
     */
    public function __construct($driver = null, array $parameters = [])
    {
        parent::__construct(
            'iml_cache_flush',
            $driver,
            $parameters
        );
    }

    protected function configure()
    {
        $this
            ->setName('iml:cache:flush')
            ->setDescription('Flush all data stored in cache.')
            ->setHelp('This command flushes all data stored in cache.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cache = $this->createClient($this->driver, $this->parameters);
        $cache->flush();

        $output->writeln('<fg=red>['.$this->driver.'] Cache was successful flushed.</>');
    }
}
