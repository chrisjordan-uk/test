<?php
declare(strict_types=1);

test('safe_extract extracts only valid images', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/sample.zip';
        make_sample_zip($zipPath);
        $dest = $tmp . '/extracted';
        $report = jps_safe_extract_zip($zipPath, $dest);

        assert_equals(7, $report['extracted'], 'expected 7 valid images extracted');
        assert_true(is_file($dest . '/ITEM_01/photo1.jpg'));
        assert_true(is_file($dest . '/ITEM_02/label.png'));
        assert_true(is_file($dest . '/ITEM_04/photo.webp'));
    } finally {
        rrmdir($tmp);
    }
});

test('path traversal is neutralised', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/sample.zip';
        make_sample_zip($zipPath);
        $dest = $tmp . '/extracted';
        $report = jps_safe_extract_zip($zipPath, $dest);

        assert_false(is_file($tmp . '/evil.jpg'), 'evil.jpg must not escape the extraction dir');
        assert_true(count(array_filter($report['warnings'], fn($w) => str_contains($w, 'evil.jpg'))) > 0);

        $destReal = realpath($dest);
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            if ($file->isFile()) {
                assert_true(str_starts_with(realpath($file->getPathname()), $destReal), 'file escaped dest dir: ' . $file->getPathname());
            }
        }
    } finally {
        rrmdir($tmp);
    }
});

test('ignores __MACOSX, .DS_Store and non-images', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/sample.zip';
        make_sample_zip($zipPath);
        $dest = $tmp . '/extracted';
        jps_safe_extract_zip($zipPath, $dest);

        assert_false(is_dir($dest . '/__MACOSX'));
        assert_false(is_file($dest . '/.DS_Store'));
        assert_false(is_file($dest . '/ITEM_01/notes.txt'));
    } finally {
        rrmdir($tmp);
    }
});

test('detect_product_folders groups correctly', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/sample.zip';
        make_sample_zip($zipPath);
        $dest = $tmp . '/extracted';
        jps_safe_extract_zip($zipPath, $dest);
        [$products, $warnings] = jps_detect_product_folders($dest);

        $names = array_map(fn($p) => $p['name'], $products);
        sort($names);
        assert_equals(['ITEM_01', 'ITEM_02', 'ITEM_03', 'ITEM_04'], $names);

        $byName = [];
        foreach ($products as $p) {
            $byName[$p['name']] = $p;
        }
        assert_equals(2, count($byName['ITEM_01']['images']));
        assert_equals(3, count($byName['ITEM_02']['images']));
        assert_equals(1, count($byName['ITEM_03']['images']));
        assert_equals(1, count($byName['ITEM_04']['images']));
    } finally {
        rrmdir($tmp);
    }
});

test('validate_zip_size rejects oversized and empty', function () {
    assert_throws(fn() => jps_validate_zip_size((MAX_ZIP_SIZE_MB + 1) * 1024 * 1024), JpsZipException::class, 'oversized zip must throw');
    assert_throws(fn() => jps_validate_zip_size(0), JpsZipException::class, 'empty zip must throw');
});

test('corrupt zip raises', function () {
    $tmp = make_temp_dir();
    try {
        $badZip = $tmp . '/bad.zip';
        file_put_contents($badZip, 'this is not a zip file at all');
        assert_throws(fn() => jps_safe_extract_zip($badZip, $tmp . '/out'), JpsZipException::class);
    } finally {
        rrmdir($tmp);
    }
});

test('no images in zip raises', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/empty.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('ITEM_01/notes.txt', 'no images here');
        $zip->close();
        assert_throws(fn() => jps_safe_extract_zip($zipPath, $tmp . '/out'), JpsZipException::class);
    } finally {
        rrmdir($tmp);
    }
});
