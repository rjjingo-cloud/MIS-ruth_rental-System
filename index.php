<?php
require_once __DIR__ . '/config/db.php';

$featured = $pdo->query('SELECT p.*, u.name AS manager_name FROM properties p LEFT JOIN users u ON u.id = p.manager_id WHERE p.status = "available" ORDER BY p.created_at DESC LIMIT 3')->fetchAll();

$totalProperties = (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
$totalCustomers = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer"')->fetchColumn();

$slides = [];
foreach ($featured as $p) {
    $slides[] = [
        'image' => property_thumb($p),
        'kicker' => 'Featured property',
        'title' => $p['title'],
        'meta' => $p['location'] . ' · ' . property_price($p),
        'url' => 'property.php?id=' . (int) $p['id'],
        'cta' => 'View property',
    ];
}
if (!$slides) {
    $slides = [
        ['image' => ROOT_URL . 'assets/img/prop-house1.jpg', 'kicker' => 'Ruth Estates', 'title' => 'Find a place you will love.', 'meta' => 'Rentals and sales, done honestly across Kampala and beyond.', 'url' => 'properties.php', 'cta' => 'Browse properties'],
        ['image' => ROOT_URL . 'assets/img/prop-apartment1.jpg', 'kicker' => 'Ruth Estates', 'title' => 'Homes for every family.', 'meta' => 'Verified apartments, houses and bedsitters ready to view.', 'url' => 'properties.php', 'cta' => 'Browse properties'],
        ['image' => ROOT_URL . 'assets/img/prop-land1.jpg', 'kicker' => 'Ruth Estates', 'title' => 'Land and commercial plots.', 'meta' => 'Prime plots in growing neighbourhoods.', 'url' => 'properties.php', 'cta' => 'Browse properties'],
    ];
}

$page_title = 'Home';
include __DIR__ . '/includes/header.php';
?>

<section class="hero-slider" id="heroSlider" aria-label="Featured properties">
  <?php foreach ($slides as $i => $s): ?>
    <div class="slide<?php echo $i === 0 ? ' active' : ''; ?>" style="background-image:url('<?php echo esc($s['image']); ?>')">
      <div class="slide-content container">
        <p class="slide-kicker"><?php echo esc($s['kicker']); ?></p>
        <h1><?php echo esc($s['title']); ?></h1>
        <p class="slide-meta"><?php echo esc($s['meta']); ?></p>
        <div class="slide-actions">
          <a class="btn" href="<?php echo esc($s['url']); ?>"><?php echo esc($s['cta']); ?></a>
          <a class="btn btn-outline btn-light" href="properties.php">Browse all</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <button class="slider-arrow prev" type="button" aria-label="Previous slide">&#10094;</button>
  <button class="slider-arrow next" type="button" aria-label="Next slide">&#10095;</button>
  <div class="slider-dots">
    <?php foreach ($slides as $i => $s): ?>
      <button class="slider-dot<?php echo $i === 0 ? ' active' : ''; ?>" type="button" data-slide="<?php echo $i; ?>" aria-label="Go to slide <?php echo $i + 1; ?>"></button>
    <?php endforeach; ?>
  </div>
</section>

<div class="marquee">
  <div class="marquee-track">
    <?php for ($g = 0; $g < 2; $g++): ?>
      <div class="marquee-group"<?php echo $g ? ' aria-hidden="true"' : ''; ?>>
        <span><?php echo $totalProperties; ?> properties on our books</span><span class="sep">&bull;</span>
        <span><?php echo $totalCustomers; ?> registered customers</span><span class="sep">&bull;</span>
        <span>No viewing charges, ever</span><span class="sep">&bull;</span>
        <span>Verified listings in Kampala and beyond</span><span class="sep">&bull;</span>
        <span>Book a room online in minutes</span><span class="sep">&bull;</span>
        <span>Rentals and sales, done honestly</span><span class="sep">&bull;</span>
      </div>
    <?php endfor; ?>
  </div>
</div>

<h2 class="section-title">Recently added</h2>
<?php if (!$featured): ?>
  <p>No properties have been listed yet. Check back soon.</p>
<?php else: ?>
<div class="cards">
  <?php foreach ($featured as $p): ?>
  <a class="card" href="property.php?id=<?php echo (int) $p['id']; ?>">
    <img class="thumb" src="<?php echo esc(property_thumb($p)); ?>" alt="">
    <div class="card-body">
      <p class="price"><?php echo esc(property_price($p)); ?></p>
      <h3><?php echo esc($p['title']); ?></h3>
      <p class="muted"><?php echo esc($p['location']); ?></p>
      <p class="tags">
        <?php echo status_badge($p['listed_for']); ?> <?php echo status_badge($p['status']); ?>
      </p>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<h2 class="section-title">How it works</h2>
<div class="steps">
  <div class="step"><b>1.</b> Create a free customer account with your name, email and phone number.</div>
  <div class="step"><b>2.</b> Browse our listings and request a viewing on any property you like.</div>
  <div class="step"><b>3.</b> Our office approves your viewing and gets back to you right here on the site or by phone.</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
