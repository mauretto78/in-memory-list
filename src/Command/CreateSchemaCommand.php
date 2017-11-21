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

class CreateSchemaCommand extends BaseCommand
{
    /**
     * CreateSchemaCommand constructor.
     *
     * @param array $parameters
     */
    public function __construct(array $parameters)
    {
        parent::__construct(
            'iml_cache_create_schema',
            'pdo',
            $parameters
        );
    }

    protected function configure()
    {
        $this
            ->setName('iml:cache:schema:create')
            ->setDescription('Create database schema.')
            ->setHelp('This command creates the database schema (only with PDO driver).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if($this->driver !== 'pdo'){
            throw new \Exception('This command is avaliable only with PDO driver');
        }

        $cache = $this->createClient($this->driver, $this->parameters);

        try {
            $cache->getRepository()->createSchema();

            $output->writeln('<fg=red>['.$this->driver.'] Schema was successful created.</>');
        } catch (\Exception $e) {
            $output->writeln('<fg=red>['.$this->driver.'] Error in schema creation: '.$e->getMessage().' .</>');
        }
    }
}
