<?php
$unread = unread_total();
$pendingPayments = (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE status = "pending"')->fetchColumn();
$openMaintenance = (int) $pdo->query('SELECT COUNT(*) FROM maintenance_requests WHERE status IN ("open", "in_progress")')->fetchColumn();
$links = [
    ROOT_URL . 'landlord/dashboard.php' => 'Overview',
    ROOT_URL . 'manager/properties.php' => 'Properties',
    ROOT_URL . 'manager/inquiries.php' => 'Viewing requests',
    ROOT_URL . 'manager/bookings.php' => 'Room bookings',
    ROOT_URL . 'manager/maintenance.php' => 'Maintenance',
    ROOT_URL . 'manager/payments.php' => 'Payments',
    ROOT_URL . 'manager/reports.php' => 'Reports',
    ROOT_URL . 'landlord/users.php' => 'People',
    ROOT_URL . 'manager/messages.php' => 'Messages',
    ROOT_URL . 'manager/profile.php' => 'My profile',
    ROOT_URL . 'manager/settings.php' => 'Settings',
];
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="dash-menu">
  <p class="menu-title">Landlord menu</p>
  <ul>
    <?php foreach ($links as $file => $label): ?>
      <li>
        <a href="<?php echo $file; ?>"<?php echo $current === basename($file) ? ' class="active"' : ''; ?>>
          <?php
          echo esc($label);
          if (str_ends_with($file, 'messages.php') && $unread) {
              echo ' <span class="pill">' . $unread . '</span>';
          }
          if (str_ends_with($file, 'maintenance.php') && $openMaintenance) {
              echo ' <span class="pill">' . $openMaintenance . '</span>';
          }
          if (str_ends_with($file, 'payments.php') && $pendingPayments) {
              echo ' <span class="pill">' . $pendingPayments . '</span>';
          }
          ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <div class="menu-user">
    <p><b><?php echo esc(auth_user()['name']); ?></b></p>
    <p class="muted">Landlord &middot; Super admin</p>
  </div>
</aside>
