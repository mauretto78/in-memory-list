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
                'parameters',
                InputArgument::IS_ARRAY,
                'Insert here connection parameters [eg. host:localhost port:11211]'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $driver = $input->getArgument('driver') ?: 'redis';
        $parameters = $input->getArgument('parameters') ?: [];

        $cache = $this->createClient($driver, $parameters);
        $statistics = $cache->getStatistics();

        $table = new Table($output);
        $table->setHeaders(['Key', 'Value']);

        $counter = 0;
        foreach ($statistics as $infoKey => $infoData) {
            $dataString = '';

            if (is_array($infoData)) {
                foreach ($infoData as $key => $value) {
                    $valueToDisplay = (is_array($value)) ? implode(',', $value) : $value;
                    $dataString .= '['.$key.']->' . $valueToDisplay . "\xA";
                }
            } else {
                $dataString .= $infoData;
            }

            $table->setRow(
                $counter,
                [
                    $infoKey,
                    $dataString
                ]
            );
            ++$counter;
        }

        $table->render();
    }
}
