<?php
$unread = unread_total();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status = "pending"');
$stmt->execute([auth_user()['id']]);
$pendingBookings = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE customer_id = ? AND status = "pending"');
$stmt->execute([auth_user()['id']]);
$pendingPayments = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_requests WHERE customer_id = ? AND status IN ("open", "in_progress")');
$stmt->execute([auth_user()['id']]);
$openRequests = (int) $stmt->fetchColumn();
$links = [
    'dashboard.php' => 'Overview',
    'inquiries.php' => 'My viewing requests',
    'bookings.php' => 'My bookings',
    'payments.php' => 'Payments',
    'maintenance.php' => 'Maintenance',
    'messages.php' => 'Messages',
    'profile.php' => 'My profile',
];
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="dash-menu">
  <p class="menu-title">Customer menu</p>
  <ul>
    <?php foreach ($links as $file => $label): ?>
      <li>
        <a href="<?php echo $file; ?>"<?php echo $current === $file ? ' class="active"' : ''; ?>>
          <?php
          echo esc($label);
          if ($file === 'messages.php' && $unread) {
              echo ' <span class="pill">' . $unread . '</span>';
          }
          if ($file === 'bookings.php' && $pendingBookings) {
              echo ' <span class="pill">' . $pendingBookings . '</span>';
          }
          if ($file === 'payments.php' && $pendingPayments) {
              echo ' <span class="pill">' . $pendingPayments . '</span>';
          }
          if ($file === 'maintenance.php' && $openRequests) {
              echo ' <span class="pill">' . $openRequests . '</span>';
          }
          ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <div class="menu-user">
    <p><b><?php echo esc(auth_user()['name']); ?></b></p>
    <p class="muted">Customer</p>
  </div>
</aside>
