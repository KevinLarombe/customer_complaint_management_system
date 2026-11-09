<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = $pdo->prepare('SELECT user_id, username, password_hash, role, technician_id FROM user_accounts WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['technician_id'] = $user['technician_id'];
        header('Location: index.php'); exit;
    }
    $error = 'Invalid username or password.';
}
$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<section class="panel auth-panel"><div class="auth-brand"><span class="brand-mark">CR</span><h1>Welcome back</h1><p>Sign in to your support workspace.</p></div>
<?php if (isset($_GET['setup'])): ?><p class="form-success">Support staff account created. Sign in below.</p><?php endif; ?>
<?php if (isset($_GET['registered'])): ?><p class="form-success">Technician account created. Sign in below.</p><?php endif; ?>
<?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="auth-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Username<input name="username" required autocomplete="username" placeholder="Enter your username"></label><label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="Enter your password"></label><button type="submit">Sign in <span aria-hidden="true">↗</span></button></form>
<p class="auth-links">Technician? <a href="register.php">Create an account</a></p></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
