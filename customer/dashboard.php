<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role('customer');
$user = auth_user();

$stats = [];
$stmt = $pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE customer_id = ?');
$stmt->execute([$user['id']]);
$stats['total'] = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE customer_id = ? AND status = "pending"');
$stmt->execute([$user['id']]);
$stats['pending'] = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE customer_id = ? AND status = "approved"');
$stmt->execute([$user['id']]);
$stats['approved'] = (int) $stmt->fetchColumn();

$stats['unread'] = unread_total();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE customer_id = ?');
$stmt->execute([$user['id']]);
$stats['bookings'] = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status = "pending"');
$stmt->execute([$user['id']]);
$stats['bookings_pending'] = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE customer_id = ? AND status = "pending"');
$stmt->execute([$user['id']]);
$stats['outstanding'] = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_requests WHERE customer_id = ? AND status IN ("open", "in_progress")');
$stmt->execute([$user['id']]);
$stats['maintenance'] = (int) $stmt->fetchColumn();

$recent = $pdo->prepare('SELECT i.*, p.title, p.location FROM inquiries i JOIN properties p ON p.id = i.property_id WHERE i.customer_id = ? ORDER BY i.created_at DESC LIMIT 5');
$recent->execute([$user['id']]);
$recent = $recent->fetchAll();

$recentBookings = $pdo->prepare('SELECT b.*, r.name AS room_name, p.title FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN properties p ON p.id = b.property_id WHERE b.customer_id = ? ORDER BY b.created_at DESC LIMIT 3');
$recentBookings->execute([$user['id']]);
$recentBookings = $recentBookings->fetchAll();

$page_title = 'My dashboard';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>Hello, <?php echo esc($user['name']); ?></h1>
  <p class="muted">Here is a quick look at your activity with us.</p>

  <div class="stat-grid">
    <div class="stat"><span><?php echo $stats['total']; ?></span>Viewing requests total</div>
    <div class="stat"><span><?php echo $stats['pending']; ?></span>Still pending</div>
    <div class="stat"><span><?php echo $stats['approved']; ?></span>Approved</div>
    <div class="stat"><span><?php echo $stats['unread']; ?></span>Unread messages</div>
    <div class="stat"><span><?php echo $stats['bookings']; ?></span>Room bookings</div>
    <div class="stat"><span><?php echo $stats['bookings_pending']; ?></span>Bookings awaiting confirmation</div>
    <div class="stat"><span><?php echo esc(money($stats['outstanding'])); ?></span>Outstanding payments</div>
    <div class="stat"><span><?php echo $stats['maintenance']; ?></span>Open maintenance requests</div>
  </div>

  <div class="panel">
    <h2>Latest activity</h2>
    <?php if (!$recent): ?>
      <p class="muted">You have not requested any viewings yet. <a href="../properties.php">Browse properties</a> to get started.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Property</th><th>Requested</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recent as $i): ?>
          <tr>
            <td><?php echo esc($i['title']); ?><br><small class="muted"><?php echo esc($i['location']); ?></small></td>
            <td><?php echo esc(friendly_date($i['created_at'])); ?></td>
            <td><?php echo status_badge($i['status']); ?></td>
            <td><a class="btn btn-tiny btn-outline" href="inquiries.php">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h2>My room bookings</h2>
      <a class="btn btn-small btn-outline" href="bookings.php">View all</a>
    </div>
    <?php if (!$recentBookings): ?>
      <p class="muted">No room bookings yet. Open a property and choose an available room.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Room</th><th>Property</th><th>Move-in</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentBookings as $b): ?>
          <tr>
            <td><b><?php echo esc($b['room_name']); ?></b></td>
            <td><?php echo esc($b['title']); ?></td>
            <td><?php echo esc(friendly_date($b['move_in'])); ?></td>
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
