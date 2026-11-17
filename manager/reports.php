<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);
$user = auth_user();

$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$fromDate = ($from !== '' && strtotime($from)) ? date('Y-m-d', strtotime($from)) : '';
$toDate = ($to !== '' && strtotime($to)) ? date('Y-m-d', strtotime($to)) : '';

$pWhere = [];
$pParams = [];
if ($fromDate !== '') {
    $pWhere[] = 'DATE(pay.created_at) >= ?';
    $pParams[] = $fromDate;
}
if ($toDate !== '') {
    $pWhere[] = 'DATE(pay.created_at) <= ?';
    $pParams[] = $toDate;
}
$pWhereSql = $pWhere ? ' WHERE ' . implode(' AND ', $pWhere) : '';

$bWhere = [];
$bParams = [];
if ($fromDate !== '') {
    $bWhere[] = 'DATE(b.created_at) >= ?';
    $bParams[] = $fromDate;
}
if ($toDate !== '') {
    $bWhere[] = 'DATE(b.created_at) <= ?';
    $bParams[] = $toDate;
}
$bWhereSql = $bWhere ? ' WHERE ' . implode(' AND ', $bWhere) : '';

$iWhere = [];
$iParams = [];
if ($fromDate !== '') {
    $iWhere[] = 'DATE(i.created_at) >= ?';
    $iParams[] = $fromDate;
}
if ($toDate !== '') {
    $iWhere[] = 'DATE(i.created_at) <= ?';
    $iParams[] = $toDate;
}
$iWhereSql = $iWhere ? ' WHERE ' . implode(' AND ', $iWhere) : '';

$mWhere = [];
$mParams = [];
if ($fromDate !== '') {
    $mWhere[] = 'DATE(m.created_at) >= ?';
    $mParams[] = $fromDate;
}
if ($toDate !== '') {
    $mWhere[] = 'DATE(m.created_at) <= ?';
    $mParams[] = $toDate;
}
$mWhereSql = $mWhere ? ' WHERE ' . implode(' AND ', $mWhere) : '';

$stmt = $pdo->prepare('SELECT
    COALESCE(SUM(CASE WHEN pay.status = "paid" THEN pay.amount ELSE 0 END), 0) AS collected,
    COALESCE(SUM(CASE WHEN pay.status = "pending" THEN pay.amount ELSE 0 END), 0) AS outstanding,
    COUNT(*) AS invoices
    FROM payments pay' . $pWhereSql);
$stmt->execute($pParams);
$paySummary = $stmt->fetch();

$stmt = $pdo->prepare('SELECT
    COUNT(*) AS total,
    COALESCE(SUM(b.status = "approved"), 0) AS approved,
    COALESCE(SUM(b.status = "pending"), 0) AS pending,
    COALESCE(SUM(b.status = "rejected"), 0) AS rejected,
    COALESCE(SUM(b.status = "cancelled"), 0) AS cancelled
    FROM bookings b' . $bWhereSql);
$stmt->execute($bParams);
$bookingSummary = $stmt->fetch();

$stmt = $pdo->prepare('SELECT
    COUNT(*) AS total,
    COALESCE(SUM(i.status = "approved"), 0) AS approved,
    COALESCE(SUM(i.status = "pending"), 0) AS pending,
    COALESCE(SUM(i.status = "rejected"), 0) AS rejected
    FROM inquiries i' . $iWhereSql);
$stmt->execute($iParams);
$inquirySummary = $stmt->fetch();

$stmt = $pdo->prepare('SELECT
    COUNT(*) AS total,
    COALESCE(SUM(m.status = "open"), 0) AS open,
    COALESCE(SUM(m.status = "in_progress"), 0) AS in_progress,
    COALESCE(SUM(m.status = "resolved"), 0) AS resolved,
    COALESCE(SUM(m.status = "closed"), 0) AS closed
    FROM maintenance_requests m' . $mWhereSql);
$stmt->execute($mParams);
$maintenanceSummary = $stmt->fetch();

$propertyCount = (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
$customerCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer" AND status = "active"')->fetchColumn();
$roomSummary = $pdo->query('SELECT
    COUNT(*) AS rooms,
    COALESCE(SUM(status = "occupied"), 0) AS occupied,
    COALESCE(SUM(status = "available"), 0) AS available
    FROM rooms')->fetch();
$occupancyRate = (int) $roomSummary['rooms'] > 0 ? round(((int) $roomSummary['occupied'] / (int) $roomSummary['rooms']) * 100) : 0;

$stmt = $pdo->prepare('SELECT
    DATE_FORMAT(COALESCE(pay.paid_at, pay.created_at), "%Y-%m") AS ym,
    COALESCE(SUM(CASE WHEN pay.status = "paid" THEN pay.amount ELSE 0 END), 0) AS collected,
    COALESCE(SUM(CASE WHEN pay.status = "pending" THEN pay.amount ELSE 0 END), 0) AS outstanding,
    COUNT(*) AS invoices
    FROM payments pay' . $pWhereSql . '
    GROUP BY ym ORDER BY ym DESC LIMIT 12');
$stmt->execute($pParams);
$monthly = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT
    COALESCE(p.title, "Manual payments") AS title,
    COALESCE(SUM(CASE WHEN pay.status = "paid" THEN pay.amount ELSE 0 END), 0) AS collected,
    COALESCE(SUM(CASE WHEN pay.status = "pending" THEN pay.amount ELSE 0 END), 0) AS outstanding,
    COUNT(*) AS invoices
    FROM payments pay
    LEFT JOIN properties p ON p.id = pay.property_id' . $pWhereSql . '
    GROUP BY pay.property_id, p.title
    ORDER BY collected DESC, invoices DESC LIMIT 10');
$stmt->execute($pParams);
$byProperty = $stmt->fetchAll();

$occupancy = $pdo->query('SELECT
    p.id, p.title, p.location,
    COUNT(r.id) AS rooms,
    COALESCE(SUM(r.status = "occupied"), 0) AS occupied,
    COALESCE(SUM(r.status = "available"), 0) AS available
    FROM properties p
    LEFT JOIN rooms r ON r.property_id = p.id
    GROUP BY p.id
    ORDER BY p.title')->fetchAll();

$managers = $pdo->query('SELECT
    u.name,
    COUNT(DISTINCT p.id) AS properties,
    COALESCE(SUM(p.status = "available"), 0) AS available
    FROM users u
    LEFT JOIN properties p ON p.manager_id = u.id
    WHERE u.role = "manager"
    GROUP BY u.id
    ORDER BY properties DESC, u.name')->fetchAll();

$page_title = 'Reports';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <div class="panel-head">
    <h1>Reports</h1>
    <button class="btn btn-small btn-outline" type="button" onclick="window.print()">Print report</button>
  </div>
  <p class="muted">Agency performance, payments, bookings and occupancy. Leave the dates blank to include everything.</p>

  <form class="field-row" method="get">
    <div><label for="from">From</label><input type="date" id="from" name="from" value="<?php echo esc($fromDate); ?>"></div>
    <div><label for="to">To</label><input type="date" id="to" name="to" value="<?php echo esc($toDate); ?>"></div>
    <button class="btn" type="submit">Apply</button>
    <?php if ($fromDate !== '' || $toDate !== ''): ?><a class="muted" href="reports.php">clear</a><?php endif; ?>
  </form>

  <div class="stat-grid">
    <div class="stat"><span><?php echo esc(money($paySummary['collected'])); ?></span>Payments collected</div>
    <div class="stat"><span><?php echo esc(money($paySummary['outstanding'])); ?></span>Outstanding</div>
    <div class="stat"><span><?php echo (int) $paySummary['invoices']; ?></span>Invoices</div>
    <div class="stat"><span><?php echo $occupancyRate; ?>%</span>Occupancy rate</div>
    <div class="stat"><span><?php echo (int) $bookingSummary['approved']; ?></span>Approved bookings</div>
    <div class="stat"><span><?php echo (int) $bookingSummary['pending']; ?></span>Pending bookings</div>
    <div class="stat"><span><?php echo (int) $maintenanceSummary['open'] + (int) $maintenanceSummary['in_progress']; ?></span>Open maintenance jobs</div>
    <div class="stat"><span><?php echo $propertyCount; ?></span>Properties</div>
    <div class="stat"><span><?php echo $customerCount; ?></span>Active customers</div>
  </div>

  <div class="panel">
    <h2>Payments by month</h2>
    <?php if (!$monthly): ?>
      <p class="muted">No payments in this period.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Month</th><th>Collected</th><th>Outstanding</th><th>Invoices</th></tr></thead>
        <tbody>
        <?php foreach ($monthly as $m): ?>
          <tr>
            <td><?php echo esc(date('F Y', strtotime($m['ym'] . '-01'))); ?></td>
            <td><?php echo esc(money($m['collected'])); ?></td>
            <td><?php echo esc(money($m['outstanding'])); ?></td>
            <td><?php echo (int) $m['invoices']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Revenue by property</h2>
    <?php if (!$byProperty): ?>
      <p class="muted">No payments in this period.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Property</th><th>Collected</th><th>Outstanding</th><th>Invoices</th></tr></thead>
        <tbody>
        <?php foreach ($byProperty as $row): ?>
          <tr>
            <td><?php echo esc($row['title']); ?></td>
            <td><?php echo esc(money($row['collected'])); ?></td>
            <td><?php echo esc(money($row['outstanding'])); ?></td>
            <td><?php echo (int) $row['invoices']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Bookings &amp; viewing requests</h2>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Item</th><th>Total</th><th>Approved</th><th>Pending</th><th>Rejected / cancelled</th></tr></thead>
      <tbody>
        <tr>
          <td><b>Room bookings</b></td>
          <td><?php echo (int) $bookingSummary['total']; ?></td>
          <td><?php echo (int) $bookingSummary['approved']; ?></td>
          <td><?php echo (int) $bookingSummary['pending']; ?></td>
          <td><?php echo (int) $bookingSummary['rejected'] + (int) $bookingSummary['cancelled']; ?></td>
        </tr>
        <tr>
          <td><b>Viewing requests</b></td>
          <td><?php echo (int) $inquirySummary['total']; ?></td>
          <td><?php echo (int) $inquirySummary['approved']; ?></td>
          <td><?php echo (int) $inquirySummary['pending']; ?></td>
          <td><?php echo (int) $inquirySummary['rejected']; ?></td>
        </tr>
      </tbody>
    </table>
    </div>
  </div>

  <div class="panel">
    <h2>Maintenance requests</h2>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Total</th><th>Open</th><th>In progress</th><th>Resolved</th><th>Closed</th></tr></thead>
      <tbody>
        <tr>
          <td><?php echo (int) $maintenanceSummary['total']; ?></td>
          <td><?php echo (int) $maintenanceSummary['open']; ?></td>
          <td><?php echo (int) $maintenanceSummary['in_progress']; ?></td>
          <td><?php echo (int) $maintenanceSummary['resolved']; ?></td>
          <td><?php echo (int) $maintenanceSummary['closed']; ?></td>
        </tr>
      </tbody>
    </table>
    </div>
  </div>

  <div class="panel">
    <h2>Occupancy by property</h2>
    <?php if (!$occupancy): ?>
      <p class="muted">No properties yet.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Property</th><th>Rooms</th><th>Occupied</th><th>Available</th><th>Occupancy</th></tr></thead>
        <tbody>
        <?php foreach ($occupancy as $row): ?>
          <?php $rate = (int) $row['rooms'] > 0 ? round(((int) $row['occupied'] / (int) $row['rooms']) * 100) : 0; ?>
          <tr>
            <td><b><?php echo esc($row['title']); ?></b><br><small class="muted"><?php echo esc($row['location']); ?></small></td>
            <td><?php echo (int) $row['rooms']; ?></td>
            <td><?php echo (int) $row['occupied']; ?></td>
            <td><?php echo (int) $row['available']; ?></td>
            <td><?php echo $rate; ?>%</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Manager listing report</h2>
    <?php if (!$managers): ?>
      <p class="muted">No managers yet.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Manager</th><th>Properties listed</th><th>Currently available</th></tr></thead>
        <tbody>
        <?php foreach ($managers as $m): ?>
          <tr>
            <td><?php echo esc($m['name']); ?></td>
            <td><?php echo (int) $m['properties']; ?></td>
            <td><?php echo (int) $m['available']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
