<?php
if (auth_user() && auth_user()['role'] === 'landlord') {
    include dirname(__DIR__) . '/landlord/menu.php';
    return;
}

$unread = unread_total();
$pendingBookings = (int) $pdo->query('SELECT COUNT(*) FROM bookings WHERE status = "pending"')->fetchColumn();
$pendingPayments = (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE status = "pending"')->fetchColumn();
$openMaintenance = (int) $pdo->query('SELECT COUNT(*) FROM maintenance_requests WHERE status IN ("open", "in_progress")')->fetchColumn();
$links = [
    'dashboard.php' => 'Overview',
    'properties.php' => 'Properties',
    'property-form.php' => '+ Add property',
    'inquiries.php' => 'Viewing requests',
    'bookings.php' => 'Room bookings',
    'maintenance.php' => 'Maintenance',
    'payments.php' => 'Payments',
    'reports.php' => 'Reports',
    'messages.php' => 'Messages',
    'profile.php' => 'My profile',
    'settings.php' => 'Settings',
];
$current = basename($_SERVER['SCRIPT_NAME']);
$active = in_array($current, ['property-form.php', 'rooms.php'], true) ? 'properties.php' : $current;
?>
<aside class="dash-menu">
  <p class="menu-title">Office menu</p>
  <ul>
    <?php foreach ($links as $file => $label): ?>
      <li>
        <a href="<?php echo $file; ?>"<?php echo $active === $file ? ' class="active"' : ''; ?>>
          <?php
          echo esc($label);
          if ($file === 'messages.php' && $unread) {
              echo ' <span class="pill">' . $unread . '</span>';
          }
          if ($file === 'bookings.php' && $pendingBookings) {
              echo ' <span class="pill">' . $pendingBookings . '</span>';
          }
          if ($file === 'maintenance.php' && $openMaintenance) {
              echo ' <span class="pill">' . $openMaintenance . '</span>';
          }
          if ($file === 'payments.php' && $pendingPayments) {
              echo ' <span class="pill">' . $pendingPayments . '</span>';
          }
          ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <div class="menu-user">
    <p><b><?php echo esc(auth_user()['name']); ?></b></p>
    <p class="muted"><?php echo esc(ucfirst(auth_user()['role'])); ?></p>
  </div>
</aside>
