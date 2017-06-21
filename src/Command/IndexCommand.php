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

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexCommand extends BaseCommand
{
    /**
     * IndexCommand constructor.
     *
     * @param null  $driver
     * @param array $parameters
     */
    public function __construct($driver = null, array $parameters = [])
    {
        parent::__construct(
            'iml_cache_index',
            $driver,
            $parameters
        );
    }

    protected function configure()
    {
        $this
            ->setName('iml:cache:index')
            ->setDescription('Get all data stored in cache.')
            ->setHelp('This command displays in a table all data stored in cache.')
            ->addArgument('from', InputArgument::OPTIONAL, 'Type date from you wish to display data. Eg: 20-06-2017')
            ->addArgument('to', InputArgument::OPTIONAL, 'Type date to you wish to display data. Eg: now')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cache = $this->createClient($this->driver, $this->parameters);

        $from = $input->getArgument('from') ? new \DateTime($input->getArgument('from')) : null;
        $to = $input->getArgument('to') ? new \DateTime($input->getArgument('to')) : null;
        $index = $cache->getIndexInRangeDate($from, $to);

        if ($index && count($index)) {
            ksort($index);
            $table = new Table($output);
            $table->setHeaders(['#', 'List', 'Created on', 'Chunks', 'Chunk size', 'Headers', 'Ttl', 'Items']);

            $counter = 0;
            foreach ($index as $item) {
                $item = unserialize($item);
                $listUuid = $item['uuid'];

                $headers = (is_array($item['headers']) and count($item['headers'])) ? $this->implodeArrayToAString($item['headers']) : 'empty';

                /** @var \DateTimeImmutable $created_on */
                $created_on = $item['created_on'];
                $table->setRow(
                    $counter,
                    [
                        $counter + 1,
                        '<fg=yellow>'.$listUuid.'</>',
                        $created_on->format('Y-m-d H:i:s'),
                        $cache->getNumberOfChunks($listUuid),
                        $cache->getChunkSize($listUuid),
                        $headers,
                        $cache->getTtl($listUuid),
                        $item['size'],
                    ]
                );
                ++$counter;
            }

            $table->render();
        } else {
            $output->writeln('<fg=red>['.$this->driver.'] Empty Index.</>');
        }
    }

    /**
     * @param array $input
     *
     * @return string
     */
    protected function implodeArrayToAString(array $input)
    {
        $output = implode(', ', array_map(
            function ($v, $k) {
                if (is_array($v)) {
                    return $k.'[]='.implode('&'.$k.'[]=', $v);
                } else {
                    return $k.'='.$v;
                }
            },
            $input,
            array_keys($input)
        ));

        return $output;
    }
}
