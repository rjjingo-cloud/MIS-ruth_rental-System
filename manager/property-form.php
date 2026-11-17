<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);
$user = auth_user();

$id = (int) ($_GET['id'] ?? 0);
$property = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt->execute([$id]);
    $property = $stmt->fetch();
    if (!$property) {
        flash('error', 'Property not found.');
        redirect('properties.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['property_type'] ?? '';
    $listed = $_POST['listed_for'] ?? '';
    $price = (float) ($_POST['price'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $bedrooms = max(0, (int) $_POST['bedrooms']);
    $bathrooms = max(0, (int) $_POST['bathrooms']);
    $status = ($_POST['status'] ?? 'available') === 'taken' ? 'taken' : 'available';
    $error = null;

    if ($title === '' || $description === '' || $location === '') {
        $error = 'Title, location and description are all required.';
    } elseif (!in_array($type, ['house', 'apartment', 'land', 'commercial'], true)) {
        $error = 'Choose a valid property type.';
    } elseif (!in_array($listed, ['rent', 'sale'], true)) {
        $error = 'Choose whether the property is for rent or for sale.';
    } elseif ($price <= 0) {
        $error = 'Enter a price greater than zero.';
    }

    $photo = $property ? $property['photo'] : null;
    if (!$error && !empty($_FILES['photo']['name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $info = @getimagesize($_FILES['photo']['tmp_name']);
        if (!$info || !isset($allowed[$info['mime']]) || filesize($_FILES['photo']['tmp_name']) > 3 * 1024 * 1024) {
            $error = 'Photo must be a JPG, PNG or WebP image under 3MB.';
        } else {
            $ext = $allowed[$info['mime']];
            $filename = 'prop_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 5) . '.' . $ext;
            if (!is_dir(dirname(__DIR__) . '/uploads')) {
                mkdir(dirname(__DIR__) . '/uploads', 0777, true);
            }
            move_uploaded_file($_FILES['photo']['tmp_name'], dirname(__DIR__) . '/uploads/' . $filename);
            $photo = 'uploads/' . $filename;
        }
    }

    if (!$error) {
        if ($property) {
            $stmt = $pdo->prepare('UPDATE properties SET title = ?, description = ?, property_type = ?, listed_for = ?, price = ?, location = ?, bedrooms = ?, bathrooms = ?, photo = ?, status = ? WHERE id = ?');
            $stmt->execute([$title, $description, $type, $listed, $price, $location, $bedrooms, $bathrooms, $photo, $status, $id]);
            flash('success', 'Property updated.');
            redirect('properties.php');
        } else {
            $stmt = $pdo->prepare('INSERT INTO properties (title, description, property_type, listed_for, price, location, bedrooms, bathrooms, photo, status, manager_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $description, $type, $listed, $price, $location, $bedrooms, $bathrooms, $photo, $status, $user['id']]);
            flash('success', 'New property listed. Now add its rooms or units.');
            redirect('rooms.php?property=' . (int) $pdo->lastInsertId());
        }
    }
}

$page_title = $property ? 'Edit property' : 'Add property';
include dirname(__DIR__) . '/includes/header.php';
$property = $property ?: [];
$v = fn($field, $fallback = '') => esc($_POST[$field] ?? ($property[$field] ?? $fallback));
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1><?php echo $property ? 'Edit property' : 'List a new property'; ?></h1>
  <p class="muted"><a href="properties.php">&larr; Back to all properties</a><?php if ($property): ?> &middot; <a href="rooms.php?property=<?php echo (int) $property['id']; ?>">Manage rooms &amp; units</a><?php endif; ?></p>
  <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo esc($error); ?></div><?php endif; ?>

  <div class="panel narrow">
    <form method="post" enctype="multipart/form-data" class="stack">
      <?php echo csrf_input(); ?>
      <label for="title">Property title</label>
      <input type="text" id="title" name="title" value="<?php echo $v('title'); ?>" required>

      <div class="field-row">
        <div>
          <label for="property_type">Type</label>
          <select id="property_type" name="property_type">
            <option value="house"<?php echo $v('property_type') === 'house' ? ' selected' : ''; ?>>House</option>
            <option value="apartment"<?php echo $v('property_type') === 'apartment' ? ' selected' : ''; ?>>Apartment</option>
            <option value="land"<?php echo $v('property_type') === 'land' ? ' selected' : ''; ?>>Land</option>
            <option value="commercial"<?php echo $v('property_type') === 'commercial' ? ' selected' : ''; ?>>Commercial</option>
          </select>
        </div>
        <div>
          <label for="listed_for">Listed for</label>
          <select id="listed_for" name="listed_for">
            <option value="rent"<?php echo $v('listed_for') === 'rent' ? ' selected' : ''; ?>>Rent</option>
            <option value="sale"<?php echo $v('listed_for') === 'sale' ? ' selected' : ''; ?>>Sale</option>
          </select>
        </div>
        <div>
          <label for="price">Price (<?php echo CURRENCY; ?>)</label>
          <input type="number" id="price" name="price" min="1" step="0.01" value="<?php echo $v('price'); ?>" required>
        </div>
      </div>

      <label for="location">Location</label>
      <input type="text" id="location" name="location" value="<?php echo $v('location'); ?>" required>

      <div class="field-row field-row-3">
        <div>
          <label for="bedrooms">Bedrooms</label>
          <input type="number" id="bedrooms" name="bedrooms" min="0" max="20" value="<?php echo $v('bedrooms', '0'); ?>">
        </div>
        <div>
          <label for="bathrooms">Bathrooms</label>
          <input type="number" id="bathrooms" name="bathrooms" min="0" max="20" value="<?php echo $v('bathrooms', '0'); ?>">
        </div>
        <div>
          <label for="status">Availability</label>
          <select id="status" name="status">
            <option value="available"<?php echo $v('status') === 'available' ? ' selected' : ''; ?>>Available</option>
            <option value="taken"<?php echo $v('status') === 'taken' ? ' selected' : ''; ?>>Taken</option>
          </select>
        </div>
      </div>

      <label for="description">Description</label>
      <textarea id="description" name="description" rows="5" required><?php echo $v('description'); ?></textarea>

      <label for="photo">Photo <small class="muted">(JPG, PNG or WebP, max 3MB)</small></label>
      <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
      <?php if ($property && $property['photo']): ?>
        <p class="muted">Current photo:</p>
        <img class="thumb-preview" src="<?php echo esc(property_thumb($property)); ?>" alt="">
      <?php endif; ?>

      <button class="btn" type="submit"><?php echo $property ? 'Save changes' : 'Publish property'; ?></button>
    </form>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
