<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);
$user = auth_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peerId = (int) ($_POST['to'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? AND status = "active"');
    $stmt->execute([$peerId]);
    $valid = $stmt->fetch();

    $canTalk = (bool) $valid;
    if ($valid && $valid['id'] == $user['id']) {
        $canTalk = false;
    }
    if ($user['role'] === 'manager' && $valid && $valid['role'] === 'manager') {
        $canTalk = false;
    }
    if (!$canTalk || $body === '') {
        flash('error', 'Could not send that message.');
        redirect('messages.php');
    }
    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'], $peerId, $body]);
    redirect('messages.php?to=' . $peerId);
}

$to = (int) ($_GET['to'] ?? 0);

$roleFilter = $user['role'] === 'manager' ? 'AND u.role IN ("customer","landlord")' : 'AND u.id != ' . (int) $user['id'];
$stmt = $pdo->query('SELECT u.id, u.name, u.role,
        (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ' . (int) $user['id'] . ' AND m.is_read = 0) AS unread
    FROM users u WHERE u.status = "active" ' . $roleFilter . ' ORDER BY (u.role = "customer") ASC, u.name');
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

$page_title = 'Messages';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>Messages</h1>
  <p class="muted">Reply to customers or discuss with <?php echo $user['role'] === 'landlord' ? 'your staff' : 'the landlord'; ?>.</p>

  <div class="chat-layout">
    <div class="chat-contacts">
      <p class="menu-title">People</p>
      <?php foreach ($contacts as $c): ?>
        <a class="contact<?php echo $peer && (int) $c['id'] === (int) $peer['id'] ? ' active' : ''; ?>" href="messages.php?to=<?php echo (int) $c['id']; ?>">
          <?php echo esc($c['name']); ?>
          <small class="muted"><?php echo esc(ucfirst($c['role'])); ?></small>
          <?php if (!empty($c['unread'])): ?><span class="pill"><?php echo (int) $c['unread']; ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <?php if (!$contacts): ?><p class="muted">No one to chat with yet.</p><?php endif; ?>
    </div>

    <div class="chat-panel">
      <?php if (!$peer): ?>
        <div class="chat-empty muted">Choose a person on the left to open the conversation.</div>
      <?php else: ?>
        <div class="chat-thread" id="chatThread">
          <?php foreach ($thread as $m): ?>
            <div class="msg<?php echo (int) $m['sender_id'] === (int) $user['id'] ? ' mine' : ''; ?>">
              <div class="msg-body"><?php echo nl2br(esc($m['body'])); ?></div>
              <small class="muted"><?php echo esc(date('j M Y, H:i', strtotime($m['created_at']))); ?></small>
            </div>
          <?php endforeach; ?>
          <?php if (!$thread): ?><div class="chat-empty muted">No messages with <?php echo esc($peer['name']); ?> yet.</div><?php endif; ?>
        </div>
        <form class="chat-send" method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="to" value="<?php echo (int) $peer['id']; ?>">
          <textarea name="body" rows="2" placeholder="Type your reply and press Send" required></textarea>
          <button class="btn" type="submit">Send</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
