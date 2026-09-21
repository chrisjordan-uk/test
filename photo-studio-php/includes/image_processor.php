<?php
declare(strict_types=1);

/**
 * Local, non-destructive image enhancement using GD only (no Imagick
 * required — GD ships with virtually every PHP install, including
 * Hostinger shared hosting).
 *
 * Same conservative philosophy as the Python/OpenCV edition:
 * - Lighting/contrast/white-balance/sharpening operate on the whole frame
 *   and never touch the garment's shape or identity.
 * - Background replacement/centring only runs when the background is
 *   confidently near-uniform; otherwise it's left untouched.
 * - Aspect-ratio conversion pads onto a canvas; it never crops into the
 *   product itself (only into the analysed-uniform background margin).
 */

const JPS_BG_COLORS = [
    'white' => [255, 255, 255],
    'light_grey' => [235, 235, 233],
    'beige' => [240, 232, 218],
    'neutral_studio' => [245, 245, 242],
];

const JPS_ASPECT_RATIOS = [
    '4:5' => [4, 5],
    '1:1' => [1, 1],
    '3:4' => [3, 4],
];

function jps_bg_rgb(string $style): array
{
    return JPS_BG_COLORS[$style] ?? JPS_BG_COLORS['neutral_studio'];
}

/** Load an image file into a GD true-color resource, correcting JPEG EXIF rotation. */
function jps_image_load(string $path): \GdImage|false
{
    $info = @getimagesize($path);
    if ($info === false) {
        return false;
    }
    $mime = $info['mime'];

    $image = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => @imagecreatefromwebp($path),
        default => false,
    };
    if ($image === false || !($image instanceof \GdImage)) {
        return false;
    }

    // Flatten transparency (PNG/WebP) onto white before any RGB-only ops.
    $w = imagesx($image);
    $h = imagesy($image);
    $flat = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($flat, 255, 255, 255);
    imagefilledrectangle($flat, 0, 0, $w, $h, $white);
    imagealphablending($flat, true);
    imagecopy($flat, $image, 0, 0, 0, 0, $w, $h);
    imagedestroy($image);
    $image = $flat;

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? 1;
        $image = jps_apply_exif_orientation($image, (int) $orientation);
    }

    return $image;
}

function jps_apply_exif_orientation(\GdImage $image, int $orientation): \GdImage
{
    switch ($orientation) {
        case 3:
            $image = imagerotate($image, 180, 0);
            break;
        case 6:
            $image = imagerotate($image, -90, 0);
            break;
        case 8:
            $image = imagerotate($image, 90, 0);
            break;
    }
    return $image;
}

function jps_image_size(\GdImage $image): array
{
    return [imagesx($image), imagesy($image)];
}

function jps_resize_max_dimension(\GdImage $image, int $maxDimension): \GdImage
{
    [$w, $h] = jps_image_size($image);
    if (max($w, $h) <= $maxDimension) {
        return $image;
    }
    $scale = $maxDimension / max($w, $h);
    $newW = max(1, (int) round($w * $scale));
    $newH = max(1, (int) round($h * $scale));
    $resized = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($image);
    return $resized;
}

function jps_new_canvas(int $w, int $h, array $rgb): \GdImage
{
    $canvas = imagecreatetruecolor($w, $h);
    $color = imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);
    imagefilledrectangle($canvas, 0, 0, $w, $h, $color);
    return $canvas;
}

/**
 * Sample the four corners of the image (at reduced resolution) and decide
 * whether the background looks near-uniform. Conservative by design.
 *
 * @return array{uniform: bool, color: array{0:int,1:int,2:int}, std: float}
 */
function jps_analyze_background(\GdImage $image): array
{
    [$w, $h] = jps_image_size($image);
    $sampleW = min(60, $w);
    $sampleH = min(60, $h);
    $preview = imagecreatetruecolor($sampleW, $sampleH);
    imagecopyresampled($preview, $image, 0, 0, 0, 0, $sampleW, $sampleH, $w, $h);

    $cs = max(3, (int) round(min($sampleW, $sampleH) * 0.15));
    $corners = [
        [0, 0], [$sampleW - $cs, 0], [0, $sampleH - $cs], [$sampleW - $cs, $sampleH - $cs],
    ];

    $cornerMeans = [];
    $allPixelStd = [];
    foreach ($corners as [$cx, $cy]) {
        $pixels = [];
        for ($y = $cy; $y < $cy + $cs && $y < $sampleH; $y++) {
            for ($x = $cx; $x < $cx + $cs && $x < $sampleW; $x++) {
                $rgb = imagecolorat($preview, $x, $y);
                $pixels[] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
            }
        }
        if (empty($pixels)) {
            continue;
        }
        $mean = [0, 0, 0];
        foreach ($pixels as $p) {
            $mean[0] += $p[0];
            $mean[1] += $p[1];
            $mean[2] += $p[2];
        }
        $n = count($pixels);
        $mean = [$mean[0] / $n, $mean[1] / $n, $mean[2] / $n];
        $cornerMeans[] = $mean;

        $variance = 0.0;
        foreach ($pixels as $p) {
            $variance += (($p[0] - $mean[0]) ** 2 + ($p[1] - $mean[1]) ** 2 + ($p[2] - $mean[2]) ** 2) / 3;
        }
        $allPixelStd[] = sqrt($variance / $n);
    }
    imagedestroy($preview);

    if (empty($cornerMeans)) {
        return ['uniform' => false, 'color' => [255, 255, 255], 'std' => 999.0];
    }

    $avgColor = [0.0, 0.0, 0.0];
    foreach ($cornerMeans as $m) {
        $avgColor[0] += $m[0];
        $avgColor[1] += $m[1];
        $avgColor[2] += $m[2];
    }
    $count = count($cornerMeans);
    $avgColor = [$avgColor[0] / $count, $avgColor[1] / $count, $avgColor[2] / $count];

    $betweenStd = 0.0;
    foreach ($cornerMeans as $m) {
        $betweenStd += (($m[0] - $avgColor[0]) ** 2 + ($m[1] - $avgColor[1]) ** 2 + ($m[2] - $avgColor[2]) ** 2) / 3;
    }
    $betweenStd = sqrt($betweenStd / $count);
    $withinStd = array_sum($allPixelStd) / count($allPixelStd);
    $combinedStd = max($betweenStd, $withinStd);

    return [
        'uniform' => $combinedStd < 18.0,
        'color' => [(int) round($avgColor[0]), (int) round($avgColor[1]), (int) round($avgColor[2])],
        'std' => $combinedStd,
    ];
}

/**
 * Conservative foreground bounding box via colour-distance-to-background
 * thresholding on a small downscaled copy (fast, pure PHP/GD, no OpenCV
 * flood fill available). Returns null when segmentation isn't trustworthy.
 *
 * @return array{0:int,1:int,2:int,3:int}|null [x, y, w, h] in ORIGINAL image coordinates.
 */
function jps_segment_foreground_bbox(\GdImage $image, array $bgColor): ?array
{
    [$w, $h] = jps_image_size($image);
    $workSize = 160;
    $scale = min($workSize / $w, $workSize / $h, 1.0);
    $workW = max(1, (int) round($w * $scale));
    $workH = max(1, (int) round($h * $scale));

    $work = imagecreatetruecolor($workW, $workH);
    imagecopyresampled($work, $image, 0, 0, 0, 0, $workW, $workH, $w, $h);

    $threshold = 40; // Euclidean colour-distance threshold vs background.
    $minX = $workW;
    $minY = $workH;
    $maxX = -1;
    $maxY = -1;
    $fgCount = 0;

    for ($y = 0; $y < $workH; $y++) {
        for ($x = 0; $x < $workW; $x++) {
            $rgb = imagecolorat($work, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $dist = sqrt((($r - $bgColor[0]) ** 2) + (($g - $bgColor[1]) ** 2) + (($b - $bgColor[2]) ** 2));
            if ($dist > $threshold) {
                $fgCount++;
                if ($x < $minX) $minX = $x;
                if ($x > $maxX) $maxX = $x;
                if ($y < $minY) $minY = $y;
                if ($y > $maxY) $maxY = $y;
            }
        }
    }
    imagedestroy($work);

    $totalPixels = $workW * $workH;
    $fgRatio = $totalPixels > 0 ? $fgCount / $totalPixels : 0;
    if ($maxX < 0 || $fgRatio < 0.02 || $fgRatio > 0.92) {
        return null; // Nothing confidently found, or segmentation looks unreliable.
    }

    $scaleBack = 1 / $scale;
    $x0 = (int) floor($minX * $scaleBack);
    $y0 = (int) floor($minY * $scaleBack);
    $x1 = (int) ceil(($maxX + 1) * $scaleBack);
    $y1 = (int) ceil(($maxY + 1) * $scaleBack);

    return [$x0, $y0, min($w, $x1) - $x0, min($h, $y1) - $y0];
}

function jps_crop_to_bbox_with_margin(\GdImage $image, array $bbox, float $marginFraction = 0.08): \GdImage
{
    [$x, $y, $bw, $bh] = $bbox;
    [$imgW, $imgH] = jps_image_size($image);
    $mx = (int) round($bw * $marginFraction);
    $my = (int) round($bh * $marginFraction);

    $left = max(0, $x - $mx);
    $top = max(0, $y - $my);
    $right = min($imgW, $x + $bw + $mx);
    $bottom = min($imgH, $y + $bh + $my);

    $cropW = max(1, $right - $left);
    $cropH = max(1, $bottom - $top);

    $cropped = imagecreatetruecolor($cropW, $cropH);
    imagecopy($cropped, $image, 0, 0, $left, $top, $cropW, $cropH);
    imagedestroy($image);
    return $cropped;
}

function jps_pad_to_canvas(\GdImage $image, array $bgRgb, float $marginFraction = 0.06): \GdImage
{
    [$w, $h] = jps_image_size($image);
    $margin = (int) round(max($w, $h) * $marginFraction);
    $canvas = jps_new_canvas($w + 2 * $margin, $h + 2 * $margin, $bgRgb);
    imagecopy($canvas, $image, $margin, $margin, 0, 0, $w, $h);
    imagedestroy($image);
    return $canvas;
}

function jps_convert_aspect_ratio(\GdImage $image, string $ratio, array $bgRgb): \GdImage
{
    if ($ratio === 'original' || !isset(JPS_ASPECT_RATIOS[$ratio])) {
        return $image;
    }
    [$tw, $th] = JPS_ASPECT_RATIOS[$ratio];
    $targetAspect = $tw / $th;
    [$w, $h] = jps_image_size($image);
    $currentAspect = $w / $h;

    if (abs($currentAspect - $targetAspect) < 1e-3) {
        return $image;
    }

    if ($currentAspect > $targetAspect) {
        $newW = $w;
        $newH = (int) round($w / $targetAspect);
    } else {
        $newH = $h;
        $newW = (int) round($h * $targetAspect);
    }

    $canvas = jps_new_canvas($newW, $newH, $bgRgb);
    $offsetX = (int) (($newW - $w) / 2);
    $offsetY = (int) (($newH - $h) / 2);
    imagecopy($canvas, $image, $offsetX, $offsetY, 0, 0, $w, $h);
    imagedestroy($image);
    return $canvas;
}

/** Additive gray-world white balance — a mild, safe colour-cast correction. */
function jps_white_balance(\GdImage $image): \GdImage
{
    [$w, $h] = jps_image_size($image);
    $sampleW = min(80, $w);
    $sampleH = min(80, $h);
    $preview = imagecreatetruecolor($sampleW, $sampleH);
    imagecopyresampled($preview, $image, 0, 0, 0, 0, $sampleW, $sampleH, $w, $h);

    $sum = [0, 0, 0];
    $n = $sampleW * $sampleH;
    for ($y = 0; $y < $sampleH; $y++) {
        for ($x = 0; $x < $sampleW; $x++) {
            $rgb = imagecolorat($preview, $x, $y);
            $sum[0] += ($rgb >> 16) & 0xFF;
            $sum[1] += ($rgb >> 8) & 0xFF;
            $sum[2] += $rgb & 0xFF;
        }
    }
    imagedestroy($preview);

    $avg = [$sum[0] / $n, $sum[1] / $n, $sum[2] / $n];
    $gray = array_sum($avg) / 3;

    // Clamp the shift so this stays a gentle correction, never a colour change.
    $dr = max(-15, min(15, $gray - $avg[0]));
    $dg = max(-15, min(15, $gray - $avg[1]));
    $db = max(-15, min(15, $gray - $avg[2]));

    imagefilter($image, IMG_FILTER_COLORIZE, (int) round($dr), (int) round($dg), (int) round($db));
    return $image;
}

/** Auto brightness/contrast based on sampled average luminance. */
function jps_auto_brightness_contrast(\GdImage $image): \GdImage
{
    [$w, $h] = jps_image_size($image);
    $sampleW = min(80, $w);
    $sampleH = min(80, $h);
    $preview = imagecreatetruecolor($sampleW, $sampleH);
    imagecopyresampled($preview, $image, 0, 0, 0, 0, $sampleW, $sampleH, $w, $h);

    $total = 0;
    $n = $sampleW * $sampleH;
    for ($y = 0; $y < $sampleH; $y++) {
        for ($x = 0; $x < $sampleW; $x++) {
            $rgb = imagecolorat($preview, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $total += (0.299 * $r + 0.587 * $g + 0.114 * $b);
        }
    }
    imagedestroy($preview);
    $avgLuminance = $total / $n;

    // Gently push towards a comfortable mid-tone target without overdoing it.
    $target = 175;
    $brightnessShift = max(-25, min(25, ($target - $avgLuminance) * 0.35));
    imagefilter($image, IMG_FILTER_BRIGHTNESS, (int) round($brightnessShift));
    imagefilter($image, IMG_FILTER_CONTRAST, -6); // small contrast boost (GD: negative = more contrast)

    return $image;
}

function jps_denoise_and_sharpen(\GdImage $image): \GdImage
{
    // Mild smoothing to tame minor noise, then a standard sharpen kernel for clarity.
    imagefilter($image, IMG_FILTER_SMOOTH, 6);

    $sharpenMatrix = [
        [0, -1, 0],
        [-1, 5, -1],
        [0, -1, 0],
    ];
    imageconvolution($image, $sharpenMatrix, 1, 0);
    return $image;
}

/**
 * Full local enhancement pipeline.
 *
 * @param array{mode:string, background_style:string, aspect_ratio:string, max_dimension:int} $settings
 * @return array{0: \GdImage, 1: string[], 2: string[]}
 */
function jps_enhance_locally(\GdImage $image, array $settings): array
{
    $operations = [];
    $warnings = [];

    $image = jps_white_balance($image);
    $operations[] = 'Corrected white balance (gray-world).';

    $image = jps_auto_brightness_contrast($image);
    $operations[] = 'Improved brightness/contrast automatically.';

    $image = jps_denoise_and_sharpen($image);
    $operations[] = 'Reduced minor noise and applied gentle sharpening.';

    $wantsBackgroundWork = in_array($settings['mode'], [
        'clean_product_photo', 'background_cleanup', 'marketplace_cover', 'batch_consistency',
    ], true);

    if ($wantsBackgroundWork && $settings['background_style'] !== 'preserve_original') {
        $analysis = jps_analyze_background($image);
        if ($analysis['uniform']) {
            $bbox = jps_segment_foreground_bbox($image, $analysis['color']);
            $bgRgb = jps_bg_rgb($settings['background_style']);
            if ($bbox !== null) {
                $image = jps_crop_to_bbox_with_margin($image, $bbox);
                $image = jps_pad_to_canvas($image, $bgRgb);
                $operations[] = "Centred product and replaced background with '{$settings['background_style']}' (uniform background detected).";
            } else {
                $warnings[] = 'Background looked uniform but the product could not be confidently isolated; left background untouched to avoid a misleading crop.';
            }
        } else {
            $warnings[] = 'Background is not uniform enough to safely replace; kept the original background.';
        }
    } elseif ($settings['background_style'] === 'preserve_original') {
        $operations[] = 'Preserved original background (per settings).';
    }

    $targetRatio = $settings['aspect_ratio'];
    if ($settings['mode'] === 'marketplace_cover' && $targetRatio === 'original') {
        $targetRatio = '4:5';
    }
    if ($targetRatio !== 'original') {
        $bgRgb = jps_bg_rgb($settings['background_style']);
        $image = jps_convert_aspect_ratio($image, $targetRatio, $bgRgb);
        $operations[] = "Converted to $targetRatio aspect ratio (padded, not cropped).";
    }

    $image = jps_resize_max_dimension($image, $settings['max_dimension']);
    $operations[] = "Resized to a maximum dimension of {$settings['max_dimension']}px.";

    return [$image, $operations, $warnings];
}

/** Lightweight finishing pass applied on top of a Gemini-edited image. */
function jps_finish_image(\GdImage $image, array $settings): array
{
    $operations = [];

    $targetRatio = $settings['aspect_ratio'];
    if ($settings['mode'] === 'marketplace_cover' && $targetRatio === 'original') {
        $targetRatio = '4:5';
    }
    if ($targetRatio !== 'original') {
        $bgRgb = jps_bg_rgb($settings['background_style']);
        $image = jps_convert_aspect_ratio($image, $targetRatio, $bgRgb);
        $operations[] = "Converted to $targetRatio aspect ratio (padded, not cropped).";
    }

    $image = jps_resize_max_dimension($image, $settings['max_dimension']);
    $operations[] = "Resized to a maximum dimension of {$settings['max_dimension']}px.";

    return [$image, $operations];
}

function jps_save_jpeg(\GdImage $image, string $destPath, int $quality): bool
{
    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return imagejpeg($image, $destPath, $quality);
}
