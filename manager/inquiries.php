<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $status = ($_POST['decision'] ?? '') === 'approve' ? 'approved' : 'rejected';
    $reply = trim($_POST['reply'] ?? '');

    $stmt = $pdo->prepare('UPDATE inquiries SET status = ?, reply = ?, responded_at = NOW() WHERE id = ? AND status = "pending"');
    $stmt->execute([$status, $reply, $id]);

    if ($stmt->rowCount()) {
        $stmt = $pdo->prepare('SELECT customer_id FROM inquiries WHERE id = ?');
        $stmt->execute([$id]);
        $customerId = (int) $stmt->fetchColumn();
        if ($reply !== '' && $customerId) {
            $stmt2 = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)');
            $stmt2->execute([auth_user()['id'], $customerId, 'Regarding your viewing request: ' . $reply]);
        }
        flash('success', 'The request was ' . $status . ' and the customer can now see your reply.');
    } else {
        flash('error', 'That request was already handled.');
    }
    redirect('inquiries.php');
}

$pending = $pdo->query('SELECT i.*, p.title, p.location, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email FROM inquiries i JOIN properties p ON p.id = i.property_id JOIN users u ON u.id = i.customer_id WHERE i.status = "pending" ORDER BY i.created_at ASC')->fetchAll();
$past = $pdo->query('SELECT i.*, p.title, u.name AS customer_name FROM inquiries i JOIN properties p ON p.id = i.property_id JOIN users u ON u.id = i.customer_id WHERE i.status != "pending" ORDER BY i.responded_at DESC LIMIT 15')->fetchAll();

$page_title = 'Viewing requests';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>Viewing requests</h1>
  <p class="muted">Approve or decline each request. A short reply is sent to the customer automatically.</p>

  <h2 class="section-title-sm">Waiting for response (<?php echo count($pending); ?>)</h2>
  <?php if (!$pending): ?>
    <div class="panel"><p class="muted">Nothing waiting. Well done.</p></div>
  <?php else: ?>
    <?php foreach ($pending as $i): ?>
      <div class="panel inquiry">
        <div class="inquiry-head">
          <div>
            <b><?php echo esc($i['title']); ?></b>
            <p class="muted"><?php echo esc($i['location']); ?> &middot; requested by <b><?php echo esc($i['customer_name']); ?></b> (<?php echo esc($i['customer_phone']); ?>, <?php echo esc($i['customer_email']); ?>)</p>
          </div>
          <div class="inquiry-side"><small class="muted"><?php echo esc(friendly_date($i['created_at'])); ?></small></div>
        </div>
        <p class="note"><b>Customer note:</b> <?php echo nl2br(esc($i['note'])); ?></p>
        <form class="respond" method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="id" value="<?php echo (int) $i['id']; ?>">
          <textarea name="reply" rows="2" placeholder="Reply shown to the customer, e.g. Saturday 10am at the gate works."></textarea>
          <div class="respond-actions">
            <select name="decision">
              <option value="approve">Approve</option>
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
        <thead><tr><th>Customer</th><th>Property</th><th>Requested</th><th>Handled</th><th>Outcome</th></tr></thead>
        <tbody>
        <?php foreach ($past as $i): ?>
          <tr>
            <td><?php echo esc($i['customer_name']); ?></td>
            <td><?php echo esc($i['title']); ?></td>
            <td><?php echo esc(friendly_date($i['created_at'])); ?></td>
            <td><?php echo esc(friendly_date($i['responded_at'])); ?></td>
            <td><?php echo status_badge($i['status']); ?></td>
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
