<?php
/**
 * Database connection for CV Maker.
 *
 * Production values should be supplied through environment variables or a .env
 * file one directory above this public web root.
 */

function loadEnvironmentFile(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $paths = [
        dirname(__DIR__, 2) . '/.env',
        dirname(__DIR__) . '/.env',
        __DIR__ . '/../.env',
    ];

    foreach ($paths as $path) {
        if (!is_readable($path)) {
            continue;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }

        break;
    }
}

function envValue(string $key, string $default = ''): string
{
    loadEnvironmentFile();
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = envValue('DB_HOST', 'localhost');
    $name = envValue('DB_NAME');
    $user = envValue('DB_USER');
    $pass = envValue('DB_PASSWORD', envValue('DB_PASS'));

    if ($name === '' || $user === '') {
        throw new RuntimeException('Database credentials are not configured. Set DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD in your environment.');
    }

    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

$pdo = getDB();
