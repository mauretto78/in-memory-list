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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DestroySchemaCommand extends BaseCommand
{
    /**
     * CreateSchemaCommand constructor.
     *
     * @param array $parameters
     */
    public function __construct(array $parameters)
    {
        parent::__construct(
            'iml_cache_destroy_schema',
            'pdo',
            $parameters
        );
    }

    protected function configure()
    {
        $this
            ->setName('iml:cache:schema:destroy')
            ->setDescription('Destroys database schema.')
            ->setHelp('This command destroys the database schema (only with PDO driver).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cache = $this->createClient($this->driver, $this->parameters);

        try {
            $cache->getRepository()->destroySchema();

            $output->writeln('<fg=red>['.$this->driver.'] Schema was successful destroyed.</>');
        } catch (\Exception $e) {
            $output->writeln('<fg=red>['.$this->driver.'] Error in schema destruction: '.$e->getMessage().' .</>');
        }
    }
}
