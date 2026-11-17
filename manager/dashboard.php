<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);
$user = auth_user();

$stats = [];
$stats['properties'] = (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
$stmt = $pdo->query('SELECT COUNT(*) FROM inquiries WHERE status = "pending"');
$stats['pending'] = (int) $stmt->fetchColumn();
$stats['customers'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer"')->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
$stmt->execute([$user['id']]);
$stats['unread'] = (int) $stmt->fetchColumn();
$stats['bookings'] = (int) $pdo->query('SELECT COUNT(*) FROM bookings WHERE status = "pending"')->fetchColumn();
$stats['outstanding'] = (float) $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "pending"')->fetchColumn();
$stats['maintenance'] = (int) $pdo->query('SELECT COUNT(*) FROM maintenance_requests WHERE status IN ("open", "in_progress")')->fetchColumn();

$recent = $pdo->query('SELECT i.*, p.title, u.name AS customer_name FROM inquiries i JOIN properties p ON p.id = i.property_id JOIN users u ON u.id = i.customer_id ORDER BY i.created_at DESC LIMIT 6')->fetchAll();
$recentBookings = $pdo->query('SELECT b.*, r.name AS room_name, p.title, u.name AS customer_name FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN properties p ON p.id = b.property_id JOIN users u ON u.id = b.customer_id ORDER BY b.created_at DESC LIMIT 5')->fetchAll();

$page_title = 'Office dashboard';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1><?php echo $user['role'] === 'landlord' ? 'Landlord' : 'Office'; ?> overview</h1>
  <p class="muted"><?php echo $user['role'] === 'landlord' ? 'Everything happening across the agency at a glance.' : 'Daily operations at a glance.'; ?></p>

  <div class="stat-grid">
    <div class="stat"><span><?php echo $stats['properties']; ?></span>Properties listed</div>
    <div class="stat"><span><?php echo $stats['pending']; ?></span>Requests waiting</div>
    <div class="stat"><span><?php echo $stats['customers']; ?></span>Registered customers</div>
    <div class="stat"><span><?php echo $stats['unread']; ?></span>Unread messages</div>
    <div class="stat"><span><?php echo $stats['bookings']; ?></span>Bookings waiting</div>
    <div class="stat"><span><?php echo esc(money($stats['outstanding'])); ?></span>Outstanding payments</div>
    <div class="stat"><span><?php echo $stats['maintenance']; ?></span>Open maintenance jobs</div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h2>Latest viewing requests</h2>
      <a class="btn btn-small btn-outline" href="inquiries.php">Manage all</a>
    </div>
    <?php if (!$recent): ?>
      <p class="muted">No viewing requests yet.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Customer</th><th>Property</th><th>Requested</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $i): ?>
          <tr>
            <td><?php echo esc($i['customer_name']); ?></td>
            <td><?php echo esc($i['title']); ?></td>
            <td><?php echo esc(friendly_date($i['created_at'])); ?></td>
            <td><?php echo status_badge($i['status']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h2>Latest room bookings</h2>
      <a class="btn btn-small btn-outline" href="bookings.php">Manage all</a>
    </div>
    <?php if (!$recentBookings): ?>
      <p class="muted">No room bookings yet.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Customer</th><th>Room</th><th>Property</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentBookings as $b): ?>
          <tr>
            <td><?php echo esc($b['customer_name']); ?></td>
            <td><?php echo esc($b['room_name']); ?></td>
            <td><?php echo esc($b['title']); ?></td>
            <td><?php echo status_badge($b['status']); ?></td>
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
