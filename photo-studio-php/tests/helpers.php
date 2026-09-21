<?php
declare(strict_types=1);

function make_test_image_bytes(string $format = 'jpeg', int $w = 400, int $h = 500, array $bg = [255, 255, 255]): string
{
    $img = imagecreatetruecolor($w, $h);
    $bgColor = imagecolorallocate($img, ...$bg);
    imagefilledrectangle($img, 0, 0, $w, $h, $bgColor);
    $fg = imagecolorallocate($img, 120, 60, 200);
    imagefilledrectangle($img, intdiv($w, 4), intdiv($h, 4), $w - intdiv($w, 4), $h - intdiv($h, 4), $fg);

    ob_start();
    match ($format) {
        'png' => imagepng($img),
        'webp' => imagewebp($img),
        default => imagejpeg($img, null, 90),
    };
    $bytes = ob_get_clean();
    imagedestroy($img);
    return $bytes;
}

function make_temp_dir(string $prefix = 'jps_test_'): string
{
    $dir = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(6));
    mkdir($dir, 0775, true);
    return $dir;
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        is_dir($path) ? rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

/** Builds a realistic small sample ZIP: 4 products, mixed formats, junk files,
 *  and a path-traversal attempt that must be neutralised on extraction. */
function make_sample_zip(string $zipPath): void
{
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $zip->addFromString('ITEM_01/photo1.jpg', make_test_image_bytes('jpeg', 400, 500, [250, 250, 250]));
    $zip->addFromString('ITEM_01/photo2.jpg', make_test_image_bytes('jpeg', 400, 500, [248, 248, 248]));

    $zip->addFromString('ITEM_02/front.jpg', make_test_image_bytes('jpeg', 400, 500, [255, 255, 255]));
    $zip->addFromString('ITEM_02/back.jpg', make_test_image_bytes('jpeg', 400, 500, [255, 255, 255]));
    $zip->addFromString('ITEM_02/label.png', make_test_image_bytes('png', 400, 500, [255, 255, 255]));

    $zip->addFromString('ITEM_03/image1.png', make_test_image_bytes('png', 400, 400, [240, 240, 240]));

    $zip->addFromString('ITEM_04/photo.webp', make_test_image_bytes('webp', 400, 480, [255, 255, 255]));

    // Junk that must be ignored.
    $zip->addFromString('__MACOSX/._photo1.jpg', 'not a real image');
    $zip->addFromString('.DS_Store', 'junk');
    $zip->addFromString('ITEM_01/notes.txt', 'not an image, should be skipped');

    // Path traversal attempt — must never land outside the extraction dir.
    $zip->addFromString('../../evil.jpg', make_test_image_bytes());

    $zip->close();
}

function default_test_settings(array $overrides = []): array
{
    return array_merge([
        'mode' => 'clean_product_photo',
        'use_gemini' => false,
        'use_local_enhancement' => true,
        'background_style' => 'neutral_studio',
        'aspect_ratio' => '4:5',
        'max_dimension' => 1200,
        'jpeg_quality' => 85,
        'selected_folders' => null,
    ], $overrides);
}

/**
 * A minimal ISO-BMFF "ftyp" box with a HEIC brand — enough for the HEIC
 * signature sniffer to recognise, without needing a real HEVC-encoded
 * payload (which nothing in this pure-PHP test environment can produce).
 */
function make_fake_heic_bytes(): string
{
    return pack('N', 20) . 'ftyp' . 'heic' . pack('N', 0) . 'mif1';
}

function assert_true($cond, string $msg = ''): void
{
    if (!$cond) {
        throw new Exception('Assertion failed: ' . $msg);
    }
}

function assert_false($cond, string $msg = ''): void
{
    assert_true(!$cond, $msg);
}

function assert_equals($expected, $actual, string $msg = ''): void
{
    if ($expected != $actual) {
        throw new Exception(sprintf('%s (expected %s, got %s)', $msg, var_export($expected, true), var_export($actual, true)));
    }
}

function assert_throws(callable $fn, string $exceptionClass, string $msg = ''): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        if ($e instanceof $exceptionClass) {
            return;
        }
        throw new Exception("$msg (wrong exception type: " . get_class($e) . ')');
    }
    throw new Exception("$msg (expected $exceptionClass to be thrown, nothing was)");
}
