<?php
declare(strict_types=1);

test('heic signature detection recognises a real ftyp/heic header', function () {
    $tmp = make_temp_dir();
    try {
        $path = $tmp . '/IMG_0001.HEIC';
        file_put_contents($path, make_fake_heic_bytes());
        assert_true(jps_is_heic_signature($path), 'expected HEIC ftyp header to be recognised');
    } finally {
        rrmdir($tmp);
    }
});

test('heic signature detection rejects a plain jpeg', function () {
    $tmp = make_temp_dir();
    try {
        $path = $tmp . '/photo.jpg';
        file_put_contents($path, make_test_image_bytes('jpeg'));
        assert_false(jps_is_heic_signature($path), 'a JPEG must not be mistaken for HEIC');
    } finally {
        rrmdir($tmp);
    }
});

test('heic and heif extensions are accepted by the zip handler', function () {
    $tmp = make_temp_dir();
    try {
        $zipPath = $tmp . '/heic.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('ITEM_01/IMG_0001.HEIC', make_fake_heic_bytes());
        $zip->addFromString('ITEM_01/IMG_0002.heif', make_fake_heic_bytes());
        $zip->close();

        $dest = $tmp . '/extracted';
        $report = jps_safe_extract_zip($zipPath, $dest);
        assert_equals(2, $report['extracted']);

        [$products,] = jps_detect_product_folders($dest);
        assert_equals(1, count($products));
        assert_equals(2, count($products[0]['images']));
    } finally {
        rrmdir($tmp);
    }
});

test('undecodable heic falls back to a raw copy with a clear, actionable error', function () {
    // This dev/CI environment has no Imagick (typical of many shared hosts
    // too), so a HEIC photo can't actually be decoded here — the important
    // thing is that the pipeline degrades gracefully instead of crashing,
    // keeps the original safe, and explains exactly why in the report.
    if (class_exists('Imagick')) {
        return; // A real HEIF-capable Imagick is present; decode may succeed instead.
    }

    $tmp = make_temp_dir();
    try {
        $imagePath = $tmp . '/IMG_0001.HEIC';
        file_put_contents($imagePath, make_fake_heic_bytes());

        $imageRecord = ['filename' => 'IMG_0001.HEIC', 'path' => $imagePath, 'size' => filesize($imagePath)];
        $settings = default_test_settings();

        $result = jps_process_single_image($imageRecord, $settings, $tmp . '/enhanced', $tmp . '/cache');

        assert_false($result['success']);
        assert_true($result['error'] !== null);
        assert_true(str_contains($result['error'], 'HEIC'), 'error should mention HEIC');
        assert_equals('IMG_0001.HEIC', $result['output_filename'], 'original bytes should be copied through unchanged');
        assert_true(is_file($tmp . '/enhanced/IMG_0001.HEIC'), 'the raw HEIC should still land in ENHANCED_PHOTOS as a fallback');
    } finally {
        rrmdir($tmp);
    }
});
