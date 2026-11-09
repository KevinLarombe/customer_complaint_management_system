<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('support_staff');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    verify_csrf();
    $ticketId = trim((string)($_POST['ticket_id'] ?? ''));
    $statusId = filter_input(INPUT_POST, 'status_id', FILTER_VALIDATE_INT);
    $summary = trim((string)($_POST['resolution_summary'] ?? ''));
    $statusCheck = $pdo->prepare('SELECT status_id, status_name FROM ticket_statuses WHERE status_id = ?');
    $statusCheck->execute([$statusId]);
    $selectedStatus = $statusCheck->fetch();
    $ticketCheck = $pdo->prepare('SELECT ticket_id FROM tickets WHERE ticket_id = ?');
    $ticketCheck->execute([$ticketId]);
    if (!$ticketCheck->fetch()) {
        $error = 'The selected ticket could not be found.';
    } elseif (!$selectedStatus) {
        $error = 'Please select a valid ticket status.';
    } elseif (strcasecmp($selectedStatus['status_name'], 'Closed') === 0 && $summary === '') {
        $error = 'A resolution summary is required before closing a ticket.';
    } else {
        $stmt = $pdo->prepare("UPDATE tickets SET status_id = ?, resolution_summary = CASE WHEN ? = '' THEN resolution_summary ELSE ? END WHERE ticket_id = ?");
        $stmt->execute([$statusId, $summary, $summary, $ticketId]);
        $message = 'Ticket ' . $ticketId . ' was updated successfully.';
    }
}

$counts = $pdo->query("SELECT ts.status_name, COUNT(t.ticket_id) AS total FROM ticket_statuses ts LEFT JOIN tickets t ON t.status_id = ts.status_id GROUP BY ts.status_id, ts.status_name ORDER BY ts.status_id")->fetchAll();
$totalTickets = array_sum(array_map(static fn($row) => (int)$row['total'], $counts));
$tickets = $pdo->query("SELECT t.ticket_id, s.customer_name, ic.category_name, p.priority_name, COALESCE(te.technician_name,'Unassigned') AS technician_name, ts.status_name, t.opened_date, t.resolution_summary, t.status_id FROM tickets t JOIN subscribers s ON s.customer_no=t.customer_no JOIN issue_categories ic ON ic.category_id=t.category_id JOIN priorities p ON p.priority_id=t.priority_id LEFT JOIN technicians te ON te.technician_id=t.technician_id JOIN ticket_statuses ts ON ts.status_id=t.status_id ORDER BY t.opened_date DESC")->fetchAll();
$statuses = $pdo->query('SELECT status_id, status_name FROM ticket_statuses ORDER BY status_id')->fetchAll();
$pageTitle = 'Support Staff Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
  <div><span class="eyebrow">WORKSPACE / OVERVIEW</span><h1>Support dashboard</h1><p>Monitor customer complaints and keep every resolution moving.</p></div>
  <div class="heading-meta"><span class="live-indicator"></span> SYSTEM OVERVIEW <span class="meta-divider">·</span> <?= date('M d, Y') ?></div>
</section>
<?php if ($message): ?><div class="alert success"><span class="alert-icon">✓</span><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><span class="alert-icon">!</span><?= e($error) ?></div><?php endif; ?>
<section class="stats-grid" aria-label="Ticket statistics">
  <article class="stat-card stat-total"><div class="stat-top"><span class="stat-label">ALL TICKETS</span><span class="stat-icon">▦</span></div><strong><?= $totalTickets ?></strong><span class="stat-foot">Total complaint records</span></article>
  <?php foreach ($counts as $count): ?>
    <article class="stat-card"><div class="stat-top"><span class="stat-label"><?= e(strtoupper($count['status_name'])) ?></span><span class="stat-icon">◦</span></div><strong><?= (int)$count['total'] ?></strong><span class="stat-foot">Tickets in this status</span></article>
  <?php endforeach; ?>
</section>
<section class="panel table-panel">
  <div class="panel-heading"><div><span class="eyebrow">TICKET MANAGEMENT</span><h2>Complaint tickets</h2><p>Review assignments, priorities, and ticket progress.</p></div><span class="record-count"><?= count($tickets) ?> RECORDS</span></div>
  <div class="toolbar"><label class="search-box"><span aria-hidden="true">⌕</span><input id="ticketSearch" type="search" placeholder="Search ticket, subscriber, issue..." aria-label="Search complaint tickets"></label><label class="filter-box"><span>STATUS</span><select id="statusFilter"><option value="">All statuses</option><?php foreach ($statuses as $st): ?><option value="<?= e(strtolower($st['status_name'])) ?>"><?= e($st['status_name']) ?></option><?php endforeach; ?></select></label></div>
  <div class="table-wrap"><table class="data-table" id="ticketsTable">
    <thead><tr><th>TICKET</th><th>SUBSCRIBER</th><th>ISSUE / PRIORITY</th><th>TECHNICIAN</th><th>STATUS</th><th>OPENED</th><th>UPDATE TICKET</th></tr></thead>
    <tbody>
    <?php foreach ($tickets as $ticket): ?>
      <tr data-status="<?= e(strtolower($ticket['status_name'])) ?>">
        <td><strong class="ticket-id"><?= e($ticket['ticket_id']) ?></strong></td>
        <td><strong><?= e($ticket['customer_name']) ?></strong><small class="cell-sub">Customer</small></td>
        <td><strong><?= e($ticket['category_name']) ?></strong><small class="cell-sub">Priority: <?= e($ticket['priority_name']) ?></small></td>
        <td><?= e($ticket['technician_name']) ?></td>
        <td><span class="status-pill status-<?= e(strtolower(str_replace(' ', '-', $ticket['status_name']))) ?>"><?= e($ticket['status_name']) ?></span></td>
        <td><?= e($ticket['opened_date']) ?></td>
        <td><form method="post" class="update-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="ticket_id" value="<?= e($ticket['ticket_id']) ?>"><select name="status_id" aria-label="Status for <?= e($ticket['ticket_id']) ?>"><?php foreach ($statuses as $st): ?><option value="<?= (int)$st['status_id'] ?>" <?= (int)$ticket['status_id'] === (int)$st['status_id'] ? 'selected' : '' ?>><?= e($st['status_name']) ?></option><?php endforeach; ?></select><input name="resolution_summary" placeholder="Resolution summary (required to close)" value="<?= e($ticket['resolution_summary']) ?>" aria-label="Resolution summary for <?= e($ticket['ticket_id']) ?>"><button type="submit" class="button-small">Save</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table><p id="emptySearch" class="empty-state" hidden>No tickets match your search.</p></div>
</section>
<script>
(() => {
 const search = document.getElementById('ticketSearch');
 const filter = document.getElementById('statusFilter');
 const rows = [...document.querySelectorAll('#ticketsTable tbody tr')];
 const empty = document.getElementById('emptySearch');
 function applyFilters() {
   const q = search.value.trim().toLowerCase(); const status = filter.value;
   let visible = 0;
   rows.forEach(row => { const match = row.innerText.toLowerCase().includes(q) && (!status || row.dataset.status === status); row.hidden = !match; if (match) visible++; });
   empty.hidden = visible !== 0;
 }
 search.addEventListener('input', applyFilters); filter.addEventListener('change', applyFilters);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
