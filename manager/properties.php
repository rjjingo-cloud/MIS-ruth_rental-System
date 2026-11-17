<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $stmt = $pdo->prepare('SELECT status FROM properties WHERE id = ?');
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        if ($current !== false) {
            $new = $current === 'available' ? 'taken' : 'available';
            $pdo->prepare('UPDATE properties SET status = ? WHERE id = ?')->execute([$new, $id]);
            flash('success', 'Property marked as ' . $new . '.');
        }
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('SELECT photo FROM properties WHERE id = ?');
        $stmt->execute([$id]);
        $photo = $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT photo FROM rooms WHERE property_id = ?');
        $stmt->execute([$id]);
        $roomPhotos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare('DELETE FROM properties WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            $paths = array_merge([$photo], $roomPhotos);
            foreach ($paths as $p) {
                if ($p && strpos($p, 'uploads/') === 0) {
                    $path = dirname(__DIR__) . '/' . $p;
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }
        }
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Property deleted.' : 'Property not found.');
    }
    redirect('properties.php');
}

$q = trim($_GET['q'] ?? '');

$sql = 'SELECT p.*, u.name AS manager_name, (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.id) AS rooms_count FROM properties p LEFT JOIN users u ON u.id = p.manager_id';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE (p.title LIKE :q1 OR p.location LIKE :q2)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}
$sql .= ' ORDER BY p.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();

$page_title = 'Properties';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <div class="panel-head">
    <h1>All properties</h1>
    <a class="btn" href="property-form.php">Add new property</a>
  </div>

  <form class="filter-bar" method="get">
    <input type="text" name="q" value="<?php echo esc($q); ?>" placeholder="Search by title or location">
    <button class="btn btn-outline" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="muted" href="properties.php">clear</a><?php endif; ?>
  </form>

  <div class="table-wrap">
  <table class="table table-props">
    <thead><tr><th>Property</th><th>Type</th><th>Price</th><th>Rooms</th><th>Status</th><th>Added by</th><th></th></tr></thead>
    <tbody>
    <?php if (!$properties): ?>
      <tr><td colspan="7" class="muted center">No properties found.</td></tr>
    <?php endif; ?>
    <?php foreach ($properties as $p): ?>
      <tr>
        <td>
          <b><a href="../property.php?id=<?php echo (int) $p['id']; ?>"><?php echo esc($p['title']); ?></a></b><br>
          <small class="muted"><?php echo esc($p['location']); ?></small>
        </td>
        <td><?php echo esc(ucfirst($p['property_type'])); ?><br><small class="muted">for <?php echo esc($p['listed_for']); ?></small></td>
        <td><?php echo esc(property_price($p)); ?></td>
        <td>
          <?php echo (int) $p['rooms_count']; ?>
          <a class="btn btn-tiny btn-outline" href="rooms.php?property=<?php echo (int) $p['id']; ?>">Manage</a>
        </td>
        <td><?php echo status_badge($p['status']); ?></td>
        <td class="muted"><?php echo esc($p['manager_name'] ?? 'unassigned'); ?></td>
        <td class="cell-actions">
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
            <button class="btn btn-tiny btn-outline" name="action" value="toggle" type="submit"><?php echo $p['status'] === 'available' ? 'Mark taken' : 'Mark available'; ?></button>
            <a class="btn btn-tiny btn-outline" href="property-form.php?id=<?php echo (int) $p['id']; ?>">Edit</a>
            <button class="btn btn-tiny btn-danger" name="action" value="delete" type="submit" data-confirm="Delete this property permanently? Its rooms and viewing requests will also be removed.">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
