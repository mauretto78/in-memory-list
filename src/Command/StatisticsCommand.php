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

class StatisticsCommand extends BaseCommand
{
    /**
     * StatisticsCommand constructor.
     */
    public function __construct()
    {
        parent::__construct('iml_cache_statistics');
    }

    protected function configure()
    {
        $this
            ->setName('iml:cache:statistics')
            ->setDescription('Get all data stored in cache.')
            ->setHelp('This command displays in a table all data stored in cache.')
            ->addArgument('driver', InputArgument::OPTIONAL, 'driver [apcu, memcached, redis]')
            ->addArgument(
                'params',
                InputArgument::IS_ARRAY,
                'Insert here connection params [eg. host:localhost port:11211]'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $driver = $input->getArgument('driver') ?: 'redis';
        $params = $input->getArgument('params') ?: [];

        $cache = $this->createClient($driver, $params);
        $statistics = $cache->getStatistics();

        if($statistics and count($statistics)){
            $table = new Table($output);
            $table->setHeaders(['#', 'Key', 'Created on', 'Expire', 'Ttl', 'Size']);

            $counter = 0;
            foreach ($statistics as $key => $item){
                $item = unserialize($item);

                /** @var \DateTimeImmutable $created_on */
                $created_on = $item['created_on'];

                if($item['ttl'] and $item['ttl'] > 0){
                    $expire_date = $created_on->add(new \DateInterval('PT'.$item['ttl'].'S'))->format('Y-m-d H:i:s');
                } else {
                    $expire_date = '--';
                }

                $table->setRow(
                    $counter,
                    [
                        $counter+1,
                        '<fg=yellow>'.$key.'</>',
                        $created_on->format('Y-m-d H:i:s'),
                        $expire_date,
                        $item['ttl'],
                        $item['size'],
                    ]
                );
                ++$counter;
            }

            $table->render();
        } else {
            $output->writeln('<fg=red>['.$driver.'] Empty Cache.</>');
        }
    }
}