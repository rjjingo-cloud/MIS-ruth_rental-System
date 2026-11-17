<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role('landlord');

$stats = [];
$stats['managers'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "manager" AND status = "active"')->fetchColumn();
$stats['customers'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer" AND status = "active"')->fetchColumn();
$stats['properties'] = (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
$stats['pending'] = (int) $pdo->query('SELECT COUNT(*) FROM inquiries WHERE status = "pending"')->fetchColumn();
$stats['bookings'] = (int) $pdo->query('SELECT COUNT(*) FROM bookings WHERE status = "pending"')->fetchColumn();
$stats['outstanding'] = (float) $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "pending"')->fetchColumn();
$stats['maintenance'] = (int) $pdo->query('SELECT COUNT(*) FROM maintenance_requests WHERE status IN ("open", "in_progress")')->fetchColumn();

$recentUsers = $pdo->query('SELECT name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll();
$topManagers = $pdo->query('SELECT u.name, COUNT(p.id) AS listed FROM users u LEFT JOIN properties p ON p.manager_id = u.id WHERE u.role = "manager" GROUP BY u.id ORDER BY listed DESC LIMIT 5')->fetchAll();

$page_title = 'Landlord dashboard';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>Agency overview</h1>
  <p class="muted">Nightly numbers, newest sign-ups and who is listing what.</p>

  <div class="stat-grid">
    <div class="stat"><span><?php echo $stats['properties']; ?></span>Properties listed</div>
    <div class="stat"><span><?php echo $stats['pending']; ?></span>Requests waiting</div>
    <div class="stat"><span><?php echo $stats['bookings']; ?></span>Bookings waiting</div>
    <div class="stat"><span><?php echo esc(money($stats['outstanding'])); ?></span>Outstanding payments</div>
    <div class="stat"><span><?php echo $stats['maintenance']; ?></span>Open maintenance jobs</div>
    <div class="stat"><span><?php echo $stats['managers']; ?></span>Active managers</div>
    <div class="stat"><span><?php echo $stats['customers']; ?></span>Active customers</div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h2>Recently joined</h2>
      <a class="btn btn-small btn-outline" href="users.php">Manage people</a>
    </div>
    <?php if (!$recentUsers): ?>
      <p class="muted">No accounts yet.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
        <tbody>
        <?php foreach ($recentUsers as $u): ?>
          <tr>
            <td><?php echo esc($u['name']); ?></td>
            <td class="muted"><?php echo esc($u['email']); ?></td>
            <td><?php echo status_badge($u['role']); ?></td>
            <td class="muted"><?php echo esc(friendly_date($u['created_at'])); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Listings handled by each manager</h2>
    <?php if (!$topManagers): ?>
      <p class="muted">No active managers yet.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Manager</th><th>Properties listed</th></tr></thead>
        <tbody>
        <?php foreach ($topManagers as $m): ?>
          <tr><td><?php echo esc($m['name']); ?></td><td><?php echo (int) $m['listed']; ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
