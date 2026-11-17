<?php
require_once __DIR__ . '/config/db.php';

$roomId = (int) ($_GET['room'] ?? $_POST['room'] ?? 0);

$stmt = $pdo->prepare('SELECT r.*, p.title AS property_title, p.location, p.listed_for, p.status AS property_status, p.id AS property_id FROM rooms r JOIN properties p ON p.id = r.property_id WHERE r.id = ?');
$stmt->execute([$roomId]);
$room = $stmt->fetch();

if (!$room) {
    flash('error', 'That room does not exist any more.');
    redirect(ROOT_URL . 'properties.php');
}

$propertyLink = ROOT_URL . 'property.php?id=' . (int) $room['property_id'];
$user = auth_user();

if (!$user) {
    flash('error', 'Create a free account to book this room. It only takes a minute.');
    redirect(ROOT_URL . 'register.php?next=' . urlencode('book.php?room=' . $roomId));
}

if ($user['role'] !== 'customer') {
    flash('error', 'Only customer accounts can book rooms.');
    redirect($propertyLink);
}

if ($room['property_status'] !== 'available' || $room['status'] !== 'available') {
    flash('error', 'That room is no longer available for booking.');
    redirect($propertyLink);
}

$stmt = $pdo->prepare('SELECT id FROM bookings WHERE room_id = ? AND customer_id = ? AND status IN ("pending","approved")');
$stmt->execute([$roomId, $user['id']]);
if ($stmt->fetch()) {
    flash('error', 'You already have a booking request for this room.');
    redirect(ROOT_URL . 'customer/bookings.php');
}

$error = null;
$move_in = $_POST['move_in'] ?? '';
$note = trim($_POST['note'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $moveInDate = null;
    if ($move_in !== '') {
        $ts = strtotime($move_in);
        if (!$ts || $ts < strtotime('today')) {
            $error = 'Choose a move-in date from today onwards.';
        } else {
            $moveInDate = date('Y-m-d', $ts);
        }
    }
    if (!$error && $note === '') {
        $error = 'Please add a short note so the office knows how to reach you.';
    }

    if (!$error) {
        $stmt = $pdo->prepare('INSERT INTO bookings (room_id, property_id, customer_id, move_in, note) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$roomId, $room['property_id'], $user['id'], $moveInDate, $note]);
        flash('success', 'Your booking request has been sent. The office will review it and you will see the confirmation on your bookings page.');
        redirect(ROOT_URL . 'customer/bookings.php');
    }
}

$page_title = 'Book ' . $room['name'];
include __DIR__ . '/includes/header.php';
?>

<p class="muted backlink"><a href="<?php echo esc($propertyLink); ?>">&larr; Back to the property</a></p>

<h1 class="section-title">Book a room</h1>

<div class="book-layout">
  <div class="book-summary">
    <img class="room-thumb" src="<?php echo esc(property_thumb($room)); ?>" alt="">
    <div class="room-body">
      <p class="price"><?php echo esc(money($room['price'])); ?><?php echo $room['listed_for'] === 'rent' ? ' / month' : ''; ?></p>
      <h3><?php echo esc($room['name']); ?></h3>
      <p class="muted"><?php echo esc(ucfirst($room['room_type'])); ?><?php echo $room['capacity'] ? ' &middot; sleeps ' . (int) $room['capacity'] : ''; ?></p>
      <?php if ($room['description']): ?><p class="muted small"><?php echo esc($room['description']); ?></p><?php endif; ?>
      <p class="muted small">Part of <b><?php echo esc($room['property_title']); ?></b>, <?php echo esc($room['location']); ?></p>
    </div>
  </div>

  <div class="panel">
    <h2>Your booking request</h2>
    <?php if ($error): ?><div class="alert alert-error"><?php echo esc($error); ?></div><?php endif; ?>
    <p class="muted">Fill in the details below. Our office will confirm your booking and you will become an accepted customer for this room.</p>
    <form method="post" class="stack">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="room" value="<?php echo (int) $roomId; ?>">
      <label for="move_in">Preferred move-in date <small class="muted">(optional)</small></label>
      <input type="date" id="move_in" name="move_in" min="<?php echo date('Y-m-d'); ?>" value="<?php echo esc($move_in); ?>">
      <label for="note">Note to the office</label>
      <textarea id="note" name="note" rows="3" required placeholder="e.g. I would like to move in next week. Please call me on 0712 345 678."><?php echo esc($note); ?></textarea>
      <button class="btn" type="submit">Send booking request</button>
    </form>
    <p class="muted small" style="margin-top:14px;">You can follow the status of this request any time under <b>My bookings</b>.</p>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
