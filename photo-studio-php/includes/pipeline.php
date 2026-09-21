<?php
declare(strict_types=1);

/** Orchestrates processing a whole batch of product folders end-to-end. */

function jps_save_enhanced(\GdImage $image, string $destPath, int $jpegQuality): bool
{
    $ok = jps_save_jpeg($image, $destPath, $jpegQuality);
    imagedestroy($image);
    return $ok;
}

/**
 * @param array{filename:string, path:string, size:int} $imageRecord
 * @param array{mode:string, use_gemini:bool, use_local_enhancement:bool, background_style:string, aspect_ratio:string, max_dimension:int, jpeg_quality:int} $settings
 * @return array
 */
function jps_process_single_image(array $imageRecord, array $settings, string $enhancedDir, string $geminiCacheDir): array
{
    $stem = pathinfo($imageRecord['filename'], PATHINFO_FILENAME);
    $outputFilename = "{$stem}_enhanced.jpg";
    $destPath = $enhancedDir . '/' . $outputFilename;

    $original = jps_image_load($imageRecord['path']);
    if ($original === false) {
        // Last-resort fallback: copy the raw original bytes through unchanged.
        $copiedName = null;
        if (!is_dir($enhancedDir)) {
            @mkdir($enhancedDir, 0775, true);
        }
        $rawDest = $enhancedDir . '/' . $imageRecord['filename'];
        if (@copy($imageRecord['path'], $rawDest)) {
            $copiedName = $imageRecord['filename'];
        }

        $error = 'Could not read image (unsupported or corrupt file); original file copied unchanged where possible.';
        if (jps_is_heic_signature($imageRecord['path'])) {
            $tip = 'Convert to JPEG before uploading, or set your iPhone to Settings > Camera > Formats > "Most Compatible".';
            $error = class_exists('Imagick')
                ? "This HEIC/HEIF photo could not be decoded (the server's ImageMagick build lacks HEIF support); original file copied unchanged. $tip"
                : "This HEIC/HEIF photo could not be decoded (no HEIC decoder available on this server - the Imagick extension is not installed); original file copied unchanged. $tip";
        }

        return [
            'filename' => $imageRecord['filename'],
            'success' => false,
            'used_gemini' => false,
            'used_local_fallback' => false,
            'marked_unsafe' => false,
            'operations' => [],
            'warnings' => [],
            'error' => $error,
            'output_filename' => $copiedName,
            'similarity' => null,
        ];
    }

    $operations = [];
    $warnings = [];
    $usedGemini = false;
    $usedLocalFallback = false;
    $markedUnsafe = false;
    $similarity = null;
    $resultImage = null;
    $success = true;
    $geminiAttemptedUnsafe = false;

    if ($settings['use_gemini'] && jps_gemini_configured()) {
        $geminiResult = jps_gemini_edit_image($original, $settings['mode'], $settings['background_style'], $geminiCacheDir);
        if ($geminiResult['image'] !== null && $geminiResult['is_safe']) {
            $resultImage = $geminiResult['image'];
            $usedGemini = true;
            $similarity = $geminiResult['similarity'];
            $cacheNote = $geminiResult['used_cache'] ? ' (served from cache)' : '';
            $operations[] = sprintf(
                'Enhanced with Gemini (%s)%s; preservation check passed (similarity=%.2f).',
                GEMINI_IMAGE_MODEL,
                $cacheNote,
                $geminiResult['similarity']
            );
        } else {
            $geminiAttemptedUnsafe = true;
            if ($geminiResult['error']) {
                $warnings[] = "Gemini request failed: {$geminiResult['error']}";
            } else {
                $warnings[] = sprintf(
                    'Gemini edit failed the product-preservation safety check (similarity=%.2f below threshold); AI edit discarded.',
                    $geminiResult['similarity'] ?? 0.0
                );
            }
        }
    } elseif ($settings['use_gemini'] && !jps_gemini_configured()) {
        $warnings[] = 'Gemini requested but not configured (missing GEMINI_API_KEY); used local processing instead.';
    }

    if ($geminiAttemptedUnsafe) {
        // Strict safety rule: if AI editing cannot be verified safe, keep the
        // ORIGINAL image rather than risk any misleading alteration.
        $resultImage = jps_resize_max_dimension(jps_clone_image($original), $settings['max_dimension']);
        $markedUnsafe = true;
        $success = false;
        $operations[] = 'Kept the original, unmodified photograph because the AI edit was rejected.';
    } elseif ($resultImage !== null) {
        [$resultImage, $finishOps] = jps_finish_image($resultImage, $settings);
        $operations = array_merge($operations, $finishOps);
    } else {
        if ($settings['use_local_enhancement']) {
            try {
                [$resultImage, $localOps, $localWarnings] = jps_enhance_locally($original, $settings);
                $operations = array_merge($operations, $localOps);
                $warnings = array_merge($warnings, $localWarnings);
                $usedLocalFallback = true;
            } catch (\Throwable $e) {
                $warnings[] = "Local enhancement failed ({$e->getMessage()}); kept original image unchanged.";
                $resultImage = jps_resize_max_dimension(jps_clone_image($original), $settings['max_dimension']);
            }
        } else {
            $resultImage = jps_resize_max_dimension(jps_clone_image($original), $settings['max_dimension']);
            $operations[] = 'Local enhancement disabled; original kept as-is (resized only).';
        }
    }

    imagedestroy($original);

    if (!jps_save_enhanced($resultImage, $destPath, $settings['jpeg_quality'])) {
        return [
            'filename' => $imageRecord['filename'],
            'success' => false,
            'used_gemini' => false,
            'used_local_fallback' => false,
            'marked_unsafe' => false,
            'operations' => [],
            'warnings' => [],
            'error' => 'Failed to save processed image.',
            'output_filename' => null,
            'similarity' => null,
        ];
    }

    return [
        'filename' => $imageRecord['filename'],
        'success' => $success,
        'used_gemini' => $usedGemini,
        'used_local_fallback' => $usedLocalFallback,
        'marked_unsafe' => $markedUnsafe,
        'operations' => $operations,
        'warnings' => $warnings,
        'error' => null,
        'output_filename' => $outputFilename,
        'similarity' => $similarity,
    ];
}

/**
 * @param array<int, array{name:string, images:array}> $products
 * @param callable(string,string,int,int):void|null $progressCallback
 */
function jps_process_batch(array $products, array $settings, string $outputRoot, string $geminiCacheDir, ?callable $progressCallback = null): array
{
    $selected = $settings['selected_folders'] ?? null;
    $activeProducts = $selected === null
        ? $products
        : array_values(array_filter($products, fn($p) => in_array($p['name'], $selected, true)));

    $totalImages = array_sum(array_map(fn($p) => count($p['images']), $activeProducts));
    $done = 0;

    $batchReport = ['product_reports' => [], 'total_images' => 0, 'gemini_calls_made' => 0];

    foreach ($activeProducts as $product) {
        $productReport = ['folder_name' => $product['name'], 'results' => []];
        $enhancedDir = $outputRoot . '/' . $product['name'] . '/ENHANCED_PHOTOS';

        foreach ($product['images'] as $imageRecord) {
            if ($progressCallback) {
                $progressCallback($product['name'], $imageRecord['filename'], $done, $totalImages);
            }

            $result = jps_process_single_image($imageRecord, $settings, $enhancedDir, $geminiCacheDir);
            $productReport['results'][] = $result;
            if ($result['used_gemini']) {
                $batchReport['gemini_calls_made']++;
            }
            $done++;
        }

        $batchReport['product_reports'][] = $productReport;
    }

    $batchReport['total_images'] = $totalImages;
    if ($progressCallback) {
        $progressCallback('', '', $done, $totalImages);
    }

    return $batchReport;
}
