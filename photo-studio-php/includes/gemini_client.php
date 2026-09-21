<?php
declare(strict_types=1);

/**
 * Google Gemini image-editing integration via the REST API (cURL — no SDK
 * needed, works on any shared host with the curl extension enabled).
 *
 * Strict product-preservation instructions are sent with every request,
 * and every edited image is compared against the original with a coarse
 * perceptual-similarity check before being trusted (see
 * jps_image_similarity() below). If it's not configured (no API key), or a
 * call fails, callers should use the local GD fallback instead — this
 * module never throws for "not configured", it just reports itself unusable.
 */

const JPS_MODE_FOCUS = [
    'clean_product_photo' => 'Improve lighting, background and framing for a clean catalog look.',
    'background_cleanup' => 'Focus only on cleaning up and neutralising the background.',
    'lighting_quality' => 'Focus only on lighting, exposure, contrast, white balance and sharpness. Do not change the background or framing.',
    'marketplace_cover' => 'Create a clean, centred cover-image presentation suitable for a marketplace listing.',
    'batch_consistency' => 'Match a consistent, neutral studio presentation style suitable for a batch of similar product photos.',
];

const JPS_GEMINI_BASE_INSTRUCTION =
    'Improve the presentation of this clothing product photograph. Preserve the exact garment, ' .
    'colour, shape, size, texture, logos, labels and all visible defects. You may improve lighting, ' .
    'framing and the background only. Do not remove or conceal stains, holes, tears, damage or wear. ' .
    'Do not add or remove product details. Do not change the pattern, print or fabric texture. ' .
    'The result must remain an honest, accurate representation of the original product photograph.';

function jps_build_gemini_instruction(string $mode, string $backgroundStyle): string
{
    $focus = JPS_MODE_FOCUS[$mode] ?? '';
    if ($backgroundStyle !== 'preserve_original') {
        $label = str_replace('_', ' ', $backgroundStyle);
        $backgroundNote = " Use a simple, neutral $label background.";
    } else {
        $backgroundNote = ' Keep the original background as-is.';
    }
    return JPS_GEMINI_BASE_INSTRUCTION . ' ' . $focus . $backgroundNote;
}

/**
 * Coarse perceptual similarity (0..1, 1 = identical) between two GD images.
 * Not true SSIM (no OpenCV available in plain PHP) — a 32x32 grayscale
 * average-difference comparison, good enough as a safety gate to reject
 * Gemini edits that look structurally very different from the source.
 */
function jps_image_similarity(\GdImage $a, \GdImage $b, int $gridSize = 32): float
{
    $grayA = jps_grayscale_grid($a, $gridSize);
    $grayB = jps_grayscale_grid($b, $gridSize);

    $n = count($grayA);
    $sumDiff = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $sumDiff += abs($grayA[$i] - $grayB[$i]);
    }
    $avgDiff = $n > 0 ? $sumDiff / $n : 255.0;
    return max(0.0, 1.0 - ($avgDiff / 255.0));
}

function jps_grayscale_grid(\GdImage $image, int $gridSize): array
{
    [$w, $h] = jps_image_size($image);
    $small = imagecreatetruecolor($gridSize, $gridSize);
    imagecopyresampled($small, $image, 0, 0, 0, 0, $gridSize, $gridSize, $w, $h);

    $values = [];
    for ($y = 0; $y < $gridSize; $y++) {
        for ($x = 0; $x < $gridSize; $x++) {
            $rgb = imagecolorat($small, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $values[] = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        }
    }
    imagedestroy($small);
    return $values;
}

/**
 * @return array{image: \GdImage|null, used_cache: bool, from_gemini: bool, similarity: float|null, is_safe: bool, error: string|null}
 */
function jps_gemini_edit_image(\GdImage $image, string $mode, string $backgroundStyle, string $cacheDir): array
{
    $result = ['image' => null, 'used_cache' => false, 'from_gemini' => false, 'similarity' => null, 'is_safe' => false, 'error' => null];

    if (!jps_gemini_configured()) {
        $result['error'] = 'Gemini is not configured (missing GEMINI_API_KEY).';
        return $result;
    }

    $uploadImage = jps_resize_max_dimension(jps_clone_image($image), GEMINI_MAX_UPLOAD_DIMENSION);
    $instruction = jps_build_gemini_instruction($mode, $backgroundStyle);

    ob_start();
    imagepng($uploadImage);
    $pngBytes = ob_get_clean();

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $cacheKey = hash('sha256', $pngBytes . $instruction . GEMINI_IMAGE_MODEL);
    $cachePath = $cacheDir . '/' . $cacheKey . '.png';

    if (is_readable($cachePath)) {
        $edited = imagecreatefrompng($cachePath);
        if ($edited !== false) {
            $similarity = jps_image_similarity($uploadImage, $edited);
            $result['image'] = $edited;
            $result['used_cache'] = true;
            $result['from_gemini'] = true;
            $result['similarity'] = $similarity;
            $result['is_safe'] = $similarity >= GEMINI_MIN_SIMILARITY;
            return $result;
        }
    }

    try {
        $rawBytes = jps_gemini_call_with_retries($pngBytes, $instruction);
    } catch (\Throwable $e) {
        $result['error'] = $e->getMessage();
        return $result;
    }

    $edited = @imagecreatefromstring($rawBytes);
    if ($edited === false) {
        $result['error'] = 'Gemini response did not contain a decodable image.';
        return $result;
    }

    $similarity = jps_image_similarity($uploadImage, $edited);
    @imagepng($edited, $cachePath);

    $result['image'] = $edited;
    $result['from_gemini'] = true;
    $result['similarity'] = $similarity;
    $result['is_safe'] = $similarity >= GEMINI_MIN_SIMILARITY;
    return $result;
}

function jps_clone_image(\GdImage $image): \GdImage
{
    [$w, $h] = jps_image_size($image);
    $copy = imagecreatetruecolor($w, $h);
    imagecopy($copy, $image, 0, 0, 0, 0, $w, $h);
    return $copy;
}

/** @throws Exception */
function jps_gemini_call_with_retries(string $pngBytes, string $instruction): string
{
    $lastError = null;
    for ($attempt = 1; $attempt <= GEMINI_MAX_RETRIES; $attempt++) {
        try {
            return jps_gemini_call_once($pngBytes, $instruction);
        } catch (\Exception $e) {
            $lastError = $e;
            if ($attempt >= GEMINI_MAX_RETRIES) {
                break;
            }
            $message = strtolower($e->getMessage());
            $backoff = GEMINI_RETRY_BACKOFF_SECONDS * (2 ** ($attempt - 1));
            if (str_contains($message, 'rate') || str_contains($message, '429') || str_contains($message, 'resource_exhausted')) {
                usleep((int) ($backoff * 2 * 1_000_000));
            } else {
                usleep((int) ($backoff * 1_000_000));
            }
        }
    }
    throw $lastError ?? new Exception('Unknown Gemini error.');
}

/** @throws Exception */
function jps_gemini_call_once(string $pngBytes, string $instruction): string
{
    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
        rawurlencode(GEMINI_IMAGE_MODEL),
        rawurlencode(GEMINI_API_KEY)
    );

    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => $instruction],
                ['inline_data' => ['mime_type' => 'image/png', 'data' => base64_encode($pngBytes)]],
            ],
        ]],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => GEMINI_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
        throw new Exception('Gemini request timed out.');
    }
    if ($response === false) {
        throw new Exception("Gemini request failed: $curlError");
    }
    if ($httpCode === 429) {
        throw new Exception('Gemini rate limit hit (429).');
    }
    if ($httpCode >= 500) {
        throw new Exception("Gemini server error ($httpCode).");
    }
    if ($httpCode !== 200) {
        $snippet = substr($response, 0, 300);
        throw new Exception("Gemini request failed with HTTP $httpCode: $snippet");
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new Exception('Gemini returned an unparsable response.');
    }

    $parts = $data['candidates'][0]['content']['parts'] ?? [];
    foreach ($parts as $part) {
        $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
        if (is_array($inline) && !empty($inline['data'])) {
            $decoded = base64_decode($inline['data'], true);
            if ($decoded !== false) {
                return $decoded;
            }
        }
    }

    throw new Exception('Gemini response did not contain an edited image.');
}

function jps_estimate_api_usage_warning(int $numImages, bool $useGemini): ?string
{
    if (!$useGemini || $numImages === 0) {
        return null;
    }
    return "This batch will send up to $numImages image edit request(s) to the Gemini API " .
        '(fewer if some are served from cache). Large batches may take a while and are subject ' .
        "to your Google AI Studio / Gemini API usage quota and billing.";
}
