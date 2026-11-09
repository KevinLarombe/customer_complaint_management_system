<?php
require_once __DIR__ . '/includes/functions.php';
if (!empty($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'support_staff') header('Location: admin/dashboard.php');
    elseif (($_SESSION['role'] ?? '') === 'technician') header('Location: technician/dashboard.php');
    else { $_SESSION = []; session_destroy(); header('Location: login.php'); }
} else header('Location: login.php');
exit;
