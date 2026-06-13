<?php
function loadLocalEnvironment(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }
        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

loadLocalEnvironment(__DIR__ . '/.env');

$appEnvironment = getenv('APP_ENV') ?: 'production';
ini_set('display_errors', $appEnvironment === 'development' ? '1' : '0');
ini_set('display_startup_errors', $appEnvironment === 'development' ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$host = 'localhost';
$port = '3306';
$database = 'u751690829_yummy';
$username = 'u751690829_yummy';
$password = 'Hong63@511';
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $host,
    $port,
    $database
);

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');
}
