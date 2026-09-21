<?php
/**
 * Minimal dependency-free .env loader (KEY=VALUE per line, # comments,
 * optional quotes). No Composer needed — works on any plain PHP host.
 */

function jps_load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"') ||
            ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function jps_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function jps_env_int(string $key, int $default): int
{
    $value = jps_env($key);
    if ($value === null || !is_numeric($value)) {
        return $default;
    }
    return (int) $value;
}

function jps_env_float(string $key, float $default): float
{
    $value = jps_env($key);
    if ($value === null || !is_numeric($value)) {
        return $default;
    }
    return (float) $value;
}

function jps_env_bool(string $key, bool $default): bool
{
    $value = jps_env($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}
