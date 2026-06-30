<?php
// 后台权限保护：所有需要管理员登录的页面/API 都应先引用此文件。
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}
