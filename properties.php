<?php
require_once __DIR__ . '/config/db.php';

$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? '';
$purpose = $_GET['purpose'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;

$where = ['p.status = "available"'];
$params = [];

if ($q !== '') {
    $where[] = '(p.title LIKE :q1 OR p.location LIKE :q2)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}
if (in_array($type, ['house', 'apartment', 'land', 'commercial'], true)) {
    $where[] = 'p.property_type = :type';
    $params[':type'] = $type;
}
if (in_array($purpose, ['rent', 'sale'], true)) {
    $where[] = 'p.listed_for = :purpose';
    $params[':purpose'] = $purpose;
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM properties p WHERE ' . $whereSql);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = 'SELECT p.*, u.name AS manager_name, (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.id AND r.status = "available") AS rooms_available FROM properties p LEFT JOIN users u ON u.id = p.manager_id WHERE ' . $whereSql . ' ORDER BY p.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();

$filtered = $q !== '' || in_array($type, ['house', 'apartment', 'land', 'commercial'], true) || in_array($purpose, ['rent', 'sale'], true);

$page_title = 'Properties';
include __DIR__ . '/includes/header.php';
?>

<h1 class="section-title">Available properties</h1>

<form class="filter-bar" method="get" action="properties.php">
  <input type="text" name="q" value="<?php echo esc($q); ?>" placeholder="Search title or location">
  <select name="type">
    <option value="">Any type</option>
    <option value="house"<?php echo $type === 'house' ? ' selected' : ''; ?>>House</option>
    <option value="apartment"<?php echo $type === 'apartment' ? ' selected' : ''; ?>>Apartment</option>
    <option value="land"<?php echo $type === 'land' ? ' selected' : ''; ?>>Land</option>
    <option value="commercial"<?php echo $type === 'commercial' ? ' selected' : ''; ?>>Commercial</option>
  </select>
  <select name="purpose">
    <option value="">For rent &amp; sale</option>
    <option value="rent"<?php echo $purpose === 'rent' ? ' selected' : ''; ?>>For rent</option>
    <option value="sale"<?php echo $purpose === 'sale' ? ' selected' : ''; ?>>For sale</option>
  </select>
  <button class="btn" type="submit">Search</button>
  <?php if ($filtered): ?><a class="muted" href="properties.php">clear</a><?php endif; ?>
</form>

<?php if (!$properties): ?>
  <p>No properties matched your search. <?php echo $filtered ? '<a href="properties.php">Clear the filters</a> to see everything.' : 'Check back soon.'; ?></p>
<?php else: ?>
<p class="muted small"><?php echo $total; ?> propert<?php echo $total === 1 ? 'y' : 'ies'; ?> found</p>
<div class="cards">
  <?php foreach ($properties as $p): ?>
  <a class="card" href="property.php?id=<?php echo (int) $p['id']; ?>">
    <img class="thumb" src="<?php echo esc(property_thumb($p)); ?>" alt="">
    <div class="card-body">
      <p class="price"><?php echo esc(property_price($p)); ?></p>
      <h3><?php echo esc($p['title']); ?></h3>
      <p class="muted"><?php echo esc($p['location']); ?></p>
      <p class="tags">
        <?php echo status_badge($p['property_type']); ?>
        <?php echo $p['rooms_available'] ? '<span class="badge badge-approved">' . (int) $p['rooms_available'] . ' room' . ($p['rooms_available'] == 1 ? '' : 's') . ' open</span>' : ''; ?>
        <?php echo $p['bedrooms'] ? '<span class="badge badge-muted">' . (int) $p['bedrooms'] . ' bed</span>' : ''; ?>
        <?php echo $p['bathrooms'] ? '<span class="badge badge-muted">' . (int) $p['bathrooms'] . ' bath</span>' : ''; ?>
      </p>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($pages > 1): ?>
<nav class="pager">
  <?php if ($page > 1): ?><a class="btn btn-small btn-outline" href="?<?php echo esc(http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">&larr; Previous</a><?php endif; ?>
  <span class="muted">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
  <?php if ($page < $pages): ?><a class="btn btn-small btn-outline" href="?<?php echo esc(http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next &rarr;</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
