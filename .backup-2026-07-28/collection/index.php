<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$type = sanitize_text((string) ($_GET['type'] ?? ''));
$slug = sanitize_text((string) ($_GET['slug'] ?? ''));

if (!in_array($type, ['shape', 'style'], true) || $slug === '') {
    redirect(resolve_link('/shop/'));
}

$redirectQuery = $type === 'shape'
    ? '/shop/?type=Ring&shape=' . rawurlencode($slug)
    : '/shop/?type=Ring&style=' . rawurlencode($slug);
redirect(resolve_link($redirectQuery));

// Meta variables
$collectionName = '';
$heroDescription = '';

if ($type === 'shape') {
    $shapes = available_diamond_shapes();
    if (!isset($shapes[$slug])) {
        redirect(resolve_link('/shop/'));
    }
    $collectionName = $shapes[$slug] . ' Cut Engagement Rings';
    $heroDescription = "Discover our curated collection of stunning {$shapes[$slug]} engagement rings. Characterised by brilliant sparkle and elegant proportions, explore the settings that perfectly complement a {$shapes[$slug]} centre stone.";
} else {
    $styles = available_ring_styles();
    if (!isset($styles[$slug])) {
        redirect(resolve_link('/shop/'));
    }
    $collectionName = $styles[$slug] . ' Engagement Rings';
    $heroDescription = "Explore our premium selection of {$styles[$slug]} engagement rings. Known for timeless beauty and expert craftsmanship, find the perfect design to symbolise your enduring love.";
}

// Filtering logic
$products = catalog_products();
$content = site_content();
$productColors = $content['catalog_meta']['colors'] ?? [];

$filters = [
    'color' => sanitize_text((string) ($_GET['color'] ?? '')),
    'sort' => sanitize_text((string) ($_GET['sort'] ?? 'featured')),
];

$matchedProducts = array_values(array_filter($products, static function (array $product) use ($type, $slug, $filters): bool {
    // Check if product belongs to this collection
    if ($type === 'shape') {
        $shapes = $product['diamondShapes'] ?? [];
        if (!in_array($slug, $shapes, true)) {
            return false;
        }
    } else {
        $styles = $product['styles'] ?? [];
        if (!in_array($slug, $styles, true)) {
            return false;
        }
    }

    // Apply secondary filters
    if ($filters['color'] !== '' && strcasecmp((string) ($product['color'] ?? ''), $filters['color']) !== 0) {
        return false;
    }

    return true;
}));

// Apply sorting
if ($filters['sort'] === 'price-low') {
    usort($matchedProducts, static fn($a, $b) => product_price_value($a) <=> product_price_value($b));
} elseif ($filters['sort'] === 'price-high') {
    usort($matchedProducts, static fn($a, $b) => product_price_value($b) <=> product_price_value($a));
} elseif ($filters['sort'] === 'name-asc') {
    usort($matchedProducts, static fn($a, $b) => strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
} elseif ($filters['sort'] === 'name-desc') {
    usort($matchedProducts, static fn($a, $b) => strcasecmp((string)($b['name'] ?? ''), (string)($a['name'] ?? '')));
}

$pageTitle = $collectionName . ' - ' . SITE_NAME;
$bodyClass = 'collection-page';

$buildCollectionUrl = static function (array $changes = []) use ($filters): string {
    $query = array_merge($filters, $changes);
    $query = array_filter($query, static fn (mixed $value): bool => is_string($value) && $value !== '');
    return empty($query) ? '?' : '?' . http_build_query($query);
};

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
  /* Base structural reset for the specific design requested */
  body.collection-page {
    background:
      linear-gradient(180deg, #f7f9fb 0%, #f4f7fa 34%, #f2f5f8 100%);
  }
  .collection-hero {
    background: transparent;
    padding: 60px 20px 40px;
    text-align: center;
  }
  .collection-hero h1 {
    font-family: var(--serif);
    font-size: clamp(2.8rem, 6vw, 4.5rem);
    color: #2b3330;
    margin-bottom: 20px;
    font-weight: 600;
  }
  .collection-hero p {
    max-width: 750px;
    margin: 0 auto 40px;
    color: #5c6b63;
    font-size: 1.15rem;
    line-height: 1.6;
  }

  /* Step Bar overrides */
  .step-bar {
    display: inline-flex;
    justify-content: center;
    gap: 15px;
    padding: 10px 30px;
    background: transparent;
  }
  .step-item {
    background: transparent;
    border: none;
    box-shadow: none;
    color: #a4b3ab;
    font-weight: 500;
    font-size: 1.05rem;
    padding: 5px 15px;
    position: relative;
  }
  .step-item span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    margin-right: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
  }
  .step-item.is-active {
    color: #fff;
    background: #004531;
    border-radius: 40px;
    padding: 10px 25px;
    box-shadow: 0 10px 25px rgba(0, 69, 49, 0.2);
  }
  .step-item.is-active span {
    background: rgba(255,255,255,0.2);
    color: #fff;
  }
  .step-item:not(.is-active) span {
    background: #e2e8e5;
    color: #a4b3ab;
  }
  
  /* Filter bar directly above products */
  .collection-filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    border-bottom: 1px solid #eaeaea;
    margin-bottom: 30px;
    background: transparent;
  }
  .filter-group {
    display: flex;
    gap: 20px;
    align-items: center;
  }
  .filter-label {
    font-weight: 600;
    color: #2b3330;
    margin-right: 10px;
    font-size: 0.95rem;
  }
  .filter-dropdown {
    background: transparent;
    border: none;
    font-size: 0.95rem;
    color: #2b3330;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    outline: none;
  }
  .filter-dropdown i {
    font-size: 0.8rem;
    color: #2b3330;
  }

  /* Adjust grid layout */
  .shop-shell {
    padding: 0 20px 80px;
  }
  .shop-layout {
    display: block; /* Remove sidebar */
  }
  .shop-sidebar {
    display: none; /* Hide old sidebar */
  }
  .shop-results {
    width: 100%;
  }
  .shop-results-bar {
    display: none; /* Hide old bar */
  }
  
  /* Modern clean product cards */
  .shop-product-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
  }
  .prod-card {
    background: #fff;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    border: 1px solid #f0f0f0;
    transition: box-shadow 0.3s ease;
    box-shadow: none;
  }
  .prod-card:hover {
    box-shadow: 0 10px 30px rgba(0,0,0,0.04);
  }
  .prod-img-box {
    height: 250px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    background: #fff;
  }
  .prod-img-box img {
    max-height: 100%;
    object-fit: contain;
  }
  .prod-name {
    font-weight: 600;
    font-size: 1.1rem;
    color: #2b3330;
    margin: 10px 0 5px;
  }
  .prod-cat {
    color: #8da197;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  .prod-prices {
    font-size: 1.2rem;
    color: #2b3330;
    font-weight: 600;
  }
  .prod-prices .new {
    color: #2b3330;
  }
  /* Breadcrumbs overrides */
  .store-breadcrumbs {
    background: rgba(249, 251, 253, 0.92);
    padding: 18px 0;
    border-bottom: 1px solid #e6ebf0;
    font-size: 0.8rem;
    color: #8da197;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }
  .store-breadcrumbs a {
    color: #2b3330;
    text-decoration: none;
    transition: color 0.2s;
    font-weight: 500;
  }
  .store-breadcrumbs a:hover {
    color: #c9a96e;
  }
  .store-breadcrumbs span {
    margin: 0 12px;
    color: #ccd4d0;
  }
  .store-breadcrumbs strong {
    color: #8da197;
    font-weight: 400;
  }
</style>

<div class="store-breadcrumbs">
  <div class="container">
    <a href="<?= h(resolve_link('/')) ?>">Home</a>
    <span>/</span>
    <a href="<?= h(resolve_link('/shop/')) ?>">Engagement Rings</a>
    <span>/</span>
    <strong><?= h($collectionName) ?></strong>
  </div>
</div>

<style>
.premium-step-banner {
    background: rgba(252, 253, 255, 0.96);
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 70px;
    margin-bottom: 50px;
    font-family: var(--sans);
    border-top: 1px solid #e6ebf0;
    border-bottom: 1px solid #e6ebf0;
  }
  .step-banner-item {
    display: flex;
    align-items: center;
    padding: 0 30px;
    font-size: 0.95rem;
    font-weight: 500;
    letter-spacing: 0.08em;
    color: #8da197;
    text-transform: uppercase;
  }
  .step-banner-item.is-active {
    color: #004531;
    font-weight: 600;
  }
  .step-banner-item.start-text {
    font-style: italic;
    font-family: var(--serif);
    font-size: 1.4rem;
    color: #2b3330;
    padding-right: 40px;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 400;
  }
  .step-banner-item span {
    font-size: 1.4rem;
    margin-right: 12px;
    font-family: var(--serif);
    color: inherit;
    font-weight: 400;
  }
  .step-banner-item.is-active span {
    color: #c9a96e;
  }
  .step-separator {
    width: 1px;
    height: 30px;
    background: #e6ebf0;
    transform: rotate(15deg);
  }
  
  .style-selector-row {
    display: flex;
    justify-content: center;
    gap: 35px;
    margin-bottom: 60px;
    flex-wrap: wrap;
    max-width: 100%;
  }
  .style-selector-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #666;
    opacity: 0.7;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    width: 110px;
  }
  .style-selector-item:hover, .style-selector-item.is-active {
    opacity: 1;
    transform: translateY(-4px);
    color: #111;
  }
  .style-selector-item img {
    width: 100px;
    height: 100px;
    object-fit: contain;
    border-radius: 50%;
    margin-bottom: 15px;
    border: 1px solid #e6ebf0;
    background: linear-gradient(180deg, #ffffff 0%, #f7f9fc 100%);
    padding: 15px;
    box-shadow: 0 4px 15px rgba(27, 45, 60, 0.04);
    transition: all 0.3s ease;
  }
  .style-selector-item:hover img {
    box-shadow: 0 8px 25px rgba(27, 45, 60, 0.08);
    border-color: #dbe3ea;
  }
  .style-selector-item.is-active img {
    border-color: #004531;
    box-shadow: 0 0 0 1px #004531, 0 8px 25px rgba(0,0,0,0.08);
  }
  .style-selector-item span {
    font-size: 0.75rem;
    font-weight: 500;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    transition: color 0.3s ease;
  }
  .style-selector-item.is-active span {
    color: #004531;
    font-weight: 600;
  }
</style>

<section class="collection-hero reveal-in" style="padding-bottom: 0;">
  <div class="container">
    <h1><?= h($collectionName) ?></h1>
    <p><?= h($heroDescription) ?></p>
  </div>
</section>

<div class="premium-step-banner">
  <div class="step-banner-item start-text">Start With A Ring Design</div>
  <div class="step-separator"></div>
  <div class="step-banner-item is-active">
    <span>1</span> Select Ring Design
  </div>
  <div class="step-separator"></div>
  <div class="step-banner-item">
    <span>2</span> Select Diamond
  </div>
  <div class="step-separator"></div>
  <div class="step-banner-item">
    <span>3</span> Complete Ring
  </div>
</div>

<div class="container">
  <div class="style-selector-row">
    <?php 
    if ($type === 'style') {
        $allStyles = available_ring_styles();
        // Map keys to images (ideally these would come from admin site settings, but for now we'll use consistent mapping)
        $styleImages = [
            'solitaire' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product3.jpg',
            'sidestones' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product1.jpg',
            'halo' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product10.jpg',
            'three-stone' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product8.jpg',
            'toi-et-moi' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product2.jpg',
            'vintage' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product4.jpg',
            'hidden-halo' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product3.jpg',
            'classic' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product1.jpg',
            'pave' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product10.jpg',
            'unique' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product5.jpg',
        ];
        foreach ($allStyles as $itemKey => $itemName): 
            $isActive = ($slug === $itemKey);
            $link = resolve_link('/engagement-rings/style/' . $itemKey);
            $img = $styleImages[$itemKey] ?? 'https://htmldemo.net/monsta/monsta/assets/img/product/product1.jpg';
        ?>
          <a href="<?= h($link) ?>" class="style-selector-item <?= $isActive ? 'is-active' : '' ?>">
            <img src="<?= h($img) ?>" alt="<?= h($itemName) ?>">
            <span><?= h($itemName) ?></span>
          </a>
        <?php endforeach; 
    } else {
        $allShapes = available_diamond_shapes();
        $shapeImages = [
            'round' => 'https://qs.imgix.net/images/round-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200',
            'oval' => 'https://qs.imgix.net/images/oval-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200',
            'pear' => 'https://qs.imgix.net/images/pear-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200',
            'cushion' => 'https://qs.imgix.net/images/cushion-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200',
            'emerald' => 'https://qs.imgix.net/images/emerald-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200',
            'princess' => 'https://qs.imgix.net/images/princess-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200',
            'marquise' => 'https://qs.imgix.net/images/marquise-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200',
            'radiant' => 'https://qs.imgix.net/images/radiant-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200',
            'asscher' => 'https://qs.imgix.net/images/asscher-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200',
            'heart' => 'https://qs.imgix.net/images/heart-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200',
        ];
        foreach ($allShapes as $itemKey => $itemName): 
            $isActive = ($slug === $itemKey);
            $link = resolve_link('/engagement-rings/shape/' . $itemKey);
            $img = $shapeImages[$itemKey] ?? 'https://qs.imgix.net/images/round-diamond-shape-icon.png?auto=format&q=60&fit=crop&crop=focalpoint&fp-x=0.5&fp-y=0.5&w=200&h=200';
        ?>
          <a href="<?= h($link) ?>" class="style-selector-item <?= $isActive ? 'is-active' : '' ?>">
            <img src="<?= h($img) ?>" alt="<?= h($itemName) ?>" style="background: #fff; padding: 10px;">
            <span><?= h($itemName) ?></span>
          </a>
        <?php endforeach;
    }
    ?>
  </div>
</div>

<section class="shop-shell">
  <div class="container shop-layout">
    <aside class="shop-sidebar">
      <div class="shop-panel">
        <div class="shop-panel-head">
          <span class="sec-hdr-icon"><i class="fas fa-sliders"></i></span>
          <div>
            <h3>Refine View</h3>
          </div>
        </div>
        <form method="get" action="" class="shop-filter-form">
          <input type="hidden" name="type" value="<?= h($type) ?>">
          <input type="hidden" name="slug" value="<?= h($slug) ?>">
          <label class="shop-field">
            <span>Metal Color</span>
            <select name="color">
              <option value="">All Metals</option>
              <?php foreach ($productColors as $color): ?>
                <option value="<?= h($color) ?>" <?= $filters['color'] === $color ? 'selected' : '' ?>><?= h($color) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="shop-field">
            <span>Sort By</span>
            <select name="sort">
              <option value="featured" <?= $filters['sort'] === 'featured' ? 'selected' : '' ?>>Featured</option>
              <option value="price-low" <?= $filters['sort'] === 'price-low' ? 'selected' : '' ?>>Price: Low to High</option>
              <option value="price-high" <?= $filters['sort'] === 'price-high' ? 'selected' : '' ?>>Price: High to Low</option>
            </select>
          </label>
          <div class="shop-filter-actions">
            <button type="submit" class="btn-shop">Apply</button>
            <a href="?type=<?= h($type) ?>&slug=<?= h($slug) ?>" class="btn-outline">Clear</a>
          </div>
        </form>
      </div>
    </aside>

    <div class="shop-results">
      <div class="collection-filter-bar">
        <form method="get" action="" style="display: flex; gap: 30px; margin: 0; align-items: center; width: 100%; justify-content: center;">
          <div class="filter-group">
            <span class="filter-label">Filter By:</span>
            <select name="color" class="filter-dropdown" onchange="this.form.submit()">
              <option value="">Metal ▼</option>
              <?php foreach ($productColors as $color): ?>
                <option value="<?= h($color) ?>" <?= $filters['color'] === $color ? 'selected' : '' ?>><?= h($color) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="sort" class="filter-dropdown" onchange="this.form.submit()">
              <option value="featured" <?= $filters['sort'] === 'featured' ? 'selected' : '' ?>>Sort By ▼</option>
              <option value="price-low" <?= $filters['sort'] === 'price-low' ? 'selected' : '' ?>>Price: Low to High</option>
              <option value="price-high" <?= $filters['sort'] === 'price-high' ? 'selected' : '' ?>>Price: High to Low</option>
            </select>
          </div>
        </form>
      </div>
      
      <div style="margin-bottom: 25px; padding-bottom: 10px; border-bottom: 1px solid #eaeaea; display: flex; justify-content: space-between; align-items: center;">
      
          <div style="font-weight: 500; font-size: 0.9rem; color: #333; text-transform: uppercase; letter-spacing: 0.05em;">
             <?= count($matchedProducts) ?> Results
          </div>
          <?php if (!empty($filters['color']) || $filters['sort'] !== 'featured'): ?>
            <a href="<?= h(resolve_link('/engagement-rings/' . $type . '/' . $slug)) ?>" style="font-size: 0.85rem; color: #a4b3ab; text-decoration: none;">Reset Filters <i class="fas fa-times"></i></a>
          <?php endif; ?>
      </div>

      <?php if ($matchedProducts === []): ?>
        <div class="shop-empty-state" style="background: #fbfcfb; border: 1px solid #edf0ed; border-radius: 12px; padding: 60px 30px; text-align: center; margin: 40px 0;">
          <h3 style="font-family: var(--serif); font-size: 2.2rem; color: #2b3330; margin-bottom: 15px; font-weight: 600;">No designs match your filters</h3>
          <p style="color: #6a7c73; font-size: 1.05rem; max-width: 500px; margin: 0 auto 30px; line-height: 1.6;">Please adjust your selections to see more options.</p>
          <a class="btn-shop" href="?type=<?= h($type) ?>&slug=<?= h($slug) ?>" style="background: #b18861; color: #fff; border: none; font-size: 0.8rem; letter-spacing: 0.15em; padding: 12px 30px; border-radius: 4px; text-transform: uppercase; font-weight: 600;">Clear Filters</a>
        </div>
      <?php else: ?>
        <div class="shop-product-grid">
          <?php foreach ($matchedProducts as $product): ?>
            <?php 
              $extraParams = [];
              if ($type === 'shape') {
                  $extraParams['shape'] = $slug;
              }
              render_product_card($product, $extraParams);
            ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
