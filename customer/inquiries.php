<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role('customer');
$user = auth_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $stmt = $pdo->prepare('DELETE FROM inquiries WHERE id = ? AND customer_id = ? AND status = "pending"');
    $stmt->execute([(int) $_POST['id'], $user['id']]);
    flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Request withdrawn.' : 'That request can no longer be withdrawn.');
    redirect('inquiries.php');
}

$page_title = 'My viewing requests';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>My viewing requests</h1>
  <p class="muted">Track each request and read the office reply underneath it.</p>

  <?php
  $stmt = $pdo->prepare('SELECT i.*, p.title, p.location, p.id AS pid FROM inquiries i JOIN properties p ON p.id = i.property_id WHERE i.customer_id = ? ORDER BY i.created_at DESC');
  $stmt->execute([$user['id']]);
  $inquiries = $stmt->fetchAll();

  if (!$inquiries): ?>
    <div class="panel">
      <p class="muted">Nothing yet. Find something you like and request a viewing <a href="../properties.php">here</a>.</p>
    </div>
  <?php else: ?>
    <?php foreach ($inquiries as $i): ?>
      <div class="panel inquiry">
        <div class="inquiry-head">
          <div>
            <b><a href="../property.php?id=<?php echo (int) $i['pid']; ?>"><?php echo esc($i['title']); ?></a></b>
            <p class="muted"><?php echo esc($i['location']); ?></p>
          </div>
          <div class="inquiry-side">
            <?php echo status_badge($i['status']); ?>
            <small class="muted"><?php echo esc(friendly_date($i['created_at'])); ?></small>
          </div>
        </div>
        <p class="note"><b>Your note:</b> <?php echo nl2br(esc($i['note'])); ?></p>
        <?php if ($i['reply']): ?>
          <p class="note note-reply"><b>Office reply:</b> <?php echo nl2br(esc($i['reply'])); ?></p>
        <?php endif; ?>
        <?php if ($i['status'] === 'pending'): ?>
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" value="<?php echo (int) $i['id']; ?>">
            <button class="btn btn-tiny btn-danger" type="submit" data-confirm="Withdraw this viewing request?">Withdraw request</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
