<?php
declare(strict_types=1);

/**
 * Generates sample_data/STOCK_PHOTOS_SAMPLE.zip — a small demo upload for
 * trying out the app without your own photos.
 *
 * Run with: php sample_data/generate_sample_zip.php
 */

function garment_photo(int $w, int $h, array $bg, array $garmentColor, bool $defect = false, bool $label = false): \GdImage
{
    $img = imagecreatetruecolor($w, $h);
    $bgColor = imagecolorallocate($img, ...$bg);
    imagefilledrectangle($img, 0, 0, $w, $h, $bgColor);

    $garment = imagecolorallocate($img, ...$garmentColor);
    $mx = intdiv($w, 5);
    $my = intdiv($h, 6);
    imagefilledrectangle($img, $mx, $my, $w - $mx, $h - $my, $garment);

    $collar = imagecolorallocate($img, max(0, $garmentColor[0] - 30), max(0, $garmentColor[1] - 30), max(0, $garmentColor[2] - 30));
    imagefilledpolygon($img, [
        intdiv($w, 2) - 40, $my,
        intdiv($w, 2), $my + 50,
        intdiv($w, 2) + 40, $my,
    ], $collar);

    if ($label) {
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, intdiv($w, 2) - 25, $my + 70, intdiv($w, 2) + 25, $my + 110, $white);
        imagestring($img, 3, intdiv($w, 2) - 20, $my + 82, 'SIZE M', $black);
    }

    if ($defect) {
        $stain = imagecolorallocate($img, 60, 40, 10);
        imagefilledellipse($img, intdiv($w, 2) + 50, intdiv($h, 2) + 15, 40, 35, $stain);
    }

    return $img;
}

$outDir = __DIR__;
$outZip = $outDir . '/STOCK_PHOTOS_SAMPLE.zip';

$zip = new ZipArchive();
$zip->open($outZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$items = [
    'ITEM_01' => [
        ['photo1.jpg', garment_photo(900, 1200, [245, 245, 240], [60, 90, 160])],
        ['photo2.jpg', garment_photo(900, 1200, [238, 238, 232], [60, 90, 160], false, true)],
        ['photo3.jpg', garment_photo(900, 1200, [250, 250, 248], [60, 90, 160], true)],
    ],
    'ITEM_02' => [
        ['front.jpg', garment_photo(900, 1200, [255, 255, 255], [180, 60, 70])],
        ['back.jpg', garment_photo(900, 1200, [255, 255, 255], [180, 60, 70])],
        ['label.jpg', garment_photo(700, 700, [255, 255, 255], [180, 60, 70], false, true)],
    ],
    'ITEM_03' => [
        ['image1.png', garment_photo(1000, 1000, [230, 225, 210], [40, 130, 90])],
    ],
    'ITEM_04' => [
        ['photo.webp', garment_photo(900, 1100, [255, 255, 255], [210, 180, 40], true)],
    ],
];

foreach ($items as $folder => $photos) {
    foreach ($photos as [$filename, $img]) {
        ob_start();
        if (str_ends_with($filename, '.png')) {
            imagepng($img);
        } elseif (str_ends_with($filename, '.webp')) {
            imagewebp($img);
        } else {
            imagejpeg($img, null, 90);
        }
        $bytes = ob_get_clean();
        imagedestroy($img);
        $zip->addFromString("$folder/$filename", $bytes);
    }
}

$zip->close();
echo 'Wrote ' . $outZip . ' (' . round(filesize($outZip) / 1024, 1) . " KB)\n";
