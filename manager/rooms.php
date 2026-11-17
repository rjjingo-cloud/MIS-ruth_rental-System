<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);
$user = auth_user();

$propertyId = (int) ($_GET['property'] ?? $_POST['property'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM properties WHERE id = ?');
$stmt->execute([$propertyId]);
$property = $stmt->fetch();
if (!$property) {
    flash('error', 'Property not found.');
    redirect('properties.php');
}

$types = ['single', 'double', 'studio', 'bedsitter', 'master', 'shop', 'office', 'plot', 'whole'];
$statuses = ['available', 'occupied', 'maintenance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT photo FROM rooms WHERE id = ? AND property_id = ?');
        $stmt->execute([$id, $propertyId]);
        $photo = $stmt->fetchColumn();
        $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = ? AND property_id = ?');
        $stmt->execute([$id, $propertyId]);
        if ($stmt->rowCount() && $photo && strpos($photo, 'uploads/') === 0) {
            $path = dirname(__DIR__) . '/' . $photo;
            if (is_file($path)) {
                unlink($path);
            }
        }
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Room deleted.' : 'Room not found.');
        redirect('rooms.php?property=' . $propertyId);
    }

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $roomType = $_POST['room_type'] ?? '';
    $price = (float) ($_POST['price'] ?? 0);
    $capacity = max(0, (int) ($_POST['capacity'] ?? 0));
    $status = $_POST['status'] ?? 'available';
    $description = trim($_POST['description'] ?? '');
    $error = null;

    if ($name === '') {
        $error = 'Give the room a name, e.g. "Bedsitter 1".';
    } elseif (!in_array($roomType, $types, true)) {
        $error = 'Choose a valid room type.';
    } elseif ($price <= 0) {
        $error = 'Enter a price greater than zero.';
    } elseif (!in_array($status, $statuses, true)) {
        $error = 'Choose a valid availability status.';
    }

    $photo = null;
    if ($id) {
        $stmt = $pdo->prepare('SELECT photo FROM rooms WHERE id = ? AND property_id = ?');
        $stmt->execute([$id, $propertyId]);
        $photo = $stmt->fetchColumn();
        if ($photo === false) {
            flash('error', 'Room not found.');
            redirect('rooms.php?property=' . $propertyId);
        }
    }

    if (!$error && !empty($_FILES['photo']['name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $info = @getimagesize($_FILES['photo']['tmp_name']);
        if (!$info || !isset($allowed[$info['mime']]) || filesize($_FILES['photo']['tmp_name']) > 3 * 1024 * 1024) {
            $error = 'Photo must be a JPG, PNG or WebP image under 3MB.';
        } else {
            $ext = $allowed[$info['mime']];
            $filename = 'room_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 5) . '.' . $ext;
            if (!is_dir(dirname(__DIR__) . '/uploads')) {
                mkdir(dirname(__DIR__) . '/uploads', 0777, true);
            }
            move_uploaded_file($_FILES['photo']['tmp_name'], dirname(__DIR__) . '/uploads/' . $filename);
            if ($photo && strpos($photo, 'uploads/') === 0 && is_file(dirname(__DIR__) . '/' . $photo)) {
                unlink(dirname(__DIR__) . '/' . $photo);
            }
            $photo = 'uploads/' . $filename;
        }
    }

    if (!$error) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE rooms SET name = ?, room_type = ?, price = ?, capacity = ?, status = ?, description = ?, photo = ? WHERE id = ? AND property_id = ?');
            $stmt->execute([$name, $roomType, $price, $capacity, $status, $description ?: null, $photo, $id, $propertyId]);
            flash('success', 'Room updated.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO rooms (property_id, name, room_type, price, capacity, status, description, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$propertyId, $name, $roomType, $price, $capacity, $status, $description ?: null, $photo]);
            flash('success', 'Room added.');
        }
        redirect('rooms.php?property=' . $propertyId);
    }
}

$edit = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ? AND property_id = ?');
    $stmt->execute([$editId, $propertyId]);
    $edit = $stmt->fetch();
    if (!$edit) {
        flash('error', 'Room not found.');
        redirect('rooms.php?property=' . $propertyId);
    }
}

$stmt = $pdo->prepare('SELECT r.*, (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id AND b.status IN ("pending","approved")) AS active_bookings FROM rooms r WHERE r.property_id = ? ORDER BY r.name');
$stmt->execute([$propertyId]);
$rooms = $stmt->fetchAll();

$page_title = 'Rooms - ' . $property['title'];
include dirname(__DIR__) . '/includes/header.php';
$edit = $edit ?: [];
$v = fn($field, $fallback = '') => esc($_POST[$field] ?? ($edit[$field] ?? $fallback));
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <div class="panel-head">
    <h1>Rooms &amp; units</h1>
    <a class="btn btn-small btn-outline" href="properties.php">&larr; Back to properties</a>
  </div>
  <p class="muted"><b><?php echo esc($property['title']); ?></b> &middot; <?php echo esc($property['location']); ?></p>

  <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo esc($error); ?></div><?php endif; ?>

  <div class="panel">
    <h2><?php echo $edit ? 'Edit room: ' . esc($edit['name']) : 'Add a room'; ?></h2>
    <form method="post" enctype="multipart/form-data" class="stack">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="property" value="<?php echo (int) $propertyId; ?>">
      <input type="hidden" name="id" value="<?php echo $edit ? (int) $edit['id'] : 0; ?>">

      <div class="field-row">
        <div>
          <label for="name">Room name</label>
          <input type="text" id="name" name="name" value="<?php echo $v('name'); ?>" required placeholder="e.g. Bedsitter 5">
        </div>
        <div>
          <label for="room_type">Type</label>
          <select id="room_type" name="room_type">
            <?php foreach ($types as $t): ?>
              <option value="<?php echo $t; ?>"<?php echo $v('room_type', 'single') === $t ? ' selected' : ''; ?>><?php echo esc(ucfirst($t)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field-row field-row-3">
        <div>
          <label for="price">Price (<?php echo CURRENCY; ?>)</label>
          <input type="number" id="price" name="price" min="1" step="0.01" value="<?php echo $v('price'); ?>" required>
        </div>
        <div>
          <label for="capacity">Sleeps</label>
          <input type="number" id="capacity" name="capacity" min="0" max="30" value="<?php echo $v('capacity', '1'); ?>">
        </div>
        <div>
          <label for="status">Availability</label>
          <select id="status" name="status">
            <?php foreach ($statuses as $s): ?>
              <option value="<?php echo $s; ?>"<?php echo $v('status', 'available') === $s ? ' selected' : ''; ?>><?php echo esc(ucfirst($s)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label for="description">Short description <small class="muted">(optional)</small></label>
      <input type="text" id="description" name="description" maxlength="255" value="<?php echo $v('description'); ?>">

      <label for="photo">Photo <small class="muted">(JPG, PNG or WebP, max 3MB)</small></label>
      <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
      <?php if ($edit && $edit['photo']): ?>
        <p class="muted">Current photo:</p>
        <img class="thumb-preview" src="<?php echo esc(property_thumb($edit)); ?>" alt="">
      <?php endif; ?>

      <button class="btn" type="submit"><?php echo $edit ? 'Save room' : 'Add room'; ?></button>
      <?php if ($edit): ?><a class="btn btn-outline" href="rooms.php?property=<?php echo (int) $propertyId; ?>">Cancel edit</a><?php endif; ?>
    </form>
  </div>

  <h2 class="section-title-sm">All rooms (<?php echo count($rooms); ?>)</h2>
  <div class="panel">
    <?php if (!$rooms): ?>
      <p class="muted">No rooms yet. Add the first one above.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table table-props">
        <thead><tr><th>Room</th><th>Type</th><th>Price</th><th>Sleeps</th><th>Status</th><th>Bookings</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rooms as $r): ?>
          <tr>
            <td>
              <b><?php echo esc($r['name']); ?></b>
              <?php if ($r['description']): ?><br><small class="muted"><?php echo esc($r['description']); ?></small><?php endif; ?>
            </td>
            <td><?php echo esc(ucfirst($r['room_type'])); ?></td>
            <td><?php echo esc(money($r['price'])); ?></td>
            <td><?php echo (int) $r['capacity']; ?></td>
            <td><?php echo status_badge($r['status']); ?></td>
            <td><?php echo (int) $r['active_bookings']; ?></td>
            <td class="cell-actions">
              <form method="post">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="property" value="<?php echo (int) $propertyId; ?>">
                <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                <a class="btn btn-tiny btn-outline" href="rooms.php?property=<?php echo (int) $propertyId; ?>&edit=<?php echo (int) $r['id']; ?>">Edit</a>
                <button class="btn btn-tiny btn-danger" name="action" value="delete" type="submit" data-confirm="Delete this room? Its bookings will also be removed.">Delete</button>
              </form>
            </td>
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
