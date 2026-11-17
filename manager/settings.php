<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);
$user = auth_user();
$isLandlord = $user['role'] === 'landlord';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'agency') {
        if (!$isLandlord) {
            flash('error', 'Only the landlord can change agency settings.');
            redirect('settings.php');
        }

        $address = trim($_POST['contact_address'] ?? '');
        $email = trim($_POST['contact_email'] ?? '');
        $phone = trim($_POST['contact_phone'] ?? '');
        $instructions = trim($_POST['payment_instructions'] ?? '');

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'That contact email address does not look right.');
            redirect('settings.php');
        }

        set_app_setting($pdo, 'contact_address', $address);
        set_app_setting($pdo, 'contact_email', $email);
        set_app_setting($pdo, 'contact_phone', $phone);
        set_app_setting($pdo, 'payment_instructions', $instructions);
        flash('success', 'Agency settings saved.');
        redirect('settings.php');
    }

    if ($action === 'account') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $current = $_POST['current_password'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';
        $error = null;

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if ($name === '') {
            $error = 'Your name cannot be empty.';
        } elseif ($password !== '') {
            if (!password_verify($current, $hash)) {
                $error = 'Your current password is not correct.';
            } elseif (strlen($password) < 6) {
                $error = 'New password must be at least 6 characters long.';
            } elseif ($password !== $confirm) {
                $error = 'The two passwords do not match.';
            }
        }

        if (!$error) {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ?, password_hash = ? WHERE id = ?');
                $stmt->execute([$name, $phone, password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
                $stmt->execute([$name, $phone, $user['id']]);
            }
            $_SESSION['user']['name'] = $name;
            flash('success', 'Your account has been updated.');
            redirect('settings.php');
        }

        flash('error', $error);
        redirect('settings.php');
    }
}

$stmt = $pdo->prepare('SELECT name, email, phone, role, created_at FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$page_title = 'Settings';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>Settings</h1>
  <p class="muted">Manage your own account and the contact details shown across the site.</p>

  <div class="panel narrow">
    <h2>My account</h2>
    <p class="muted">Email: <b><?php echo esc($profile['email']); ?></b><br>
      Role: <b><?php echo esc(ucfirst($profile['role'])); ?></b> &middot; Joined <?php echo esc(friendly_date($profile['created_at'])); ?></p>
    <form method="post" class="stack">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="account">
      <label for="name">Full name</label>
      <input type="text" id="name" name="name" value="<?php echo esc($profile['name']); ?>" required>
      <label for="phone">Phone number</label>
      <input type="tel" id="phone" name="phone" value="<?php echo esc($profile['phone']); ?>">
      <label for="current_password">Current password <small class="muted">(only needed to change password)</small></label>
      <input type="password" id="current_password" name="current_password" autocomplete="current-password">
      <label for="password">New password <small class="muted">(leave blank to keep current)</small></label>
      <input type="password" id="password" name="password" autocomplete="new-password">
      <label for="confirm">Confirm new password</label>
      <input type="password" id="confirm" name="confirm" autocomplete="new-password">
      <button class="btn" type="submit">Save account</button>
    </form>
  </div>

  <?php if ($isLandlord): ?>
    <div class="panel narrow">
      <h2>Agency settings</h2>
      <p class="muted">These details appear in the site footer and on the payments page.</p>
      <form method="post" class="stack">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="agency">
        <label for="contact_address">Office address</label>
        <input type="text" id="contact_address" name="contact_address" value="<?php echo esc(app_setting('contact_address')); ?>">
        <label for="contact_email">Contact email</label>
        <input type="email" id="contact_email" name="contact_email" value="<?php echo esc(app_setting('contact_email')); ?>">
        <label for="contact_phone">Contact phone</label>
        <input type="tel" id="contact_phone" name="contact_phone" value="<?php echo esc(app_setting('contact_phone')); ?>">
        <label for="payment_instructions">Payment instructions</label>
        <textarea id="payment_instructions" name="payment_instructions" rows="3"><?php echo esc(app_setting('payment_instructions')); ?></textarea>
        <button class="btn" type="submit">Save agency settings</button>
      </form>
    </div>
  <?php else: ?>
    <div class="panel narrow">
      <h2>Agency settings</h2>
      <p class="muted">Office contact details and payment instructions are maintained by the landlord. Ask them to update these if something is out of date.</p>
    </div>
  <?php endif; ?>

  <div class="panel narrow">
    <h2>System</h2>
    <p class="muted">Currency: <b><?php echo esc(CURRENCY); ?></b><br>
      Payments are created automatically when a room booking is approved, then reconciled from the Payments page.</p>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
