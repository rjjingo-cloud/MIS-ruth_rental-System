<?php
require_once __DIR__ . '/config/db.php';

$next = safe_next();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] !== 'active') {
            $error = 'This account has been disabled. Please contact our office.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $user['id'], 'name' => $user['name'], 'role' => $user['role'], 'email' => $user['email']];
            redirect($next !== '' ? $next : dashboard_url());
        }
    } else {
        $error = 'Wrong email or password, please try again.';
    }
}

$page_title = 'Login';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
  <h1>Welcome back</h1>
  <p class="muted">Log in to manage your properties or requests.</p>
  <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo esc($error); ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="next" value="<?php echo esc($next); ?>">
    <label for="email">Email address</label>
    <input type="email" id="email" name="email" required autofocus>
    <label for="password">Password</label>
    <div class="password-field">
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
        <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
      </button>
    </div>
    <button class="btn btn-full" type="submit">Log in</button>
  </form>
  <p class="muted center">New here? <a href="register.php<?php echo $next !== '' ? '?next=' . urlencode($next) : ''; ?>">Create an account</a></p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
