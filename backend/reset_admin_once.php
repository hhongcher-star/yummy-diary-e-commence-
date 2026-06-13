<?php
require __DIR__ . '/../config.php';

$setupKey = 'YD-setup-2026-47';

if (!hash_equals($setupKey, (string)($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit('Not found');
}

$username = 'yummyadmin';
$password = 'YummyAdmin#2026!47';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO admins (username, password_hash, created_at)
     VALUES (?, ?, NOW())
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
);
$stmt->execute([$username, $passwordHash]);

header('Content-Type: text/plain; charset=UTF-8');
echo "Administrator reset successfully.\n";
echo "Username: {$username}\n";
echo "Password: {$password}\n";
echo "Delete backend/reset_admin_once.php from the server now.\n";
