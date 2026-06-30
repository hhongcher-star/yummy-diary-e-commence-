<?php
// ç®¡ç†å‘˜ç™»å½•é¡µï¼šéªŒè¯è´¦å·å¯†ç ï¼Œç™»å½•æˆåŠŸåŽå»ºç«‹åŽå° sessionã€‚
session_start();

require __DIR__ . '/../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT id, username, password_hash
         FROM admins
         WHERE username = ?
         LIMIT 1"
    );

    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];

        header("Location: dashboard.php");
        exit;
    }

    $error = "âŒ ç”¨æˆ·åæˆ–å¯†ç é”™è¯¯";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>åŽå°ç™»å½• - Yummy Diary</title>

<style>
<?php include __DIR__ . '/assets/css/login.css'; ?>
</style>
</head>

<body>

<div class="login-page">
    <section class="login-brand">
        <div class="logo-badge">ðŸª</div>

        <h1>Yummy Diary<br>Admin Panel</h1>

        <p>
            ç®¡ç†å•†å“ã€åº“å­˜ã€è®¢å•å’Œçƒ­é”€å±•ç¤ºã€‚
            ç™»å½•åŽå°åŽå¯ä»¥æŸ¥çœ‹é”€å”®æ•°æ®å¹¶ç»´æŠ¤åº—é“ºå†…å®¹ã€‚
        </p>

        <div class="brand-pills">
            <span>å•†å“ç®¡ç†</span>
            <span>åº“å­˜è¿½è¸ª</span>
            <span>è®¢å•è®°å½•</span>
            <span>çƒ­é”€æŽ’åº</span>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-box">
            <h2>åŽå°ç™»å½•</h2>
            <p class="subtitle">è¯·è¾“å…¥ç®¡ç†å‘˜è´¦å·å’Œå¯†ç ç»§ç»­ã€‚</p>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label>ç”¨æˆ·å</label>
                    <input type="text"
                           name="username"
                           placeholder="è¯·è¾“å…¥ç”¨æˆ·å"
                           autocomplete="username"
                           required>
                </div>

                <div class="form-group">
                    <label>å¯†ç </label>
                    <input type="password"
                           name="password"
                           placeholder="è¯·è¾“å…¥å¯†ç "
                           autocomplete="current-password"
                           required>
                </div>

                <button type="submit" class="login-btn">ç™»å½•åŽå°</button>
            </form>

            <div class="login-footer">
                Â© <?= date('Y') ?> Yummy Diary Admin
            </div>
        </div>
    </section>
</div>

</body>
</html>

