<?php
declare(strict_types=1);

/** Human-readable processing reports (per product and whole-batch). */

const JPS_PRIVACY_NOTICE =
    "Privacy notice: when Gemini processing is enabled, photographs are sent to Google's " .
    'Gemini API for editing. Disable Gemini in the settings to keep all processing local.';

function jps_timestamp(): string
{
    return gmdate('Y-m-d H:i:s') . ' UTC';
}

function jps_product_success_count(array $productReport): int
{
    return count(array_filter($productReport['results'], fn($r) => $r['success'] && !$r['marked_unsafe']));
}

function jps_product_unsafe_count(array $productReport): int
{
    return count(array_filter($productReport['results'], fn($r) => $r['marked_unsafe']));
}

function jps_product_failure_count(array $productReport): int
{
    return count(array_filter($productReport['results'], fn($r) => !$r['success']));
}

function jps_batch_total_success(array $batchReport): int
{
    return array_sum(array_map('jps_product_success_count', $batchReport['product_reports']));
}

function jps_batch_total_unsafe(array $batchReport): int
{
    return array_sum(array_map('jps_product_unsafe_count', $batchReport['product_reports']));
}

function jps_batch_total_failure(array $batchReport): int
{
    return array_sum(array_map('jps_product_failure_count', $batchReport['product_reports']));
}

function jps_build_product_report(array $productReport, array $settings): string
{
    $lines = [];
    $title = "PROCESSING REPORT — {$productReport['folder_name']}";
    $lines[] = $title;
    $lines[] = str_repeat('=', strlen($title));
    $lines[] = 'Generated: ' . jps_timestamp();
    $lines[] = 'Mode: ' . $settings['mode'];
    $lines[] = 'Background style: ' . $settings['background_style'];
    $lines[] = 'Aspect ratio: ' . $settings['aspect_ratio'];
    $lines[] = sprintf(
        'Images: %d total | %d enhanced | %d kept original (safety) | %d failed',
        count($productReport['results']),
        jps_product_success_count($productReport),
        jps_product_unsafe_count($productReport),
        jps_product_failure_count($productReport)
    );
    $lines[] = '';

    foreach ($productReport['results'] as $result) {
        $lines[] = "- {$result['filename']}";
        $status = $result['success']
            ? 'OK'
            : ($result['marked_unsafe'] ? 'UNSAFE / ORIGINAL KEPT' : 'FAILED');
        $lines[] = "    Status: $status";
        if (!empty($result['output_filename'])) {
            $lines[] = "    Output: ENHANCED_PHOTOS/{$result['output_filename']}";
        }
        if ($result['used_gemini']) {
            $lines[] = '    Enhanced by: Gemini AI';
        } elseif ($result['used_local_fallback']) {
            $lines[] = '    Enhanced by: Local processing (GD)';
        } elseif ($result['success']) {
            $lines[] = '    Enhanced by: none (resized only)';
        }
        if ($result['similarity'] !== null) {
            $lines[] = sprintf('    Preservation similarity score: %.2f', $result['similarity']);
        }
        foreach ($result['operations'] as $op) {
            $lines[] = "    - $op";
        }
        foreach ($result['warnings'] as $warning) {
            $lines[] = "    WARNING: $warning";
        }
        if (!empty($result['error'])) {
            $lines[] = "    ERROR: {$result['error']}";
        }
        $lines[] = '';
    }

    $lines[] = $settings['use_gemini'] ? JPS_PRIVACY_NOTICE : 'Gemini was disabled for this batch; all processing was local.';
    $lines[] = '';
    $lines[] = 'This report does not certify the garment\'s condition. Any defects visible in the ' .
        'original photographs (stains, holes, tears, wear, etc.) remain visible in the enhanced ' .
        'photographs by design.';

    return implode("\n", $lines);
}

function jps_build_batch_report(array $batchReport, array $settings, array $extractionWarnings): string
{
    $lines = [];
    $title = 'BATCH PROCESSING REPORT — Jordyn AI Photo Studio (PHP edition)';
    $lines[] = $title;
    $lines[] = str_repeat('=', strlen($title));
    $lines[] = 'Generated: ' . jps_timestamp();
    $lines[] = 'Mode: ' . $settings['mode'];
    $lines[] = 'Gemini enabled: ' . ($settings['use_gemini'] ? 'true' : 'false');
    $lines[] = 'Local enhancement enabled: ' . ($settings['use_local_enhancement'] ? 'true' : 'false');
    $lines[] = 'Background style: ' . $settings['background_style'];
    $lines[] = 'Aspect ratio: ' . $settings['aspect_ratio'];
    $lines[] = 'Products processed: ' . count($batchReport['product_reports']);
    $lines[] = 'Total images: ' . $batchReport['total_images'];
    $lines[] = '  Successfully enhanced: ' . jps_batch_total_success($batchReport);
    $lines[] = '  Kept original (AI safety check failed): ' . jps_batch_total_unsafe($batchReport);
    $lines[] = '  Failed: ' . jps_batch_total_failure($batchReport);
    $lines[] = 'Gemini API calls made: ' . $batchReport['gemini_calls_made'];
    $lines[] = '';

    $lines[] = 'Per-product summary:';
    foreach ($batchReport['product_reports'] as $productReport) {
        $lines[] = sprintf(
            '  - %s: %d images, %d enhanced, %d kept original, %d failed',
            $productReport['folder_name'],
            count($productReport['results']),
            jps_product_success_count($productReport),
            jps_product_unsafe_count($productReport),
            jps_product_failure_count($productReport)
        );
    }
    $lines[] = '';

    if (!empty($extractionWarnings)) {
        $lines[] = 'ZIP extraction warnings:';
        foreach ($extractionWarnings as $w) {
            $lines[] = "  - $w";
        }
        $lines[] = '';
    }

    $lines[] = $settings['use_gemini'] ? JPS_PRIVACY_NOTICE : 'Gemini was disabled for this batch; all processing was local.';
    $lines[] = '';
    $lines[] = 'Strict preservation policy: this application never removes stains, holes, tears or ' .
        'other damage, never changes garment colour/shape/size, and never alters logos, labels or ' .
        'care tags. Any photo the AI could not confidently edit within these rules was left as the ' .
        'original image and flagged above.';

    return implode("\n", $lines);
}
