<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('support_staff');

$message = '';
$error = '';

function next_record_id(PDO $pdo, string $table, string $column, string $prefix): string {
    // Table and column names are fixed internal constants at each call site.
    $ids = $pdo->query("SELECT {$column} FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
    $highest = 0;
    foreach ($ids as $id) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', (string)$id, $match)) {
            $highest = max($highest, (int)$match[1]);
        }
    }
    return $prefix . '-' . str_pad((string)($highest + 1), 2, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_ticket') {
        $customerName = trim((string)($_POST['customer_name'] ?? ''));
        $serviceAddress = trim((string)($_POST['service_address'] ?? ''));
        $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $priorityId = filter_input(INPUT_POST, 'priority_id', FILTER_VALIDATE_INT);
        $technicianId = trim((string)($_POST['technician_id'] ?? ''));
        $openedDate = trim((string)($_POST['opened_date'] ?? date('Y-m-d')));

        if ($customerName === '' || mb_strlen($customerName) > 100 || $serviceAddress === '' || mb_strlen($serviceAddress) > 255) {
            $error = 'Enter a customer name and service address within the allowed length.';
        } elseif (!$categoryId || !$priorityId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $openedDate)) {
            $error = 'Please select a valid issue, priority, and opening date.';
        } else {
            $categoryCheck = $pdo->prepare('SELECT category_id FROM issue_categories WHERE category_id = ?');
            $categoryCheck->execute([$categoryId]);
            $priorityCheck = $pdo->prepare('SELECT priority_id FROM priorities WHERE priority_id = ?');
            $priorityCheck->execute([$priorityId]);
            $techCheck = $pdo->prepare('SELECT technician_id FROM technicians WHERE technician_id = ?');
            if ($technicianId !== '') $techCheck->execute([$technicianId]);

            if (!$categoryCheck->fetch() || !$priorityCheck->fetch() || ($technicianId !== '' && !$techCheck->fetch())) {
                $error = 'One of the selected issue, priority, or technician records is invalid.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Reuse an existing subscriber at the same service address.
                    $subscriberCheck = $pdo->prepare('SELECT customer_no FROM subscribers WHERE service_address = ?');
                    $subscriberCheck->execute([$serviceAddress]);
                    $customerNo = $subscriberCheck->fetchColumn();

                    if (!$customerNo) {
                        $customerNo = next_record_id($pdo, 'subscribers', 'customer_no', 'C');
                        $subscriberInsert = $pdo->prepare('INSERT INTO subscribers (customer_no, customer_name, service_address) VALUES (?, ?, ?)');
                        $subscriberInsert->execute([$customerNo, $customerName, $serviceAddress]);
                    }

                    $ticketId = next_record_id($pdo, 'tickets', 'ticket_id', 'TK');
                    $initialStatus = $technicianId === '' ? 'Open' : 'Assigned';
                    $statusStmt = $pdo->prepare('SELECT status_id FROM ticket_statuses WHERE status_name = ?');
                    $statusStmt->execute([$initialStatus]);
                    $statusId = $statusStmt->fetchColumn();
                    if (!$statusId) throw new RuntimeException('The required Open or Assigned ticket status is missing.');

                    $insert = $pdo->prepare('INSERT INTO tickets (ticket_id, customer_no, category_id, priority_id, technician_id, status_id, opened_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $insert->execute([$ticketId, $customerNo, $categoryId, $priorityId, $technicianId !== '' ? $technicianId : null, $statusId, $openedDate]);

                    $pdo->commit();
                    $message = "New complaint ticket {$ticketId} was created successfully.";
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'Could not create the ticket. Check the entered details and try again.';
                }
            }
        }
    } elseif ($action === 'update_ticket') {
        $ticketId = trim((string)($_POST['ticket_id'] ?? ''));
        $statusId = filter_input(INPUT_POST, 'status_id', FILTER_VALIDATE_INT);
        $technicianId = trim((string)($_POST['technician_id'] ?? ''));
        $summary = trim((string)($_POST['resolution_summary'] ?? ''));

        $statusCheck = $pdo->prepare('SELECT status_id, status_name FROM ticket_statuses WHERE status_id = ?');
        $statusCheck->execute([$statusId]);
        $selectedStatus = $statusCheck->fetch();
        $ticketCheck = $pdo->prepare('SELECT ticket_id FROM tickets WHERE ticket_id = ?');
        $ticketCheck->execute([$ticketId]);
        $techValid = true;
        if ($technicianId !== '') {
            $techCheck = $pdo->prepare('SELECT technician_id FROM technicians WHERE technician_id = ?');
            $techCheck->execute([$technicianId]);
            $techValid = (bool)$techCheck->fetch();
        }

        if (!$ticketCheck->fetch()) {
            $error = 'The selected ticket could not be found.';
        } elseif (!$selectedStatus || !$techValid) {
            $error = 'Please select a valid status and technician.';
        } elseif (strcasecmp($selectedStatus['status_name'], 'Closed') === 0 && $summary === '') {
            $error = 'A resolution summary is required before closing a ticket.';
        } else {
            $stmt = $pdo->prepare("UPDATE tickets SET status_id = ?, technician_id = ?, resolution_summary = CASE WHEN ? = '' THEN resolution_summary ELSE ? END WHERE ticket_id = ?");
            $stmt->execute([$statusId, $technicianId !== '' ? $technicianId : null, $summary, $summary, $ticketId]);
            $message = 'Ticket ' . $ticketId . ' was updated successfully.';
        }
    }
}

$counts = $pdo->query("SELECT ts.status_name, COUNT(t.ticket_id) AS total FROM ticket_statuses ts LEFT JOIN tickets t ON t.status_id = ts.status_id GROUP BY ts.status_id, ts.status_name ORDER BY ts.status_id")->fetchAll();
$totalTickets = array_sum(array_map(static fn($row) => (int)$row['total'], $counts));
$tickets = $pdo->query("SELECT t.ticket_id, s.customer_name, ic.category_name, p.priority_name, t.technician_id, COALESCE(te.technician_name,'Unassigned') AS technician_name, ts.status_name, t.opened_date, t.resolution_summary, t.status_id FROM tickets t JOIN subscribers s ON s.customer_no=t.customer_no JOIN issue_categories ic ON ic.category_id=t.category_id JOIN priorities p ON p.priority_id=t.priority_id LEFT JOIN technicians te ON te.technician_id=t.technician_id JOIN ticket_statuses ts ON ts.status_id=t.status_id ORDER BY t.opened_date DESC")->fetchAll();
$statuses = $pdo->query('SELECT status_id, status_name FROM ticket_statuses ORDER BY status_id')->fetchAll();
$technicians = $pdo->query('SELECT technician_id, technician_name FROM technicians ORDER BY technician_name')->fetchAll();
$categories = $pdo->query('SELECT category_id, category_name FROM issue_categories ORDER BY category_name')->fetchAll();
$priorities = $pdo->query('SELECT priority_id, priority_name FROM priorities ORDER BY priority_id')->fetchAll();

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
  <div><span class="eyebrow">WORKSPACE / OVERVIEW</span><h1>Admin dashboard</h1><p>Create complaint tickets, manage assignments, and monitor resolutions.</p></div>
  <div class="heading-meta"><span class="live-indicator"></span> SYSTEM OVERVIEW <span class="meta-divider">·</span> <?= date('M d, Y') ?></div>
</section>
<?php if ($message): ?><div class="alert success"><span class="alert-icon">✓</span><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><span class="alert-icon">!</span><?= e($error) ?></div><?php endif; ?>

<section class="panel create-ticket-panel">
  <div class="panel-heading"><div><span class="eyebrow">NEW CUSTOMER COMPLAINT</span><h2>Create a ticket</h2><p>Enter the customer and complaint details, then assign a technician.</p></div></div>
  <form method="post" class="ticket-create-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create_ticket">
    <label>Customer name<input name="customer_name" required maxlength="100" placeholder="Customer full name"></label>
    <label>Service address<input name="service_address" required maxlength="255" placeholder="Complete service address"></label>
    <label>Issue category<select name="category_id" required><option value="">Select issue</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['category_id'] ?>"><?= e($category['category_name']) ?></option><?php endforeach; ?></select></label>
    <label>Priority<select name="priority_id" required><option value="">Select priority</option><?php foreach ($priorities as $priority): ?><option value="<?= (int)$priority['priority_id'] ?>"><?= e($priority['priority_name']) ?></option><?php endforeach; ?></select></label>
    <label>Assign technician<select name="technician_id"><option value="">Leave unassigned</option><?php foreach ($technicians as $tech): ?><option value="<?= e($tech['technician_id']) ?>"><?= e($tech['technician_name']) ?></option><?php endforeach; ?></select></label>
    <label>Date opened<input type="date" name="opened_date" required value="<?= e(date('Y-m-d')) ?>"></label>
    <div class="form-actions"><button type="submit">Create complaint ticket ↗</button></div>
  </form>
</section>

<section class="stats-grid" aria-label="Ticket statistics">
  <article class="stat-card stat-total"><div class="stat-top"><span class="stat-label">ALL TICKETS</span><span class="stat-icon">▦</span></div><strong><?= $totalTickets ?></strong><span class="stat-foot">Total complaint records</span></article>
  <?php foreach ($counts as $count): ?>
    <article class="stat-card"><div class="stat-top"><span class="stat-label"><?= e(strtoupper($count['status_name'])) ?></span><span class="stat-icon">◦</span></div><strong><?= (int)$count['total'] ?></strong><span class="stat-foot">Tickets in this status</span></article>
  <?php endforeach; ?>
</section>

<section class="panel table-panel">
  <div class="panel-heading"><div><span class="eyebrow">TICKET MANAGEMENT</span><h2>Complaint tickets</h2><p>Review tickets and update their assigned technicians or status.</p></div><span class="record-count"><?= count($tickets) ?> RECORDS</span></div>
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
        <td>
          <form method="post" class="update-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_ticket">
            <input type="hidden" name="ticket_id" value="<?= e($ticket['ticket_id']) ?>">
            <label class="sr-only" for="tech-<?= e($ticket['ticket_id']) ?>">Assigned technician</label>
            <select id="tech-<?= e($ticket['ticket_id']) ?>" name="technician_id"><option value="">Unassigned</option><?php foreach ($technicians as $tech): ?><option value="<?= e($tech['technician_id']) ?>" <?= $ticket['technician_id'] === $tech['technician_id'] ? 'selected' : '' ?>><?= e($tech['technician_name']) ?></option><?php endforeach; ?></select>
            <label class="sr-only" for="status-<?= e($ticket['ticket_id']) ?>">Ticket status</label>
            <select id="status-<?= e($ticket['ticket_id']) ?>" name="status_id"><?php foreach ($statuses as $st): ?><option value="<?= (int)$st['status_id'] ?>" <?= (int)$ticket['status_id'] === (int)$st['status_id'] ? 'selected' : '' ?>><?= e($st['status_name']) ?></option><?php endforeach; ?></select>
            <input name="resolution_summary" placeholder="Resolution summary (required to close)" value="<?= e($ticket['resolution_summary']) ?>" aria-label="Resolution summary for <?= e($ticket['ticket_id']) ?>">
            <button type="submit" class="button-small">Save</button>
          </form>
        </td>
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
