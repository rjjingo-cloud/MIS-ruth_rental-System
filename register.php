<?php
require_once __DIR__ . '/config/db.php';

$next = safe_next();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $error = null;

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in all the required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That email address does not look right.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
        $error = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number and a symbol.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    }

    if (!$error) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with that email already exists. Try logging in instead.';
        }
    }

    if (!$error) {
        session_regenerate_id(true);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, "customer")');
        $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
        $id = (int) $pdo->lastInsertId();
        $_SESSION['user'] = ['id' => $id, 'name' => $name, 'role' => 'customer', 'email' => $email];
        flash('success', 'Your account is ready. Welcome to Ruth Estates!');
        redirect($next !== '' ? $next : ROOT_URL . 'customer/dashboard.php');
    }
}

$page_title = 'Register';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
  <h1>Create your account</h1>
  <p class="muted">Customers can request viewings and chat with our office. Staff accounts are created by administration.</p>
  <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo esc($error); ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="next" value="<?php echo esc($next); ?>">
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" value="<?php echo esc($_POST['name'] ?? ''); ?>" required>
    <label for="email">Email address</label>
    <input type="email" id="email" name="email" value="<?php echo esc($_POST['email'] ?? ''); ?>" required>
    <label for="phone">Phone number</label>
    <input type="tel" id="phone" name="phone" value="<?php echo esc($_POST['phone'] ?? ''); ?>" placeholder="e.g. 0712 345 678">
    <label for="password">Password</label>
    <div class="password-field">
      <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
      <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
        <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
      </button>
    </div>
    <ul class="password-reqs" data-password-reqs>
      <li data-req="length">At least 8 characters</li>
      <li data-req="upper">One uppercase letter (A-Z)</li>
      <li data-req="lower">One lowercase letter (a-z)</li>
      <li data-req="digit">One number (0-9)</li>
      <li data-req="symbol">One symbol (e.g. ! @ # $)</li>
    </ul>
    <div class="password-strength">
      <div class="password-strength-bar"><div class="password-strength-fill" data-password-strength-fill></div></div>
      <span class="password-strength-text" data-password-strength-text>Enter a password to check its strength.</span>
    </div>
    <label for="confirm">Confirm password</label>
    <div class="password-field">
      <input type="password" id="confirm" name="confirm" required minlength="8" autocomplete="new-password">
      <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
        <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
      </button>
    </div>
    <button class="btn btn-full" type="submit">Create account</button>
  </form>
  <p class="muted center">Already registered? <a href="login.php<?php echo $next !== '' ? '?next=' . urlencode($next) : ''; ?>">Log in</a></p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
