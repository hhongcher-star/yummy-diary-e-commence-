-- 创建初始管理员账号：导入主数据库后执行，并在首次登录后立即修改密码。
-- Temporary administrator account for Yummy Diary.
-- Import this file after importing the main database.
-- Change the password immediately after the first successful login.

INSERT INTO `admins` (`username`, `password_hash`, `created_at`)
VALUES (
  'yummyadmin',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
  CURRENT_TIMESTAMP
)
ON DUPLICATE KEY UPDATE
  `password_hash` = VALUES(`password_hash`);
