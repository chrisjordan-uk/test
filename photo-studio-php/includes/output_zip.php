<?php
declare(strict_types=1);

/** Builds the final downloadable ZIP: originals + enhanced photos + reports. */

const JPS_OUTPUT_ROOT_NAME = 'JORDYN_PHOTO_STUDIO_OUTPUT';

function jps_build_output_zip(
    array $products,
    array $batchReport,
    array $settings,
    string $enhancedRoot,
    string $destZipPath,
    array $extractionWarnings = []
): string {
    $reportByFolder = [];
    foreach ($batchReport['product_reports'] as $pr) {
        $reportByFolder[$pr['folder_name']] = $pr;
    }

    $destDir = dirname($destZipPath);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0775, true);
    }

    $zip = new ZipArchive();
    if ($zip->open($destZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Could not create output ZIP at $destZipPath");
    }

    foreach ($products as $product) {
        $base = JPS_OUTPUT_ROOT_NAME . '/' . $product['name'];

        foreach ($product['images'] as $image) {
            $zip->addFile($image['path'], "$base/ORIGINAL_PHOTOS/{$image['filename']}");
        }

        $enhancedDir = $enhancedRoot . '/' . $product['name'] . '/ENHANCED_PHOTOS';
        if (is_dir($enhancedDir)) {
            $files = scandir($enhancedDir);
            sort($files);
            foreach ($files as $file) {
                $path = $enhancedDir . '/' . $file;
                if (is_file($path)) {
                    $zip->addFile($path, "$base/ENHANCED_PHOTOS/$file");
                }
            }
        }

        if (isset($reportByFolder[$product['name']])) {
            $reportText = jps_build_product_report($reportByFolder[$product['name']], $settings);
            $zip->addFromString("$base/PROCESSING_REPORT.txt", $reportText);
        }
    }

    $batchText = jps_build_batch_report($batchReport, $settings, $extractionWarnings);
    $zip->addFromString(JPS_OUTPUT_ROOT_NAME . '/BATCH_PROCESSING_REPORT.txt', $batchText);

    $zip->close();
    return $destZipPath;
}
