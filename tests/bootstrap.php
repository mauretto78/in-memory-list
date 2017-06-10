<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

try {
    $config = Yaml::parse(file_get_contents(__DIR__.'/../config/parameters.yml'));

    return $config;
} catch (ParseException $e) {
    printf('Unable to parse the YAML string: %s', $e->getMessage());
    die();
}
