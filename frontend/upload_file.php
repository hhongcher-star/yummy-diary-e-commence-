<?php
require __DIR__ . '/../config.php';

$file = trim(str_replace('\\', '/', (string)($_GET['file'] ?? '')), '/');
if ($file === '' || str_contains($file, '/') || str_contains($file, '..')) {
    http_response_code(404);
    exit;
}

$path = productUploadStoragePath($file);
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');
readfile($path);
