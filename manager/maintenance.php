<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);

$statuses = ['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'open';
    if (!isset($statuses[$status])) {
        $status = 'open';
    }
    $reply = trim((string) ($_POST['reply'] ?? ''));

    $stmt = $pdo->prepare('SELECT * FROM maintenance_requests WHERE id = ?');
    $stmt->execute([$id]);
    $request = $stmt->fetch();
    if (!$request) {
        flash('error', 'That maintenance request could not be found.');
        redirect('maintenance.php');
    }

    $newReply = $reply !== '' ? $reply : $request['reply'];
    $stmt = $pdo->prepare('UPDATE maintenance_requests SET status = ?, reply = ?, handled_by = ?, responded_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $newReply, auth_user()['id'], $id]);

    $body = 'Maintenance update for "' . $request['subject'] . '": ';
    if ($reply !== '') {
        $body .= $reply;
    } else {
        $body .= 'Your request is now ' . strtolower($statuses[$status]) . '.';
    }
    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)');
    $stmt->execute([auth_user()['id'], $request['customer_id'], $body]);

    flash('success', 'Maintenance request updated and the customer notified.');
    redirect('maintenance.php');
}

$open = $pdo->query('SELECT m.*, p.title AS property_title, p.location, r.name AS room_name, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email FROM maintenance_requests m LEFT JOIN properties p ON p.id = m.property_id LEFT JOIN rooms r ON r.id = m.room_id JOIN users u ON u.id = m.customer_id WHERE m.status IN ("open", "in_progress") ORDER BY FIELD(m.priority, "urgent", "high", "normal", "low"), m.created_at ASC')->fetchAll();
$past = $pdo->query('SELECT m.*, p.title AS property_title, r.name AS room_name, u.name AS customer_name FROM maintenance_requests m LEFT JOIN properties p ON p.id = m.property_id LEFT JOIN rooms r ON r.id = m.room_id JOIN users u ON u.id = m.customer_id WHERE m.status IN ("resolved", "closed") ORDER BY m.responded_at DESC LIMIT 20')->fetchAll();

$photosByRequest = [];
$requestIds = array_map('intval', array_merge(array_column($open, 'id'), array_column($past, 'id')));
if ($requestIds) {
    $placeholders = implode(', ', array_fill(0, count($requestIds), '?'));
    $stmt = $pdo->prepare('SELECT * FROM maintenance_photos WHERE request_id IN (' . $placeholders . ') ORDER BY id');
    $stmt->execute($requestIds);
    foreach ($stmt->fetchAll() as $photo) {
        $photosByRequest[(int) $photo['request_id']][] = $photo;
    }
}

$page_title = 'Maintenance requests';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>Maintenance requests</h1>
  <p class="muted">Review customer reports, update the job status and send a response.</p>

  <h2 class="section-title-sm">Open jobs (<?php echo count($open); ?>)</h2>
  <?php if (!$open): ?>
    <div class="panel"><p class="muted">No open maintenance requests.</p></div>
  <?php else: ?>
    <?php foreach ($open as $request): ?>
      <div class="panel inquiry">
        <div class="inquiry-head">
          <div>
            <b><?php echo esc($request['subject']); ?></b>
            <p class="muted">
              <?php echo esc($request['property_title'] ?: 'General request'); ?>
              <?php if ($request['room_name']): ?> &middot; <?php echo esc($request['room_name']); ?><?php endif; ?>
              &middot; <?php echo esc($request['customer_name']); ?> (<?php echo esc($request['customer_phone']); ?>, <?php echo esc($request['customer_email']); ?>)
            </p>
          </div>
          <div class="inquiry-side">
            <?php echo status_badge($request['status']); ?>
            <?php echo status_badge($request['priority']); ?>
            <small class="muted"><?php echo esc(friendly_date($request['created_at'])); ?></small>
          </div>
        </div>
        <p class="note"><b>Customer report:</b> <?php echo nl2br(esc($request['description'])); ?></p>
        <?php if (!empty($photosByRequest[(int) $request['id']])): ?>
          <div class="photo-grid">
            <?php foreach ($photosByRequest[(int) $request['id']] as $photo): ?>
              <a href="<?php echo esc(ROOT_URL . $photo['photo']); ?>" target="_blank" rel="noopener">
                <img src="<?php echo esc(ROOT_URL . $photo['photo']); ?>" alt="Maintenance photo">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($request['reply']): ?>
          <p class="note note-reply"><b>Current reply:</b> <?php echo nl2br(esc($request['reply'])); ?></p>
        <?php endif; ?>
        <form class="respond" method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="id" value="<?php echo (int) $request['id']; ?>">
          <textarea name="reply" rows="2" placeholder="Tell the customer what has been done or when the repair will happen."></textarea>
          <div class="respond-actions">
            <select name="status">
              <?php foreach ($statuses as $value => $label): ?>
                <option value="<?php echo esc($value); ?>"<?php echo $request['status'] === $value ? ' selected' : ''; ?>><?php echo esc($label); ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Save update</button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h2 class="section-title-sm">Resolved and closed</h2>
  <div class="panel">
    <?php if (!$past): ?>
      <p class="muted">No completed maintenance requests yet.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Customer</th><th>Property / room</th><th>Subject</th><th>Photos</th><th>Priority</th><th>Resolved</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($past as $request): ?>
          <tr>
            <td><?php echo esc($request['customer_name']); ?></td>
            <td><?php echo esc($request['property_title'] ?: 'General'); ?><?php if ($request['room_name']): ?><br><small class="muted"><?php echo esc($request['room_name']); ?></small><?php endif; ?></td>
            <td><?php echo esc($request['subject']); ?></td>
            <td>
              <?php if (!empty($photosByRequest[(int) $request['id']])): ?>
                <div class="photo-grid photo-grid-sm">
                  <?php foreach ($photosByRequest[(int) $request['id']] as $photo): ?>
                    <a href="<?php echo esc(ROOT_URL . $photo['photo']); ?>" target="_blank" rel="noopener">
                      <img src="<?php echo esc(ROOT_URL . $photo['photo']); ?>" alt="Maintenance photo">
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="muted small">None</span>
              <?php endif; ?>
            </td>
            <td><?php echo status_badge($request['priority']); ?></td>
            <td><?php echo esc(friendly_date($request['responded_at'])); ?></td>
            <td><?php echo status_badge($request['status']); ?></td>
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
