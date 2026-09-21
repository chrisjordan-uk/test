<?php
declare(strict_types=1);

function sample_product_report(): array
{
    return [
        'folder_name' => 'ITEM_01',
        'results' => [
            [
                'filename' => 'photo1.jpg',
                'success' => true,
                'used_gemini' => false,
                'used_local_fallback' => true,
                'marked_unsafe' => false,
                'operations' => ['Resized to a maximum dimension of 1600px.'],
                'warnings' => [],
                'error' => null,
                'output_filename' => 'photo1_enhanced.jpg',
                'similarity' => null,
            ],
            [
                'filename' => 'photo2.jpg',
                'success' => false,
                'used_gemini' => false,
                'used_local_fallback' => false,
                'marked_unsafe' => true,
                'operations' => [],
                'warnings' => ['Gemini edit failed the product-preservation safety check.'],
                'error' => null,
                'output_filename' => 'photo2_enhanced.jpg',
                'similarity' => 0.2,
            ],
        ],
    ];
}

test('product report contains key fields', function () {
    $settings = default_test_settings(['mode' => 'clean_product_photo']);
    $text = jps_build_product_report(sample_product_report(), $settings);
    assert_true(str_contains($text, 'ITEM_01'));
    assert_true(str_contains($text, 'photo1.jpg'));
    assert_true(str_contains($text, 'photo2.jpg'));
    assert_true(str_contains($text, 'UNSAFE / ORIGINAL KEPT'));
    assert_true(str_contains(strtolower($text), 'similarity'));
});

test('batch report contains counts and privacy notice', function () {
    $settings = default_test_settings(['use_gemini' => true]);
    $batch = ['product_reports' => [sample_product_report()], 'total_images' => 2, 'gemini_calls_made' => 1];
    $text = jps_build_batch_report($batch, $settings, ['Skipped foo.txt']);

    assert_true(str_contains($text, 'BATCH PROCESSING REPORT'));
    assert_true(str_contains($text, 'Total images: 2'));
    assert_true(str_contains($text, 'Gemini'));
    assert_true(str_contains($text, 'Privacy notice'));
    assert_true(str_contains($text, 'Skipped foo.txt'));
});

test('batch report without gemini states local only', function () {
    $settings = default_test_settings(['use_gemini' => false]);
    $batch = ['product_reports' => [sample_product_report()], 'total_images' => 2, 'gemini_calls_made' => 0];
    $text = jps_build_batch_report($batch, $settings, []);
    assert_true(str_contains($text, 'Gemini was disabled'));
});
