<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);
$user = auth_user();

$page_title = 'My profile';
include dirname(__DIR__) . '/includes/header.php';

$stmt = $pdo->prepare('SELECT name, email, phone, created_at FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>My profile</h1>
  <div class="panel narrow">
    <p class="muted">Email: <b><?php echo esc($profile['email']); ?></b><br>Joined the office on <?php echo esc(friendly_date($profile['created_at'])); ?></p>
    <p class="muted">Update your name, phone number or password from the <a href="settings.php">Settings</a> page.</p>
    <?php if ($user['role'] === 'landlord'): ?>
      <p class="muted">You can also create accounts, block users and reset passwords from the <a href="users.php">People</a> page.</p>
    <?php endif; ?>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
