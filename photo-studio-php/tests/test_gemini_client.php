<?php
declare(strict_types=1);

test('not configured without api key returns unsafe error result', function () {
    // This dev/test environment has no GEMINI_API_KEY in .env by default.
    if (jps_gemini_configured()) {
        return; // A real key is configured in this environment; skip.
    }
    $img = imagecreatetruecolor(100, 100);
    $result = jps_gemini_edit_image($img, 'clean_product_photo', 'neutral_studio', sys_get_temp_dir());
    assert_true($result['image'] === null);
    assert_false($result['is_safe']);
    assert_true($result['error'] !== null);
});

test('build_instruction preserves defect language', function () {
    $instruction = jps_build_gemini_instruction('clean_product_photo', 'white');
    $lower = strtolower($instruction);
    assert_true(str_contains($lower, 'stains'));
    assert_true(str_contains($lower, 'holes'));
    assert_true(str_contains($lower, 'preserve'));
    assert_true(str_contains($lower, 'logos'));
});

test('build_instruction respects preserve_original background', function () {
    $instruction = jps_build_gemini_instruction('clean_product_photo', 'preserve_original');
    assert_true(str_contains(strtolower($instruction), 'keep the original background'));
});

test('similarity of identical images is near one', function () {
    $img = imagecreatetruecolor(200, 200);
    $color = imagecolorallocate($img, 120, 80, 40);
    imagefilledrectangle($img, 0, 0, 200, 200, $color);
    $score = jps_image_similarity($img, $img);
    assert_true($score > 0.98, "expected near-1.0 similarity, got $score");
});

test('similarity of very different images is low', function () {
    $a = imagecreatetruecolor(200, 200);
    $white = imagecolorallocate($a, 255, 255, 255);
    imagefilledrectangle($a, 0, 0, 200, 200, $white);

    $b = imagecreatetruecolor(200, 200);
    $black = imagecolorallocate($b, 0, 0, 0);
    imagefilledrectangle($b, 0, 0, 200, 200, $black);

    $score = jps_image_similarity($a, $b);
    assert_true($score < 0.3, "expected low similarity, got $score");
});
