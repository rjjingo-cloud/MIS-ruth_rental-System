<?php
$user = auth_user();
$page_title = $page_title ?? SITE_NAME;
$unread = unread_total();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($page_title); ?> | <?php echo SITE_NAME; ?></title>
<link rel="stylesheet" href="<?php echo ROOT_URL; ?>assets/css/style.css">
<link rel="icon" type="image/svg+xml" href="<?php echo ROOT_URL; ?>assets/img/logo.svg">
</head>
<body>
<nav class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="<?php echo ROOT_URL; ?>index.php">Ruth<span>.</span>Estates</a>
    <div class="nav-links" id="navLinks">
      <a href="<?php echo ROOT_URL; ?>index.php">Home</a>
      <a href="<?php echo ROOT_URL; ?>properties.php">Properties</a>
      <?php if ($user): ?>
        <a href="<?php echo dashboard_url(); ?>">Dashboard</a>
        <a href="<?php echo messages_url(); ?>" class="nav-msg">Messages<?php echo $unread ? ' <span class="pill">' . $unread . '</span>' : ''; ?></a>
        <span class="nav-user"><?php echo esc(explode(' ', $user['name'])[0]); ?></span>
        <a href="<?php echo ROOT_URL; ?>logout.php">Logout</a>
      <?php else: ?>
        <a href="<?php echo ROOT_URL; ?>login.php">Login</a>
        <a href="<?php echo ROOT_URL; ?>register.php" class="btn btn-small">Get started</a>
      <?php endif; ?>
    </div>
    <button class="nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false" aria-controls="navLinks">&#9776;</button>
  </div>
</nav>
<div class="container main-area">
<?php
$flashes = get_flashes();
if ($flashes) {
    foreach ($flashes as $f) {
        echo '<div class="alert alert-' . esc($f['type']) . '">' . esc($f['text']) . '</div>';
    }
}
?>
