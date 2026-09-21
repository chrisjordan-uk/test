<?php
declare(strict_types=1);

test('output zip has expected structure', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/sample.zip';
        make_sample_zip($zipPath);
        $dest = $tmp . '/extracted';
        jps_safe_extract_zip($zipPath, $dest);
        [$products, $warnings] = jps_detect_product_folders($dest);

        $settings = default_test_settings();
        $enhancedRoot = $tmp . '/enhanced';
        $batchReport = jps_process_batch($products, $settings, $enhancedRoot, $tmp . '/cache');

        $outputZipPath = $tmp . '/OUTPUT.zip';
        jps_build_output_zip($products, $batchReport, $settings, $enhancedRoot, $outputZipPath, $warnings);

        assert_true(is_file($outputZipPath));

        $zip = new ZipArchive();
        $zip->open($outputZipPath);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        assert_true(in_array(JPS_OUTPUT_ROOT_NAME . '/BATCH_PROCESSING_REPORT.txt', $names, true));

        foreach ($products as $product) {
            $base = JPS_OUTPUT_ROOT_NAME . '/' . $product['name'];
            assert_true(in_array("$base/PROCESSING_REPORT.txt", $names, true));
            foreach ($product['images'] as $image) {
                assert_true(in_array("$base/ORIGINAL_PHOTOS/{$image['filename']}", $names, true));
            }
            $enhancedNames = array_filter($names, fn($n) => str_starts_with($n, "$base/ENHANCED_PHOTOS/"));
            assert_equals(count($product['images']), count($enhancedNames));
        }

        $batchText = $zip->getFromName(JPS_OUTPUT_ROOT_NAME . '/BATCH_PROCESSING_REPORT.txt');
        assert_true(str_contains($batchText, 'BATCH PROCESSING REPORT'));
        $zip->close();
    } finally {
        rrmdir($tmp);
    }
});
