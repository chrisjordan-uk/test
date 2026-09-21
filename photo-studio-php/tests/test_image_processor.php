<?php
declare(strict_types=1);

test('resize_max_dimension shrinks large image', function () {
    $img = imagecreatetruecolor(4000, 2000);
    $resized = jps_resize_max_dimension($img, 1000);
    [$w, $h] = jps_image_size($resized);
    assert_equals(1000, max($w, $h));
    assert_true(abs($w / $h - 4000 / 2000) < 0.01);
});

test('resize_max_dimension leaves small image untouched', function () {
    $img = imagecreatetruecolor(300, 200);
    $resized = jps_resize_max_dimension($img, 1000);
    [$w, $h] = jps_image_size($resized);
    assert_equals(300, $w);
    assert_equals(200, $h);
});

test('convert_aspect_ratio pads without cropping', function () {
    $img = imagecreatetruecolor(400, 600);
    $result = jps_convert_aspect_ratio($img, '1:1', [255, 255, 255]);
    [$w, $h] = jps_image_size($result);
    assert_equals($w, $h, 'must be square');
    assert_true($w >= 400 && $h >= 600, 'padding must not shrink below original');
});

test('convert_aspect_ratio original is a no-op', function () {
    $img = imagecreatetruecolor(400, 600);
    $result = jps_convert_aspect_ratio($img, 'original', [255, 255, 255]);
    [$w, $h] = jps_image_size($result);
    assert_equals(400, $w);
    assert_equals(600, $h);
});

test('analyze_background detects uniform background', function () {
    $img = imagecreatetruecolor(500, 500);
    $color = imagecolorallocate($img, 250, 250, 250);
    imagefilledrectangle($img, 0, 0, 500, 500, $color);
    $analysis = jps_analyze_background($img);
    assert_true($analysis['uniform'], 'flat colour image must be detected as uniform');
});

test('analyze_background detects noisy background', function () {
    $img = imagecreatetruecolor(300, 300);
    mt_srand(42);
    for ($y = 0; $y < 300; $y += 3) {
        for ($x = 0; $x < 300; $x += 3) {
            $c = imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagefilledrectangle($img, $x, $y, $x + 3, $y + 3, $c);
        }
    }
    $analysis = jps_analyze_background($img);
    assert_false($analysis['uniform'], 'random noise must not be detected as uniform');
});

test('enhance_locally runs and reports operations', function () {
    $img = imagecreatetruecolor(800, 1000);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, 800, 1000, $white);

    $settings = [
        'mode' => 'clean_product_photo',
        'background_style' => 'neutral_studio',
        'aspect_ratio' => '4:5',
        'max_dimension' => 1200,
    ];
    [$resultImg, $operations, $warnings] = jps_enhance_locally($img, $settings);
    assert_true($resultImg instanceof \GdImage);
    assert_true(count($operations) > 0);
    [$w, $h] = jps_image_size($resultImg);
    assert_true(abs($w / $h - 4 / 5) < 0.02, 'expected ~4:5 aspect ratio');
});

test('enhance_locally preserves background when requested', function () {
    $img = imagecreatetruecolor(800, 1000);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, 800, 1000, $white);

    $settings = [
        'mode' => 'clean_product_photo',
        'background_style' => 'preserve_original',
        'aspect_ratio' => 'original',
        'max_dimension' => 1200,
    ];
    [, $operations,] = jps_enhance_locally($img, $settings);
    $found = count(array_filter($operations, fn($op) => str_contains($op, 'Preserved original background'))) > 0;
    assert_true($found, 'expected a "Preserved original background" operation');
});

test('lighting mode does not touch background or aspect ratio', function () {
    $img = imagecreatetruecolor(800, 1000);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, 800, 1000, $white);

    $settings = [
        'mode' => 'lighting_quality',
        'background_style' => 'neutral_studio',
        'aspect_ratio' => 'original',
        'max_dimension' => 1200,
    ];
    [$resultImg, $operations,] = jps_enhance_locally($img, $settings);
    [$w, $h] = jps_image_size($resultImg);
    assert_equals(800, $w);
    assert_equals(1000, $h);
    foreach ($operations as $op) {
        assert_false(str_contains(strtolower($op), 'background'), 'lighting mode must not mention background ops');
    }
});
