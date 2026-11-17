<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);
$user = auth_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $decision = ($_POST['decision'] ?? '') === 'approve' ? 'approve' : 'decline';
    $reply = trim($_POST['reply'] ?? '');

    if ($decision === 'approve') {
        $result = approve_booking($pdo, $id, $reply, $user['id']);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('bookings.php');
    }

    $stmt = $pdo->prepare('SELECT b.*, r.name AS room_name FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ?');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();

    if (!$booking || $booking['status'] !== 'pending') {
        flash('error', 'That booking request was already handled.');
        redirect('bookings.php');
    }

    $stmt = $pdo->prepare('UPDATE bookings SET status = "rejected", reply = ?, responded_at = NOW() WHERE id = ? AND status = "pending"');
    $stmt->execute([$reply, $id]);

    if (!$stmt->rowCount()) {
        flash('error', 'That booking request was already handled.');
        redirect('bookings.php');
    }

    if ($reply !== '') {
        $body = 'Regarding your booking for ' . $booking['room_name'] . ': ' . $reply;
        $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)')->execute([$user['id'], $booking['customer_id'], $body]);
    }

    flash('success', 'The booking was declined and the customer has been notified.');
    redirect('bookings.php');
}

$pending = $pdo->query('SELECT b.*, r.name AS room_name, r.room_type, r.price AS room_price, p.title, p.location, p.listed_for, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN properties p ON p.id = b.property_id JOIN users u ON u.id = b.customer_id WHERE b.status = "pending" ORDER BY b.created_at ASC')->fetchAll();
$past = $pdo->query('SELECT b.*, r.name AS room_name, p.title, u.name AS customer_name FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN properties p ON p.id = b.property_id JOIN users u ON u.id = b.customer_id WHERE b.status != "pending" ORDER BY b.responded_at DESC LIMIT 20')->fetchAll();

$page_title = 'Room bookings';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>Room bookings</h1>
  <p class="muted">Approve or decline room bookings. Approving a room marks it occupied everywhere, creates the customer's payment invoice and notifies everyone involved.</p>

  <h2 class="section-title-sm">Waiting for confirmation (<?php echo count($pending); ?>)</h2>
  <?php if (!$pending): ?>
    <div class="panel"><p class="muted">No booking requests waiting. Well done.</p></div>
  <?php else: ?>
    <?php foreach ($pending as $b): ?>
      <div class="panel inquiry">
        <div class="inquiry-head">
          <div>
            <b><?php echo esc($b['room_name']); ?></b> &middot; <?php echo esc(money($b['room_price'])); ?><?php echo $b['listed_for'] === 'rent' ? ' / month' : ''; ?>
            <p class="muted"><?php echo esc($b['title']); ?>, <?php echo esc($b['location']); ?> &middot; requested by <b><?php echo esc($b['customer_name']); ?></b> (<?php echo esc($b['customer_phone']); ?>, <?php echo esc($b['customer_email']); ?>)</p>
          </div>
          <div class="inquiry-side">
            <?php echo status_badge($b['status']); ?>
            <small class="muted"><?php echo esc(friendly_date($b['created_at'])); ?></small>
          </div>
        </div>
        <?php if ($b['move_in']): ?>
          <p class="muted small">Preferred move-in: <b><?php echo esc(friendly_date($b['move_in'])); ?></b></p>
        <?php endif; ?>
        <p class="note"><b>Customer note:</b> <?php echo nl2br(esc($b['note'])); ?></p>
        <form class="respond" method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="id" value="<?php echo (int) $b['id']; ?>">
          <textarea name="reply" rows="2" placeholder="Reply shown to the customer, e.g. Approved. Please visit the office to sign the agreement."></textarea>
          <div class="respond-actions">
            <select name="decision">
              <option value="approve">Approve booking</option>
              <option value="decline">Decline</option>
            </select>
            <button class="btn" type="submit">Send response</button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h2 class="section-title-sm">Previously handled</h2>
  <div class="panel">
    <?php if (!$past): ?>
      <p class="muted">No history yet.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Customer</th><th>Room</th><th>Property</th><th>Requested</th><th>Handled</th><th>Outcome</th></tr></thead>
        <tbody>
        <?php foreach ($past as $b): ?>
          <tr>
            <td><?php echo esc($b['customer_name']); ?></td>
            <td><?php echo esc($b['room_name']); ?></td>
            <td><?php echo esc($b['title']); ?></td>
            <td><?php echo esc(friendly_date($b['created_at'])); ?></td>
            <td><?php echo esc(friendly_date($b['responded_at'])); ?></td>
            <td><?php echo status_badge($b['status']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
