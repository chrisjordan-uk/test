<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config.php';
require __DIR__ . '/helpers.php';

$GLOBALS['jps_tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['jps_tests'][] = [$name, $fn];
}
