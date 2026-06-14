<?php
$scriptDirectory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
$basePath = $scriptDirectory === '/' || $scriptDirectory === '.' ? '' : rtrim($scriptDirectory, '/');
header('Location: ' . $basePath . '/frontend/index.php');
exit;
