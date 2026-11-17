<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role('customer');
$user = auth_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $error = null;

    if ($name === '') {
        $error = 'Your name cannot be empty.';
    } elseif ($password !== '' && strlen($password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($password !== '' && $password !== $confirm) {
        $error = 'The two passwords do not match.';
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
        flash('success', 'Your profile has been updated.');
        redirect('profile.php');
    }
}

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
  <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo esc($error); ?></div><?php endif; ?>
  <div class="panel narrow">
    <p class="muted">Email: <b><?php echo esc($profile['email']); ?></b> (cannot be changed)<br>Member since <?php echo esc(friendly_date($profile['created_at'])); ?></p>
    <form method="post" class="stack">
      <?php echo csrf_input(); ?>
      <label for="name">Full name</label>
      <input type="text" id="name" name="name" value="<?php echo esc($profile['name']); ?>" required>
      <label for="phone">Phone number</label>
      <input type="tel" id="phone" name="phone" value="<?php echo esc($profile['phone']); ?>">
      <label for="password">New password <small class="muted">(leave blank to keep current)</small></label>
      <input type="password" id="password" name="password">
      <label for="confirm">Confirm new password</label>
      <input type="password" id="confirm" name="confirm">
      <button class="btn" type="submit">Save changes</button>
    </form>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
