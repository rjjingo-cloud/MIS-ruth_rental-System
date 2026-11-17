<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role('customer');
$user = auth_user();

$stmt = $pdo->prepare('SELECT b.id, b.property_id, b.room_id, r.name AS room_name, p.title, p.location FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN properties p ON p.id = b.property_id WHERE b.customer_id = ? AND b.status = "approved" ORDER BY b.created_at DESC');
$stmt->execute([$user['id']]);
$approvedBookings = $stmt->fetchAll();

$errors = [];
$form = ['booking_id' => 0, 'subject' => '', 'description' => '', 'priority' => 'normal'];
$priorities = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
$validPhotos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $form['booking_id'] = (int) ($_POST['booking_id'] ?? 0);
    $form['subject'] = trim((string) ($_POST['subject'] ?? ''));
    $form['description'] = trim((string) ($_POST['description'] ?? ''));
    $form['priority'] = $_POST['priority'] ?? 'normal';
    if (!isset($priorities[$form['priority']])) {
        $form['priority'] = 'normal';
    }
    if ($form['subject'] === '') {
        $errors[] = 'Enter a short subject for the request.';
    }
    if ($form['description'] === '') {
        $errors[] = 'Describe the problem so the office knows what to fix.';
    }

    $booking = null;
    if ($form['booking_id']) {
        foreach ($approvedBookings as $approved) {
            if ((int) $approved['id'] === $form['booking_id']) {
                $booking = $approved;
                break;
            }
        }
        if (!$booking) {
            $errors[] = 'Choose one of your approved bookings.';
        }
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $maxBytes = 3 * 1024 * 1024;
    $maxPhotos = 3;
    if (!empty($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
        $fileCount = count($_FILES['photos']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $errorCode = (int) ($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            $originalName = (string) ($_FILES['photos']['name'][$i] ?? '');
            if ($errorCode === UPLOAD_ERR_NO_FILE || $originalName === '') {
                continue;
            }
            if ($errorCode !== UPLOAD_ERR_OK) {
                $errors[] = 'One of the images could not be uploaded. Please try again.';
                break;
            }
            $tmp = (string) ($_FILES['photos']['tmp_name'][$i] ?? '');
            $size = (int) ($_FILES['photos']['size'][$i] ?? 0);
            $info = @getimagesize($tmp);
            if (!$info || !isset($allowed[$info['mime']])) {
                $errors[] = 'Images must be JPG, PNG or WebP files.';
                continue;
            }
            if ($size <= 0 || $size > $maxBytes) {
                $errors[] = 'Each image must be under 3MB.';
                continue;
            }
            $validPhotos[] = ['tmp' => $tmp, 'ext' => $allowed[$info['mime']]];
        }
        if (count($validPhotos) > $maxPhotos) {
            $errors[] = 'You can upload up to 3 images.';
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        $moved = [];
        try {
            $stmt = $pdo->prepare('INSERT INTO maintenance_requests (customer_id, property_id, room_id, booking_id, subject, description, priority) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $user['id'],
                $booking ? $booking['property_id'] : null,
                $booking ? $booking['room_id'] : null,
                $booking ? $booking['id'] : null,
                $form['subject'],
                $form['description'],
                $form['priority'],
            ]);
            $requestId = (int) $pdo->lastInsertId();

            $uploadDir = dirname(__DIR__) . '/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            foreach ($validPhotos as $photo) {
                $filename = 'maintenance_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $photo['ext'];
                $destination = $uploadDir . '/' . $filename;
                if (!move_uploaded_file($photo['tmp'], $destination)) {
                    throw new RuntimeException('Could not save an uploaded image.');
                }
                $moved[] = $destination;
                $stmt = $pdo->prepare('INSERT INTO maintenance_photos (request_id, photo) VALUES (?, ?)');
                $stmt->execute([$requestId, 'uploads/' . $filename]);
            }
            $pdo->commit();
            flash('success', 'Maintenance request submitted. The office will respond here.');
            redirect('maintenance.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($moved as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            $errors[] = 'The request could not be saved. Please try again.';
        }
    }
}

$stmt = $pdo->prepare('SELECT m.*, p.title AS property_title, p.location, r.name AS room_name, u.name AS handler_name FROM maintenance_requests m LEFT JOIN properties p ON p.id = m.property_id LEFT JOIN rooms r ON r.id = m.room_id LEFT JOIN users u ON u.id = m.handled_by WHERE m.customer_id = ? ORDER BY (m.status IN ("open", "in_progress")) DESC, m.created_at DESC');
$stmt->execute([$user['id']]);
$requests = $stmt->fetchAll();

$photosByRequest = [];
if ($requests) {
    $requestIds = array_map('intval', array_column($requests, 'id'));
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
  <p class="muted">Report a problem in your room or property and follow the office response here.</p>

  <div class="panel">
    <h2>New request</h2>
    <?php if ($errors): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $error): ?><p><?php echo esc($error); ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="create">
      <label>Property
        <select name="booking_id">
          <option value="0">General request (no specific room)</option>
          <?php foreach ($approvedBookings as $booking): ?>
            <option value="<?php echo (int) $booking['id']; ?>"<?php echo $form['booking_id'] === (int) $booking['id'] ? ' selected' : ''; ?>>
              <?php echo esc($booking['title'] . ' - ' . $booking['room_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="field-row">
        <label>Subject
          <input type="text" name="subject" maxlength="150" required value="<?php echo esc($form['subject']); ?>" placeholder="e.g. Leaking kitchen tap">
        </label>
        <label>Priority
          <select name="priority">
            <?php foreach ($priorities as $value => $label): ?>
              <option value="<?php echo esc($value); ?>"<?php echo $form['priority'] === $value ? ' selected' : ''; ?>><?php echo esc($label); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label>What needs fixing?
        <textarea name="description" rows="4" required placeholder="Describe the problem and when it started."><?php echo esc($form['description']); ?></textarea>
      </label>
      <label>Photos <small class="muted">(optional, up to 3 JPG, PNG or WebP images under 3MB each)</small>
        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
      </label>
      <button class="btn" type="submit">Submit request</button>
    </form>
    <?php if (!$approvedBookings): ?>
      <p class="muted small">You have no approved bookings yet, so this will be sent as a general request.</p>
    <?php endif; ?>
  </div>

  <?php if (!$requests): ?>
    <div class="panel"><p class="muted">You have not submitted any maintenance requests yet.</p></div>
  <?php else: ?>
    <?php foreach ($requests as $request): ?>
      <div class="panel inquiry">
        <div class="inquiry-head">
          <div>
            <b><?php echo esc($request['subject']); ?></b>
            <p class="muted">
              <?php echo esc($request['property_title'] ?: 'General request'); ?>
              <?php if ($request['room_name']): ?> &middot; <?php echo esc($request['room_name']); ?><?php endif; ?>
            </p>
          </div>
          <div class="inquiry-side">
            <?php echo status_badge($request['status']); ?>
            <?php echo status_badge($request['priority']); ?>
            <small class="muted"><?php echo esc(friendly_date($request['created_at'])); ?></small>
          </div>
        </div>
        <p class="note"><b>Your report:</b> <?php echo nl2br(esc($request['description'])); ?></p>
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
          <p class="note note-reply"><b>Office reply:</b> <?php echo nl2br(esc($request['reply'])); ?></p>
          <p class="muted small">Handled by <?php echo esc($request['handler_name'] ?: 'the office'); ?><?php echo $request['responded_at'] ? ' on ' . esc(friendly_date($request['responded_at'])) : ''; ?>.</p>
        <?php else: ?>
          <p class="muted small">Waiting for the office to respond.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
