<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('technician');
$techId = $_SESSION['technician_id'] ?? '';
if (!$techId) { http_response_code(403); exit('This account is not linked to a technician record.'); }
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ticketId = trim((string)($_POST['ticket_id'] ?? ''));
    $note = trim((string)($_POST['progress_note'] ?? ''));
    $check = $pdo->prepare('SELECT ticket_id FROM tickets WHERE ticket_id = ? AND technician_id = ?');
    $check->execute([$ticketId, $techId]);
    if (!$check->fetch()) $error = 'You can only add notes to tickets assigned to your technician account.';
    elseif ($note === '') $error = 'Progress note cannot be empty.';
    elseif (mb_strlen($note) > 5000) $error = 'Progress note must not exceed 5,000 characters.';
    else {
        $stmt = $pdo->prepare('INSERT INTO progress_notes (ticket_id, author_user_id, progress_note) VALUES (?, ?, ?)');
        $stmt->execute([$ticketId, $_SESSION['user_id'], $note]);
        $message = 'Progress note added to ' . $ticketId . '.';
    }
}
$stmt = $pdo->prepare("SELECT t.ticket_id, s.customer_name, s.service_address, ic.category_name, p.priority_name, ts.status_name, t.opened_date FROM tickets t JOIN subscribers s ON s.customer_no=t.customer_no JOIN issue_categories ic ON ic.category_id=t.category_id JOIN priorities p ON p.priority_id=t.priority_id JOIN ticket_statuses ts ON ts.status_id=t.status_id WHERE t.technician_id = ? ORDER BY t.opened_date DESC");
$stmt->execute([$techId]);
$tickets = $stmt->fetchAll();
$noteStmt = $pdo->prepare('SELECT progress_timestamp, progress_note FROM progress_notes WHERE ticket_id = ? ORDER BY progress_timestamp DESC');
$pageTitle = 'Technician Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">WORKSPACE / MY ASSIGNMENTS</span><h1>Technician workspace</h1><p>Track your assigned complaints and record troubleshooting progress.</p></div><div class="heading-meta"><span class="live-indicator"></span> <?= e($techId) ?></div></section>
<?php if ($message): ?><div class="alert success"><span class="alert-icon">✓</span><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><span class="alert-icon">!</span><?= e($error) ?></div><?php endif; ?>
<section class="stats-grid tech-stats"><article class="stat-card stat-total"><div class="stat-top"><span class="stat-label">MY ASSIGNED TICKETS</span><span class="stat-icon">▦</span></div><strong><?= count($tickets) ?></strong><span class="stat-foot">Current ticket assignments</span></article><article class="stat-card"><div class="stat-top"><span class="stat-label">OPEN / ACTIVE</span><span class="stat-icon">◦</span></div><strong><?= count(array_filter($tickets, static fn($t) => !in_array(strtolower($t['status_name']), ['closed','resolved'], true))) ?></strong><span class="stat-foot">Awaiting completion</span></article></section>
<div class="section-heading"><div><span class="eyebrow">YOUR QUEUE</span><h2>Assigned complaints</h2></div><span class="record-count"><?= count($tickets) ?> TICKETS</span></div>
<?php if (!$tickets): ?><section class="panel empty-panel"><span class="empty-icon">✓</span><h2>You're all caught up</h2><p>No tickets are currently assigned to your account.</p></section><?php endif; ?>
<div class="ticket-grid">
<?php foreach ($tickets as $ticket): ?>
  <article class="panel ticket-card">
    <div class="ticket-card-head"><div><span class="eyebrow">COMPLAINT TICKET</span><h2><?= e($ticket['ticket_id']) ?></h2></div><span class="status-pill status-<?= e(strtolower(str_replace(' ', '-', $ticket['status_name']))) ?>"><?= e($ticket['status_name']) ?></span></div>
    <h3 class="issue-title"><?= e($ticket['category_name']) ?></h3>
    <div class="ticket-details"><div><span>SUBSCRIBER</span><strong><?= e($ticket['customer_name']) ?></strong></div><div><span>SERVICE ADDRESS</span><strong><?= e($ticket['service_address']) ?></strong></div><div><span>PRIORITY</span><strong><?= e($ticket['priority_name']) ?></strong></div><div><span>OPENED</span><strong><?= e($ticket['opened_date']) ?></strong></div></div>
    <form method="post" class="progress-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="ticket_id" value="<?= e($ticket['ticket_id']) ?>"><label for="note-<?= e($ticket['ticket_id']) ?>">Add troubleshooting progress</label><textarea id="note-<?= e($ticket['ticket_id']) ?>" name="progress_note" required maxlength="5000" rows="3" placeholder="Describe the troubleshooting steps taken..."></textarea><div class="form-foot"><small>Up to 5,000 characters</small><button type="submit">Add progress <span aria-hidden="true">↗</span></button></div></form>
    <details class="history-details"><summary>View progress history</summary><?php $noteStmt->execute([$ticket['ticket_id']]); $notes = $noteStmt->fetchAll(); ?><?php if (!$notes): ?><p class="muted">No progress notes have been added yet.</p><?php else: ?><ul class="notes"><?php foreach ($notes as $note): ?><li><time><?= e($note['progress_timestamp']) ?></time><p><?= nl2br(e($note['progress_note'])) ?></p></li><?php endforeach; ?></ul><?php endif; ?></details>
  </article>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
