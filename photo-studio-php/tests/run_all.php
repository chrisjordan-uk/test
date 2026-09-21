<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$testFiles = glob(__DIR__ . '/test_*.php');
sort($testFiles);
foreach ($testFiles as $file) {
    require $file;
}

$pass = 0;
$fail = 0;
foreach ($GLOBALS['jps_tests'] as [$name, $fn]) {
    try {
        $fn();
        echo "PASS  $name\n";
        $pass++;
    } catch (\Throwable $e) {
        echo "FAIL  $name\n      " . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
