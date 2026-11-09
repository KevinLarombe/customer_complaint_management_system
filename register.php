<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
$error = '';
$techs = $pdo->query('SELECT technician_id, technician_name FROM technicians ORDER BY technician_name')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $technicianId = (string)($_POST['technician_id'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{4,50}$/', $username)) $error = 'Username must be 4–50 characters and use letters, numbers, dot, underscore, or hyphen.';
    elseif (strlen($password) < 10) $error = 'Password must contain at least 10 characters.';
    elseif (!in_array($technicianId, array_column($techs, 'technician_id'), true)) $error = 'Select a valid technician record.';
    else {
        try {
            $stmt = $pdo->prepare("INSERT INTO user_accounts (username, password_hash, role, technician_id) VALUES (?, ?, 'technician', ?)");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $technicianId]);
            header('Location: login.php?registered=success'); exit;
        } catch (PDOException $e) { $error = 'Registration failed. Username or technician account may already be registered.'; }
    }
}
$pageTitle = 'Technician Registration';
require __DIR__ . '/includes/header.php';
?>
<section class="panel auth-panel"><div class="auth-brand"><span class="brand-mark">CR</span><h1>Technician registration</h1><p>Create an account for your assigned technician record.</p></div>
<p class="info-note">Registration is available for technicians only. Support staff accounts are created through the one-time setup process.</p>
<?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
<?php if (!$techs): ?><p class="form-error">No technician records exist. Ask support staff to add technician records first.</p><?php else: ?>
<form method="post" class="auth-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Username<input name="username" required minlength="4" maxlength="50" autocomplete="username" placeholder="Choose a username"></label><label>Password<input type="password" name="password" required minlength="10" autocomplete="new-password" placeholder="At least 10 characters"></label><label>Technician record<select name="technician_id" required><option value="">Choose your technician record</option><?php foreach ($techs as $tech): ?><option value="<?= e($tech['technician_id']) ?>"><?= e($tech['technician_id'] . ' — ' . $tech['technician_name']) ?></option><?php endforeach; ?></select></label><button type="submit">Create technician account ↗</button></form><?php endif; ?>
<p class="auth-links"><a href="login.php">← Back to sign in</a></p></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
