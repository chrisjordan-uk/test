<?php
declare(strict_types=1);

/**
 * Upload ZIP validation, safe extraction and product-folder detection.
 *
 * Security notes:
 * - jps_safe_extract_zip() rejects any entry whose resolved path would land
 *   outside the destination directory (zip slip / path traversal), and
 *   rejects absolute paths and ".." components. Files are extracted by
 *   reading each entry's bytes ourselves and writing to a path we computed
 *   and validated — never via ZipArchive::extractTo() with raw entry names.
 * - File size / count / type are bounded before anything is written.
 */

class JpsZipException extends Exception
{
}

function jps_is_ignored_path(string $name): bool
{
    foreach (explode('/', str_replace('\\', '/', $name)) as $part) {
        if ($part === '') {
            continue;
        }
        if (in_array($part, IGNORED_NAMES, true) || str_starts_with($part, '._')) {
            return true;
        }
    }
    return false;
}

function jps_is_safe_member(string $memberName, string $destDir): bool
{
    $name = trim($memberName);
    if ($name === '' || $name === '.') {
        return false;
    }
    $normalized = str_replace('\\', '/', $name);
    if (str_starts_with($normalized, '/') || preg_match('#^[A-Za-z]:#', $normalized)) {
        return false; // absolute POSIX or Windows drive path
    }
    foreach (explode('/', $normalized) as $part) {
        if ($part === '..') {
            return false;
        }
    }
    $destReal = realpath($destDir);
    if ($destReal === false) {
        return false;
    }
    // The target file doesn't exist yet, so resolve ".." segments lexically
    // rather than relying on realpath() (which requires the path to exist).
    $resolvedLexical = jps_lexical_resolve($destDir . '/' . $normalized);
    return str_starts_with($resolvedLexical, rtrim($destReal, '/') . '/')
        || $resolvedLexical === $destReal;
}

/** Resolve "." / ".." segments lexically without requiring the path to exist. */
function jps_lexical_resolve(string $path): string
{
    $parts = explode('/', str_replace('\\', '/', $path));
    $stack = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($stack);
            continue;
        }
        $stack[] = $part;
    }
    return '/' . implode('/', $stack);
}

function jps_validate_zip_size(int $sizeBytes): void
{
    $maxBytes = MAX_ZIP_SIZE_MB * 1024 * 1024;
    if ($sizeBytes <= 0) {
        throw new JpsZipException('The uploaded file is empty.');
    }
    if ($sizeBytes > $maxBytes) {
        throw new JpsZipException(sprintf(
            'The uploaded ZIP is %.1f MB, which exceeds the configured limit of %d MB (MAX_ZIP_SIZE_MB).',
            $sizeBytes / (1024 * 1024),
            MAX_ZIP_SIZE_MB
        ));
    }
}

/**
 * @return array{extracted:int, warnings:string[]}
 */
function jps_safe_extract_zip(string $zipPath, string $destDir): array
{
    if (!is_dir($destDir)) {
        mkdir($destDir, 0775, true);
    }

    $zip = new ZipArchive();
    $openResult = $zip->open($zipPath, ZipArchive::CHECKCONS);
    if ($openResult !== true) {
        throw new JpsZipException('The uploaded file is not a valid ZIP archive.');
    }

    $extracted = 0;
    $warnings = [];
    $maxImageBytes = MAX_IMAGE_FILE_SIZE_MB * 1024 * 1024;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat === false) {
            continue;
        }
        $name = $stat['name'];
        if (str_ends_with($name, '/')) {
            continue; // directory entry
        }
        if (jps_is_ignored_path($name)) {
            continue;
        }
        if (!jps_is_safe_member($name, $destDir)) {
            $warnings[] = "Skipped unsafe path in ZIP: \"$name\"";
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, SUPPORTED_EXTENSIONS, true)) {
            $warnings[] = "Skipped unsupported file type: \"$name\"";
            continue;
        }

        if ($stat['size'] > $maxImageBytes) {
            $warnings[] = sprintf(
                'Skipped "%s": %.1f MB exceeds the per-image limit of %d MB.',
                $name,
                $stat['size'] / (1024 * 1024),
                MAX_IMAGE_FILE_SIZE_MB
            );
            continue;
        }

        $targetPath = $destDir . '/' . str_replace('\\', '/', $name);
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $data = $zip->getFromIndex($i);
        if ($data === false) {
            $warnings[] = "Could not read \"$name\" from the archive; skipped.";
            continue;
        }
        file_put_contents($targetPath, $data);
        $extracted++;
    }

    $zip->close();

    if ($extracted === 0) {
        throw new JpsZipException(
            'No supported image files were found in the ZIP (expected .jpg, .jpeg, .png or .webp ' .
            'files inside product folders).'
        );
    }

    return ['extracted' => $extracted, 'warnings' => $warnings];
}

/**
 * @return array{0: array<int, array{name:string, images:array<int, array{filename:string, path:string, size:int}>}>, 1: string[]}
 */
function jps_detect_product_folders(string $destDir): array
{
    $warnings = [];
    $folders = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($destDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $relativePath = ltrim(str_replace($destDir, '', $fileInfo->getPathname()), '/');
        if (jps_is_ignored_path($relativePath)) {
            continue;
        }
        $ext = strtolower($fileInfo->getExtension());
        if (!in_array($ext, SUPPORTED_EXTENSIONS, true)) {
            continue;
        }
        if (str_starts_with($fileInfo->getFilename(), '._')) {
            continue;
        }

        $relativeDir = dirname($relativePath);
        $folderKey = ($relativeDir === '.') ? 'UNSORTED' : $relativeDir;

        $folders[$folderKey][] = [
            'filename' => $fileInfo->getFilename(),
            'path' => $fileInfo->getPathname(),
            'size' => $fileInfo->getSize(),
        ];
    }

    if (empty($folders)) {
        throw new JpsZipException('No product folders containing images were detected in the ZIP.');
    }

    if (count($folders) > MAX_PRODUCT_FOLDERS) {
        throw new JpsZipException(sprintf(
            'The ZIP contains %d product folders, which exceeds the configured limit of %d (MAX_PRODUCT_FOLDERS).',
            count($folders),
            MAX_PRODUCT_FOLDERS
        ));
    }

    $totalImages = array_sum(array_map('count', $folders));
    if ($totalImages > MAX_TOTAL_IMAGES) {
        throw new JpsZipException(sprintf(
            'The ZIP contains %d images, which exceeds the configured total limit of %d (MAX_TOTAL_IMAGES).',
            $totalImages,
            MAX_TOTAL_IMAGES
        ));
    }

    ksort($folders);
    $products = [];
    foreach ($folders as $name => $images) {
        usort($images, fn($a, $b) => strcmp($a['filename'], $b['filename']));
        if (count($images) > MAX_IMAGES_PER_FOLDER) {
            $warnings[] = sprintf(
                '"%s" has %d images; only the first %d (MAX_IMAGES_PER_FOLDER) will be processed.',
                $name,
                count($images),
                MAX_IMAGES_PER_FOLDER
            );
            $images = array_slice($images, 0, MAX_IMAGES_PER_FOLDER);
        }
        $products[] = ['name' => $name, 'images' => $images];
    }

    return [$products, $warnings];
}
