<?php
// 项目根入口：访问根目录时统一跳转到前台首页。
$scriptDirectory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
$basePath = $scriptDirectory === '/' || $scriptDirectory === '.' ? '' : rtrim($scriptDirectory, '/');
header('Location: ' . $basePath . '/frontend/index.php');
exit;
