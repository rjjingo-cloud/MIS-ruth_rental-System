<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role('landlord');
$me = auth_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        $name = trim($_POST['new_name'] ?? '');
        $email = trim($_POST['new_email'] ?? '');
        $role = ($_POST['new_role'] ?? 'customer') === 'manager' ? 'manager' : 'customer';
        $password = $_POST['new_password'] ?? '';
        $error = null;

        if ($name === '' || $email === '' || $password === '') {
            $error = 'Name, email and password are all required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'That email address does not look right.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'That email is already in use.';
            }
        }
        if (!$error) {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            flash('success', ucfirst($role) . ' account created for ' . $name . '.');
            redirect('users.php?role=' . $role);
        }
        flash('error', $error);
        redirect('users.php');
    }

    $target = null;
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
    }
    $allowed = $target && $target['id'] !== (int) $me['id'] && in_array($target['role'], ['customer', 'manager'], true);
    if (!$allowed) {
        flash('error', 'You cannot manage that account.');
        redirect('users.php');
    }

    if ($action === 'toggle') {
        $new = $target['status'] === 'active' ? 'blocked' : 'active';
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $id]);
        flash('success', $target['name'] . ' is now ' . $new . '.');
    } elseif ($action === 'reset') {
        $password = trim($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            flash('error', 'New password must be at least 6 characters.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            flash('success', 'Password changed for ' . $target['name'] . '.');
        }
    }
    redirect('users.php');
}

$roleFilter = $_GET['role'] ?? '';
$sql = 'SELECT u.*, (SELECT COUNT(*) FROM properties p WHERE p.manager_id = u.id) AS listed FROM users u';
if (in_array($roleFilter, ['customer', 'manager', 'landlord'], true)) {
    $sql .= ' WHERE u.role = :role';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':role' => $roleFilter]);
} else {
    $stmt = $pdo->query($sql . ' ORDER BY u.created_at DESC');
}
$users = $stmt->fetchAll();

$page_title = 'People';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>People</h1>
  <p class="muted">Create manager or customer accounts, block anyone who misuses the system and hand out new passwords.</p>

  <div class="panel">
    <h2>Add a new account</h2>
    <form method="post" class="field-row field-row-4">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="create">
      <div><label for="new_name">Full name</label><input type="text" id="new_name" name="new_name" required></div>
      <div><label for="new_email">Email</label><input type="email" id="new_email" name="new_email" required></div>
      <div>
        <label for="new_role">Role</label>
        <select id="new_role" name="new_role">
          <option value="customer">Customer</option>
          <option value="manager">Manager (office admin)</option>
        </select>
      </div>
      <div><label for="new_password">Password</label><input type="text" id="new_password" name="new_password" required minlength="6"></div>
      <button class="btn" type="submit">Create account</button>
    </form>
  </div>

  <div class="panel-head">
    <h2>All accounts</h2>
    <form method="get" class="filter-bar slim">
      <select name="role" onchange="this.form.submit()">
        <option value="">Everyone</option>
        <option value="manager"<?php echo $roleFilter === 'manager' ? ' selected' : ''; ?>>Managers</option>
        <option value="customer"<?php echo $roleFilter === 'customer' ? ' selected' : ''; ?>>Customers</option>
      </select>
    </form>
  </div>

  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php if (!$users): ?>
      <tr><td colspan="7" class="muted center">No accounts found.</td></tr>
    <?php endif; ?>
    <?php foreach ($users as $u): ?>
      <?php $isMe = (int) $u['id'] === (int) $me['id']; ?>
      <tr>
        <td><b><?php echo esc($u['name']); ?></b><?php echo $isMe ? ' <small class="muted">(you)</small>' : ''; ?></td>
        <td class="muted"><?php echo esc($u['email']); ?></td>
        <td class="muted"><?php echo esc($u['phone'] ?: '-'); ?></td>
        <td><?php echo status_badge($u['role']); ?></td>
        <td><?php echo status_badge($u['status']); ?></td>
        <td class="muted"><?php echo esc(friendly_date($u['created_at'])); ?></td>
        <td class="cell-actions">
          <?php if (!$isMe && $u['role'] !== 'landlord'): ?>
            <form class="inline-actions" method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
              <button class="btn btn-tiny <?php echo $u['status'] === 'active' ? 'btn-danger' : 'btn-outline'; ?>" name="action" value="toggle" type="submit"
                      data-confirm="<?php echo $u['status'] === 'active' ? 'Block this account? They will no longer be able to log in.' : 'Unblock this account?'; ?>">
                <?php echo $u['status'] === 'active' ? 'Block' : 'Unblock'; ?>
              </button>
              <input class="input-tiny" type="password" name="password" placeholder="New password" minlength="6">
              <button class="btn btn-tiny btn-outline" name="action" value="reset" type="submit"
                      data-confirm="Replace this user's password?">Reset</button>
            </form>
          <?php elseif ($isMe): ?>
            <span class="muted">-</span>
          <?php else: ?>
            <span class="muted">Protected</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
