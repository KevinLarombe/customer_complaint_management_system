<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
$count = (int)$pdo->query("SELECT COUNT(*) FROM user_accounts WHERE role = 'support_staff'")->fetchColumn();
$error = '';
if ($count > 0) { http_response_code(403); exit('Initial support staff account already exists. Remove or disable setup_admin.php on the server.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{4,50}$/', $username)) $error = 'Username must be 4–50 characters and use letters, numbers, dot, underscore, or hyphen.';
    elseif (strlen($password) < 10) $error = 'Use a password with at least 10 characters.';
    else {
        try {
            $stmt = $pdo->prepare("INSERT INTO user_accounts (username, password_hash, role) VALUES (?, ?, 'support_staff')");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php?setup=success'); exit;
        } catch (PDOException $e) { $error = 'Could not create account. The username may already be in use.'; }
    }
}
$pageTitle = 'Initial Support Staff Setup';
require __DIR__ . '/includes/header.php';
?>
<section class="panel auth-panel"><div class="auth-brand"><span class="brand-mark">CR</span><h1>Initial admin setup</h1><p>Create the first support staff account.</p></div><p class="info-note">This page works only until the first support staff account is created. Remove this file from the server immediately after setup.</p><?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?><form method="post" class="auth-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Username<input name="username" required minlength="4" maxlength="50" autocomplete="username"></label><label>Password<input type="password" name="password" required minlength="10" autocomplete="new-password"></label><button type="submit">Create support staff account ↗</button></form></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
