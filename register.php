<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $technicianName = trim((string)($_POST['technician_name'] ?? ''));

    if (!preg_match('/^[A-Za-z0-9_.-]{4,50}$/', $username)) {
        $error = 'Username must be 4–50 characters and use letters, numbers, dot, underscore, or hyphen.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif ($technicianName === '' || mb_strlen($technicianName) > 100) {
        $error = 'Please enter a technician name (maximum 100 characters).';
    } else {
        try {
            $pdo->beginTransaction();

            // Generate the next TEC number from existing technician records.
            $ids = $pdo->query("SELECT technician_id FROM technicians")->fetchAll(PDO::FETCH_COLUMN);
            $highest = 0;
            foreach ($ids as $id) {
                if (preg_match('/^TEC-(\d+)$/', (string)$id, $match)) {
                    $highest = max($highest, (int)$match[1]);
                }
            }
            $technicianId = 'TEC-' . str_pad((string)($highest + 1), 2, '0', STR_PAD_LEFT);

            $techInsert = $pdo->prepare('INSERT INTO technicians (technician_id, technician_name) VALUES (?, ?)');
            $techInsert->execute([$technicianId, $technicianName]);

            $accountInsert = $pdo->prepare("INSERT INTO user_accounts (username, password_hash, role, technician_id) VALUES (?, ?, 'technician', ?)");
            $accountInsert->execute([$username, password_hash($password, PASSWORD_DEFAULT), $technicianId]);

            $pdo->commit();
            header('Location: login.php?registered=success');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Registration failed. The username may already be in use. Please try another username.';
        }
    }
}

$pageTitle = 'Technician Registration';
require __DIR__ . '/includes/header.php';
?>
<section class="panel auth-panel">
  <div class="auth-brand"><span class="brand-mark">CR</span><h1>Technician registration</h1><p>Create your technician record and account.</p></div>
  <p class="info-note">Your technician record will be added automatically when you create your account. Support staff accounts are created separately.</p>
  <?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="auth-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>Technician name<input name="technician_name" required maxlength="100" autocomplete="name" placeholder="Enter your full name" value="<?= e($_POST['technician_name'] ?? '') ?>"></label>
    <label>Username<input name="username" required minlength="4" maxlength="50" autocomplete="username" placeholder="Choose a username" value="<?= e($_POST['username'] ?? '') ?>"></label>
    <label>Password<input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters"></label>
    <button type="submit">Create technician account ↗</button>
  </form>
  <p class="auth-links"><a href="login.php">← Back to sign in</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
