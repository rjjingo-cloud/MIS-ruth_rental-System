<?php
require_once __DIR__ . '/config/db.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT p.*, u.name AS manager_name, u.phone AS manager_phone FROM properties p LEFT JOIN users u ON u.id = p.manager_id WHERE p.id = ?');
$stmt->execute([$id]);
$property = $stmt->fetch();

if (!$property) {
    flash('error', 'That property does not exist.');
    redirect(ROOT_URL . 'properties.php');
}

$others = $pdo->prepare('SELECT * FROM properties WHERE id != ? AND status = "available" ORDER BY RAND() LIMIT 3');
$others->execute([$id]);
$others = $others->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM rooms WHERE property_id = ? ORDER BY (status = "available") DESC, name');
$stmt->execute([$id]);
$rooms = $stmt->fetchAll();

$user = auth_user();
$myInquiry = null;
if ($user && $user['role'] === 'customer') {
    $stmt = $pdo->prepare('SELECT * FROM inquiries WHERE property_id = ? AND customer_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$id, $user['id']]);
    $myInquiry = $stmt->fetch();
}
$done = $myInquiry && in_array($myInquiry['status'], ['pending', 'approved'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && $user['role'] === 'customer') {
    if ($done) {
        flash('error', 'You already have a running request on this property.');
        redirect('property.php?id=' . $id);
    }
    $note = trim($_POST['note'] ?? '');
    if ($note === '') {
        flash('error', 'Please tell us briefly when you would like to view this property.');
        redirect('property.php?id=' . $id);
    }
    $stmt = $pdo->prepare('INSERT INTO inquiries (property_id, customer_id, note) VALUES (?, ?, ?)');
    $stmt->execute([$id, $user['id'], $note]);
    flash('success', 'Your viewing request has been sent. We will get back to you shortly.');
    redirect(ROOT_URL . 'customer/inquiries.php');
}

$page_title = $property['title'];
include __DIR__ . '/includes/header.php';
?>

<p class="muted backlink"><a href="properties.php">&larr; Back to all properties</a></p>

<article class="detail">
  <img class="detail-thumb" src="<?php echo esc(property_thumb($property)); ?>" alt="">
  <div class="detail-body">
    <div class="detail-head">
      <h1><?php echo esc($property['title']); ?></h1>
      <p class="price price-lg"><?php echo esc(property_price($property)); ?></p>
    </div>
    <p class="muted"><?php echo esc($property['location']); ?> &middot; Added <?php echo esc(friendly_date($property['created_at'])); ?></p>
    <p class="tags">
      <?php echo status_badge($property['property_type']); ?>
      <?php echo status_badge($property['listed_for']); ?>
      <?php echo status_badge($property['status']); ?>
      <?php echo $property['bedrooms'] ? '<span class="badge badge-muted">' . (int) $property['bedrooms'] . ' bedrooms</span>' : ''; ?>
      <?php echo $property['bathrooms'] ? '<span class="badge badge-muted">' . (int) $property['bathrooms'] . ' bathrooms</span>' : ''; ?>
    </p>
    <p class="detail-desc"><?php echo nl2br(esc($property['description'])); ?></p>

    <?php if ($user && in_array($user['role'], ['manager', 'landlord'], true)): ?>
      <div class="callout">
        <p><b>Staff panel:</b> this listing is handled by <?php echo esc($property['manager_name'] ?? 'unassigned'); ?>.</p>
        <a class="btn btn-outline" href="<?php echo ROOT_URL; ?>manager/property-form.php?id=<?php echo (int) $property['id']; ?>">Edit this property</a>
        <a class="btn btn-outline" href="<?php echo ROOT_URL; ?>manager/rooms.php?property=<?php echo (int) $property['id']; ?>">Manage rooms</a>
      </div>
    <?php elseif ($user && $user['role'] === 'customer'): ?>
      <?php if ($property['status'] !== 'available'): ?>
        <div class="callout"><p>This property is currently <?php echo esc($property['status']); ?>. Ask us if you would like to be notified when it opens up.</p></div>
      <?php elseif ($done && $myInquiry['status'] === 'pending'): ?>
        <div class="callout"><p>Your viewing request was sent on <?php echo esc(friendly_date($myInquiry['created_at'])); ?> and is still awaiting approval.</p></div>
      <?php elseif ($done && $myInquiry['status'] === 'approved'): ?>
        <div class="callout">
          <p>Good news - your viewing request for this property was approved by our office<?php echo $myInquiry['reply'] ? ': <i>&ldquo;' . esc($myInquiry['reply']) . '&rdquo;</i>' : '.'; ?></p>
        </div>
      <?php else: ?>
        <form method="post" action="property.php?id=<?php echo (int) $id; ?>" class="stack">
          <?php echo csrf_input(); ?>
          <label for="note">Request a viewing</label>
          <textarea id="note" name="note" rows="3" required placeholder="e.g. I would like to view it on Saturday at 10am.<?php echo ($myInquiry && $myInquiry['status'] === 'rejected') ? ' My previous request was declined.' : ''; ?>"></textarea>
          <?php if ($myInquiry && $myInquiry['status'] === 'rejected'): ?>
            <p class="muted">Your previous request was declined<?php echo $myInquiry['reply'] ? ': ' . esc($myInquiry['reply']) : '.'; ?> You may try again.</p>
          <?php endif; ?>
          <button class="btn" type="submit">Send request</button>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <div class="callout">
        <p>Would you like to see this property? <a href="login.php">Log in</a> or <a href="register.php">open a free account</a> to request a viewing.</p>
      </div>
    <?php endif; ?>
  </div>
</article>

<?php if ($rooms): ?>
<h2 class="section-title">Rooms &amp; units</h2>
<p class="muted">Pick an available room to start a booking. Our office confirms each request, after which you become an accepted customer.</p>
<div class="rooms">
  <?php foreach ($rooms as $r): ?>
  <div class="room-card">
    <img class="room-thumb" src="<?php echo esc(property_thumb($r)); ?>" alt="">
    <div class="room-body">
      <p class="price"><?php echo esc(money($r['price'])); ?><?php echo $property['listed_for'] === 'rent' ? ' / month' : ''; ?></p>
      <h3><?php echo esc($r['name']); ?></h3>
      <p class="muted small"><?php echo esc(ucfirst($r['room_type'])); ?><?php echo $r['capacity'] ? ' &middot; sleeps ' . (int) $r['capacity'] : ''; ?></p>
      <?php if ($r['description']): ?><p class="muted small"><?php echo esc($r['description']); ?></p><?php endif; ?>
      <p class="tags"><?php echo status_badge($r['status']); ?></p>
      <?php if ($r['status'] === 'available' && $property['status'] === 'available'): ?>
        <?php if ($user && in_array($user['role'], ['manager', 'landlord'], true)): ?>
          <a class="btn btn-small btn-outline" href="<?php echo ROOT_URL; ?>manager/rooms.php?property=<?php echo (int) $property['id']; ?>">Manage rooms</a>
        <?php else: ?>
          <a class="btn btn-small" href="book.php?room=<?php echo (int) $r['id']; ?>">Book this room</a>
        <?php endif; ?>
      <?php else: ?>
        <span class="btn btn-small btn-disabled"><?php echo $r['status'] === 'occupied' ? 'Occupied' : 'Unavailable'; ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($others): ?>
<h2 class="section-title">Other properties you may like</h2>
<div class="cards">
  <?php foreach ($others as $p): ?>
  <a class="card" href="property.php?id=<?php echo (int) $p['id']; ?>">
    <img class="thumb" src="<?php echo esc(property_thumb($p)); ?>" alt="">
    <div class="card-body">
      <p class="price"><?php echo esc(property_price($p)); ?></p>
      <h3><?php echo esc($p['title']); ?></h3>
      <p class="muted"><?php echo esc($p['location']); ?></p>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
