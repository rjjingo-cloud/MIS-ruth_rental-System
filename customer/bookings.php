<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role('customer');
$user = auth_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $stmt = $pdo->prepare('UPDATE bookings SET status = "cancelled", responded_at = NOW() WHERE id = ? AND customer_id = ? AND status = "pending"');
    $stmt->execute([(int) $_POST['id'], $user['id']]);
    flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Booking request withdrawn.' : 'That booking can no longer be withdrawn.');
    redirect('bookings.php');
}

$page_title = 'My bookings';
include dirname(__DIR__) . '/includes/header.php';

$stmt = $pdo->prepare('SELECT b.*, r.name AS room_name, r.room_type, r.price AS room_price, r.photo AS room_photo, p.title, p.location, p.listed_for, p.id AS pid FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN properties p ON p.id = b.property_id WHERE b.customer_id = ? ORDER BY b.created_at DESC');
$stmt->execute([$user['id']]);
$bookings = $stmt->fetchAll();
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>My bookings</h1>
  <p class="muted">Track the rooms you have asked for. The office confirms or declines each request.</p>

  <?php if (!$bookings): ?>
    <div class="panel">
      <p class="muted">You have not booked any room yet. Open a property and choose an available room to get started.</p>
      <p><a class="btn btn-small" href="../properties.php">Browse properties</a></p>
    </div>
  <?php else: ?>
    <?php foreach ($bookings as $b): ?>
      <div class="panel inquiry">
        <div class="inquiry-head">
          <div class="inquiry-title">
            <img class="mini-thumb" src="<?php echo esc($b['room_photo'] ? ROOT_URL . $b['room_photo'] : ROOT_URL . 'assets/img/placeholder.svg'); ?>" alt="">
            <div>
              <b><a href="../property.php?id=<?php echo (int) $b['pid']; ?>"><?php echo esc($b['room_name']); ?></a></b>
              <p class="muted"><?php echo esc($b['title']); ?> &middot; <?php echo esc($b['location']); ?></p>
              <p class="price"><?php echo esc(money($b['room_price'])); ?><?php echo $b['listed_for'] === 'rent' ? ' / month' : ''; ?></p>
            </div>
          </div>
          <div class="inquiry-side">
            <?php echo status_badge($b['status']); ?>
            <small class="muted"><?php echo esc(friendly_date($b['created_at'])); ?></small>
          </div>
        </div>
        <?php if ($b['move_in']): ?>
          <p class="muted small">Preferred move-in: <b><?php echo esc(friendly_date($b['move_in'])); ?></b></p>
        <?php endif; ?>
        <p class="note"><b>Your note:</b> <?php echo nl2br(esc($b['note'])); ?></p>
        <?php if ($b['reply']): ?>
          <p class="note note-reply"><b>Office reply:</b> <?php echo nl2br(esc($b['reply'])); ?></p>
        <?php endif; ?>

        <?php if ($b['status'] === 'approved'): ?>
          <p class="accepted-note">You are now an accepted customer for <b><?php echo esc($b['room_name']); ?></b>. Our office will contact you about the next steps.</p>
        <?php elseif ($b['status'] === 'pending'): ?>
          <p class="muted small">Waiting for confirmation from the landlord or manager.</p>
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" value="<?php echo (int) $b['id']; ?>">
            <button class="btn btn-tiny btn-danger" type="submit" data-confirm="Withdraw this booking request?">Withdraw request</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
