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

function appBasePath(): string
{
    static $basePath;
    if ($basePath !== null) {
        return $basePath;
    }

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach (['/frontend/', '/backend/'] as $marker) {
        $position = strpos($scriptName, $marker);
        if ($position !== false) {
            return $basePath = rtrim(substr($scriptName, 0, $position), '/');
        }
    }

    $directory = str_replace('\\', '/', dirname($scriptName));
    return $basePath = $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
}

function appUrl(string $path = ''): string
{
    $basePath = appBasePath();
    $path = ltrim($path, '/');
    return ($basePath !== '' ? $basePath : '') . '/' . $path;
}

function productImageUrl(?string $value): string
{
    $path = trim(str_replace('\\', '/', (string)$value));
    $path = preg_replace('#^https?://[^/]+/#i', '', $path);
    $path = preg_replace('#^/?(?:store/)?yummy-diary/#i', '', $path);
    $path = ltrim($path, '/');

    if (str_starts_with($path, 'uploads/')) {
        $path = 'frontend/' . $path;
    }

    return appUrl($path !== '' ? $path : 'images/soldout.png');
}

$appEnvironment = getenv('APP_ENV') ?: 'production';
ini_set('display_errors', $appEnvironment === 'development' ? '1' : '0');
ini_set('display_startup_errors', $appEnvironment === 'development' ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$requestPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['REQUEST_URI'] ?? ''));
$isApiRequest = str_contains($requestPath, '/api/');
if ($isApiRequest) {
    set_exception_handler(static function (Throwable $e) use ($appEnvironment): void {
        error_log($e->__toString());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'success' => false,
            'message' => $appEnvironment === 'development' ? $e->getMessage() : 'Server error',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    });
}

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_NAME') ?: '';
$username = getenv('DB_USER') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
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
    if ($isApiRequest) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    exit('Database connection failed.');
}
