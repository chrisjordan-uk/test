<?php
declare(strict_types=1);

test('original files are never modified', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/sample.zip';
        make_sample_zip($zipPath);
        $dest = $tmp . '/extracted';
        jps_safe_extract_zip($zipPath, $dest);
        [$products,] = jps_detect_product_folders($dest);

        $hashesBefore = [];
        foreach ($products as $p) {
            foreach ($p['images'] as $img) {
                $hashesBefore[$img['path']] = hash_file('sha256', $img['path']);
            }
        }

        $settings = default_test_settings();
        jps_process_batch($products, $settings, $tmp . '/enhanced', $tmp . '/cache');

        foreach ($hashesBefore as $path => $digest) {
            assert_true(is_file($path), "original disappeared: $path");
            assert_equals($digest, hash_file('sha256', $path), "original was modified: $path");
        }
    } finally {
        rrmdir($tmp);
    }
});

test('process_batch produces enhanced output for every image', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/sample.zip';
        make_sample_zip($zipPath);
        $dest = $tmp . '/extracted';
        jps_safe_extract_zip($zipPath, $dest);
        [$products,] = jps_detect_product_folders($dest);

        $settings = default_test_settings();
        $enhancedRoot = $tmp . '/enhanced';
        $batchReport = jps_process_batch($products, $settings, $enhancedRoot, $tmp . '/cache');

        $totalImages = array_sum(array_map(fn($p) => count($p['images']), $products));
        assert_equals($totalImages, $batchReport['total_images']);
        assert_equals($totalImages, jps_batch_total_success($batchReport));
        assert_equals(0, jps_batch_total_failure($batchReport));

        foreach ($products as $product) {
            $enhancedDir = $enhancedRoot . '/' . $product['name'] . '/ENHANCED_PHOTOS';
            $produced = glob($enhancedDir . '/*_enhanced.jpg');
            assert_equals(count($product['images']), count($produced));
        }
    } finally {
        rrmdir($tmp);
    }
});

test('error recovery continues after corrupt image', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/batch.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('ITEM_01/good1.jpg', make_test_image_bytes());
        $zip->addFromString('ITEM_01/corrupt.jpg', 'this is not actually a jpeg');
        $zip->addFromString('ITEM_01/good2.jpg', make_test_image_bytes());
        $zip->close();

        $dest = $tmp . '/extracted';
        jps_safe_extract_zip($zipPath, $dest);
        [$products,] = jps_detect_product_folders($dest);

        $settings = default_test_settings();
        $batchReport = jps_process_batch($products, $settings, $tmp . '/enhanced', $tmp . '/cache');

        assert_equals(3, $batchReport['total_images']);
        assert_equals(1, jps_batch_total_failure($batchReport));
        assert_equals(2, jps_batch_total_success($batchReport));

        $report = $batchReport['product_reports'][0];
        $corruptResult = null;
        foreach ($report['results'] as $r) {
            if ($r['filename'] === 'corrupt.jpg') {
                $corruptResult = $r;
            }
        }
        assert_true($corruptResult !== null);
        assert_false($corruptResult['success']);
        assert_true($corruptResult['error'] !== null);
    } finally {
        rrmdir($tmp);
    }
});

test('selected_folders filters processing', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/sample.zip';
        make_sample_zip($zipPath);
        $dest = $tmp . '/extracted';
        jps_safe_extract_zip($zipPath, $dest);
        [$products,] = jps_detect_product_folders($dest);

        $settings = default_test_settings(['selected_folders' => ['ITEM_01']]);
        $batchReport = jps_process_batch($products, $settings, $tmp . '/enhanced', $tmp . '/cache');

        assert_equals(1, count($batchReport['product_reports']));
        assert_equals('ITEM_01', $batchReport['product_reports'][0]['folder_name']);
    } finally {
        rrmdir($tmp);
    }
});
