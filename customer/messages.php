<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role('customer');
$user = auth_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peerId = (int) ($_POST['to'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role IN ("manager","landlord") AND status = "active"');
    $stmt->execute([$peerId]);
    $valid = $stmt->fetch();
    if (!$valid || $body === '') {
        flash('error', 'Please type your message first.');
        redirect('messages.php' . ($peerId ? '?to=' . $peerId : ''));
    }
    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'], $peerId, $body]);
    redirect('messages.php?to=' . $peerId);
}

$to = (int) ($_GET['to'] ?? 0);

$stmt = $pdo->query('SELECT id, name, role FROM users WHERE role IN ("manager","landlord") AND status = "active" ORDER BY role');
$contacts = $stmt->fetchAll();

$peer = null;
foreach ($contacts as $c) {
    if ((int) $c['id'] === $to) {
        $peer = $c;
    }
}

$thread = [];
if ($peer) {
    $pdo->prepare('UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?')->execute([$user['id'], $peer['id']]);
    $stmt = $pdo->prepare('SELECT m.*, u.name AS sender_name FROM messages m JOIN users u ON u.id = m.sender_id WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ORDER BY m.created_at ASC');
    $stmt->execute([$user['id'], $peer['id'], $peer['id'], $user['id']]);
    $thread = $stmt->fetchAll();
}

$unreadByPeer = [];
$stmt = $pdo->prepare('SELECT sender_id, COUNT(*) AS c FROM messages WHERE receiver_id = ? AND is_read = 0 GROUP BY sender_id');
$stmt->execute([$user['id']]);
foreach ($stmt->fetchAll() as $row) {
    $unreadByPeer[(int) $row['sender_id']] = (int) $row['c'];
}

$page_title = 'Messages';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>Messages</h1>
  <p class="muted">Talk to our office here. We usually reply within a day.</p>

  <div class="chat-layout">
    <div class="chat-contacts">
      <p class="menu-title">Our staff</p>
      <?php foreach ($contacts as $c): ?>
        <a class="contact<?php echo $peer && (int) $c['id'] === (int) $peer['id'] ? ' active' : ''; ?>" href="messages.php?to=<?php echo (int) $c['id']; ?>">
          <?php echo esc($c['name']); ?>
          <small class="muted"><?php echo esc($c['role']); ?></small>
          <?php if (!empty($unreadByPeer[(int) $c['id']])): ?><span class="pill"><?php echo $unreadByPeer[(int) $c['id']]; ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <?php if (!$contacts): ?><p class="muted">Our office is not reachable at the moment. Try again later.</p><?php endif; ?>
    </div>

    <div class="chat-panel">
      <?php if (!$peer): ?>
        <div class="chat-empty muted">Choose a staff member on the left to start a conversation.</div>
      <?php else: ?>
        <div class="chat-thread" id="chatThread">
          <?php foreach ($thread as $m): ?>
            <div class="msg<?php echo (int) $m['sender_id'] === (int) $user['id'] ? ' mine' : ''; ?>">
              <div class="msg-body"><?php echo nl2br(esc($m['body'])); ?></div>
              <small class="muted"><?php echo esc(date('j M Y, H:i', strtotime($m['created_at']))); ?></small>
            </div>
          <?php endforeach; ?>
          <?php if (!$thread): ?><div class="chat-empty muted">No messages with <?php echo esc($peer['name']); ?> yet. Say hello!</div><?php endif; ?>
        </div>
        <form class="chat-send" method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="to" value="<?php echo (int) $peer['id']; ?>">
          <textarea name="body" rows="2" placeholder="Type your message and press Send" required></textarea>
          <button class="btn" type="submit">Send</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
