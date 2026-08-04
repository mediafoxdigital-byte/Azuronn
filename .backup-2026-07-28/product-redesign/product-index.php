<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$productId = clean_string($_GET['id'] ?? '', 80);
$product = product_by_id($productId);
if ($product === null || strtolower((string) ($product['status'] ?? 'active')) !== 'active') {
    site_flash_set('error', 'Product not found.');
    redirect(resolve_link('/shop/'));
}

$options = product_option_data($product);
$pageFlash = site_flash_pull();
$customer = current_customer();
$isWishlisted = customer_has_wishlist_product($customer, (string) ($product['id'] ?? ''));
$requestSelection = [
    'color' => $_POST['color'] ?? $_GET['color'] ?? '',
    'size' => $_POST['size'] ?? $_GET['size'] ?? '',
    'diamond_shape' => $_POST['diamond_shape'] ?? $_GET['diamond_shape'] ?? $_GET['shape'] ?? '',
    'metal' => $_POST['metal'] ?? $_GET['metal'] ?? '',
    'band_claw_metal' => $_POST['band_claw_metal'] ?? $_GET['band_claw_metal'] ?? '',
    'delivery_option' => $_POST['delivery_option'] ?? $_GET['delivery_option'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $pageFlash = ['type' => 'error', 'message' => 'Session expired. Please try again.'];
    } else {
        $action = clean_string($_POST['action'] ?? 'add-to-cart', 40);
        if ($action === 'toggle-wishlist') {
            if ($customer === null) {
                site_flash_set('error', 'Sign in to save products to your wishlist.');
                redirect(resolve_link('/account/login/?next=' . urlencode(product_url($product))));
            }

            $wishlistResult = customer_toggle_wishlist((string) $product['id']);
            site_flash_set(($wishlistResult['ok'] ?? false) ? 'success' : 'error', (string) ($wishlistResult['message'] ?? 'Unable to update wishlist.'));
            redirect(product_url($product));
        } else {
            $quantity = clean_int($_POST['quantity'] ?? 1, 1, 99);
            $selection = [
                'color' => clean_string($_POST['color'] ?? '', 80),
                'size' => clean_string($_POST['size'] ?? '', 40),
                'diamond_shape' => clean_string($_POST['diamond_shape'] ?? '', 40),
                'metal' => clean_string($_POST['metal'] ?? '', 40),
                'band_claw_metal' => clean_string($_POST['band_claw_metal'] ?? '', 60),
                'delivery_option' => clean_string($_POST['delivery_option'] ?? '', 30),
            ];
            $result = cart_add_item((string) $product['id'], $quantity, $selection);
            if ($result['ok'] ?? false) {
                if (clean_string($_POST['intent'] ?? '', 20) === 'buy-now') {
                    $target = customer_is_logged_in() ? '/checkout/' : '/account/login/?next=' . urlencode('/checkout/');
                    site_flash_set('success', 'Product added. Continue to checkout.');
                    redirect(resolve_link($target));
                }
                site_flash_set('success', 'Product added to cart.');
                redirect(resolve_link('/cart/'));
            }
            $pageFlash = ['type' => 'error', 'message' => (string) ($result['message'] ?? 'Unable to add the product.')];
        }
    }
}

$relatedProducts = array_values(array_filter(catalog_products(), static function (array $item) use ($product): bool {
    return (string) ($item['id'] ?? '') !== (string) ($product['id'] ?? '') && (string) ($item['product_type'] ?? '') === (string) ($product['product_type'] ?? '');
}));
// For rings, keep "You May Also Like" inside the same section (and gender for
// wedding rings) so engagement rings are not suggested on a men's band. Fall
// back to the type-wide list if the scoped set is too small to be useful.
$productTaxonomy = product_ring_taxonomy($product);
if ($productTaxonomy['category'] !== '') {
    $scopedRelated = array_values(array_filter($relatedProducts, static function (array $item) use ($productTaxonomy): bool {
        $taxonomy = product_ring_taxonomy($item);
        if ($taxonomy['category'] !== $productTaxonomy['category']) {
            return false;
        }
        if ($productTaxonomy['category'] === 'wedding' && $productTaxonomy['gender'] !== '' && $taxonomy['gender'] !== '') {
            return $taxonomy['gender'] === $productTaxonomy['gender'] || $taxonomy['gender'] === 'unisex' || $productTaxonomy['gender'] === 'unisex';
        }
        return true;
    }));
    if (count($scopedRelated) >= 2) {
        $relatedProducts = $scopedRelated;
    }
}
$relatedProducts = array_slice($relatedProducts, 0, 4);

$productGallery = array_values(array_filter(array_map(
    static fn (mixed $item): string => clean_image((string) $item),
    (array) ($options['gallery'] ?? [])
), static fn (string $item): bool => $item !== ''));
if ($productGallery === []) {
    $productGallery = array_values(array_filter(array_map(
        static fn (mixed $item): string => clean_image((string) $item),
        [
            $product['default_image'] ?? '',
            $product['hover_image'] ?? '',
            $product['popup_image'] ?? '',
        ]
    ), static fn (string $item): bool => $item !== ''));
}
$productPrimaryMedia = $productGallery[0] ?? clean_image((string) ($product['default_image'] ?? ''));
$metaMetalOptions = $options['metal_options'] ?? [];
$metaMetalLabels = $metaMetalOptions !== []
    ? array_values(array_filter(array_map(static fn (array $item): string => clean_string((string) ($item['label'] ?? ''), 120), $metaMetalOptions), static fn (string $item): bool => $item !== ''))
    : array_values(array_filter(array_map(static fn (mixed $item): string => clean_string((string) $item, 120), (array) ($options['materials'] ?? [])), static fn (string $item): bool => $item !== ''));
$productMediaMime = static function (string $path): string {
    $extension = strtolower(pathinfo((string) (parse_url($path, PHP_URL_PATH) ?? $path), PATHINFO_EXTENSION));

    return match ($extension) {
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        'mov' => 'video/quicktime',
        'm4v' => 'video/x-m4v',
        default => 'video/mp4',
    };
};
$renderProductMedia = static function (string $path, string $alt, string $className, array $attributes = [], bool $isStage = false) use ($productMediaMime): string {
    $resolvedPath = clean_image($path);
    if ($resolvedPath === '') {
        return '';
    }

    $attributePairs = [];
    foreach ($attributes as $attribute => $value) {
        if ($value === null) {
            continue;
        }

        if ($value === true) {
            $attributePairs[] = h((string) $attribute);
            continue;
        }

        $attributePairs[] = h((string) $attribute) . '="' . h((string) $value) . '"';
    }
    $attributeHtml = $attributePairs !== [] ? ' ' . implode(' ', $attributePairs) : '';

    if (media_asset_type($resolvedPath) === 'video') {
        $videoAttributes = $attributeHtml;
        if ($isStage) {
            $videoAttributes .= ' controls autoplay muted loop';
        } else {
            $videoAttributes .= ' muted aria-hidden="true"';
        }

        return '<video class="' . h($className) . '"' . $videoAttributes . ' playsinline preload="metadata"><source src="' . h($resolvedPath) . '" type="' . h($productMediaMime($resolvedPath)) . '"></video>';
    }

    return '<img class="' . h($className) . '" src="' . h($resolvedPath) . '" alt="' . h($alt) . '"' . $attributeHtml . '>';
};

$pageTitle = (string) ($product['name'] ?? 'Product') . ' - ' . SITE_NAME;
$bodyClass = 'product-page';
require_once dirname(__DIR__) . '/includes/header.php';

$isRingProduct = $options['is_ring_product'] ?? false;
$isMatrixProduct = $options['is_matrix_product'] ?? false;
// Explicit ring section wins; product_ring_taxonomy() keeps the legacy
// diamond-shape heuristic as inference for un-migrated products, so a women's
// wedding band WITH diamonds is no longer routed into the diamond builder.
$isEngagementRing = $isRingProduct && $productTaxonomy['category'] === 'engagement';
$selectedVariant = product_normalize_selection($product, $requestSelection);
$selectedInventoryStatus = product_inventory_status($product, $selectedVariant);
$selectedIsOutOfStock = !empty($selectedInventoryStatus['out_of_stock']);
$selectedMetalBandOptions = [];
foreach ((array) $metaMetalOptions as $metalOption) {
    if ((string) ($metalOption['value'] ?? '') !== (string) ($selectedVariant['metal'] ?? '')) {
        continue;
    }

    $selectedMetalBandOptions = array_values(array_filter(
        (array) ($metalOption['band_options'] ?? []),
        static fn (mixed $item): bool => is_array($item) && clean_string((string) ($item['value'] ?? ''), 80) !== ''
    ));
    break;
}
if ($selectedMetalBandOptions === []) {
    $selectedMetalBandOptions = array_values(array_filter(
        (array) ($options['band_claw_metal_options'] ?? []),
        static fn (mixed $item): bool => is_array($item) && clean_string((string) ($item['value'] ?? ''), 80) !== ''
    ));
}
$selectedDeliveryMeta = '';
foreach ((array) ($options['delivery_options'] ?? []) as $deliveryOption) {
    if ((string) ($deliveryOption['value'] ?? '') !== (string) ($selectedVariant['delivery_option'] ?? '')) {
        continue;
    }

    $selectedDeliveryMeta = trim(implode(' / ', array_filter([
        clean_string((string) ($deliveryOption['badge'] ?? ''), 40),
        clean_string((string) ($deliveryOption['label'] ?? ''), 120),
    ], static fn (string $item): bool => $item !== '')));
    break;
}
if ($selectedDeliveryMeta === '') {
    $selectedDeliveryMeta = 'Insured premium shipping';
}

$diamondShapeVisualMap = [];
foreach ((array) (site_content()['diamond_shapes']['items'] ?? []) as $shapeItem) {
    if (!is_array($shapeItem)) {
        continue;
    }
    $shapeKey = strtolower(clean_string((string) ($shapeItem['name'] ?? ''), 40));
    if ($shapeKey === '') {
        continue;
    }
    $shapeVisual = clean_image((string) ($shapeItem['icon_image'] ?? $shapeItem['image'] ?? ''));
    if ($shapeVisual !== '') {
        $diamondShapeVisualMap[$shapeKey] = $shapeVisual;
    }
}

$optionToneFor = static function (string $label): string {
    $normalized = strtolower($label);
    return match (true) {
        str_contains($normalized, 'rose') => 'rose',
        str_contains($normalized, 'yellow') => 'yellow',
        str_contains($normalized, 'platinum') => 'platinum',
        str_contains($normalized, 'white') => 'white',
        default => 'forest',
    };
};
?>

<?php if ($isEngagementRing): ?>
<div class="container">
    <div class="step-bar">
      <div class="step-item is-active">
        <span>1</span> Select Ring Design
      </div>
      <div class="step-item">
        <span>2</span> Select Diamond
      </div>
      <div class="step-item">
        <span>3</span> Complete Ring
      </div>
    </div>
</div>
<?php endif; ?>

<section class="product-shell reveal-in">
  <div class="container">
    <div class="product-layout">
      <div class="product-gallery">
        <div class="product-gallery-frame">
          <div class="product-gallery-stage" data-product-stage data-product-alt="<?= h((string) ($product['name'] ?? 'Product')) ?>" data-initial-gallery="<?= h((string) json_encode($productGallery, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">
            <?= $renderProductMedia($productPrimaryMedia, (string) ($product['name'] ?? 'Product'), 'product-gallery-media', ['data-product-main-media' => 'true'], true) ?>
          </div>
        </div>
        <div class="product-thumb-row" data-product-thumbs>
          <?php foreach ($productGallery as $index => $image): ?>
            <button type="button" class="product-thumb <?= $index === 0 ? 'is-active' : '' ?>" data-product-thumb data-media-src="<?= h($image) ?>" data-media-type="<?= h(media_asset_type($image)) ?>" aria-label="<?= h('View media ' . ($index + 1)) ?>">
              <?= $renderProductMedia($image, (string) ($product['name'] ?? 'Product'), 'product-thumb-media', [], false) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="product-copy product-copy-panel">
        <div class="product-topbar">
          <span class="product-eyebrow">
            <?php if ($productTaxonomy['category'] !== ''): ?>
              <a href="<?= h(resolve_link('/shop/?' . http_build_query(['type' => 'Ring']))) ?>">Rings</a>
              / <a href="<?= h(resolve_link('/shop/?' . http_build_query(['type' => 'Ring', 'ring_category' => $productTaxonomy['category']]))) ?>"><?= h(ring_section_label($productTaxonomy['category'])) ?></a>
              <?php if ($productTaxonomy['gender'] !== ''): ?>
                / <a href="<?= h(resolve_link('/shop/?' . http_build_query(['type' => 'Ring', 'ring_category' => 'wedding', 'gender' => $productTaxonomy['gender']]))) ?>"><?= h($productTaxonomy['gender'] === 'mens' ? "Men's" : ($productTaxonomy['gender'] === 'womens' ? "Women's" : 'Unisex')) ?></a>
              <?php endif; ?>
            <?php else: ?>
              <?= h($product['product_type']) ?> / <?= h($product['category']) ?>
            <?php endif; ?>
          </span>
          <form method="post" action="<?= h(product_url($product)) ?>" class="product-wishlist-form product-wishlist-form-inline">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="toggle-wishlist">
            <button type="submit" class="wishlist-toggle-inline <?= $isWishlisted ? 'is-active' : '' ?>">
              <i class="<?= $isWishlisted ? 'fas' : 'far' ?> fa-heart"></i>
              <span><?= $isWishlisted ? 'Saved in Wishlist' : 'Save to Wishlist' ?></span>
            </button>
          </form>
        </div>
        <h1><?= h($product['name']) ?></h1>
        <div class="product-price-line">
          <?php if (($product['old_price'] ?? '') !== ''): ?><span class="old"><?= h($product['old_price']) ?></span><?php endif; ?>
          <strong id="live-product-price" data-original-price="<?= h($product['new_price']) ?>"><?= h($product['new_price']) ?></strong>
        </div>
        <?php $displayDesc = !empty($options['metal_description']) ? $options['metal_description'] : $product['description']; ?>
        <p class="product-description" id="live-product-desc" data-base-desc="<?= h($product['description']) ?>"><?= h($displayDesc) ?></p>

        <?php if ($pageFlash !== null): ?>
          <div class="store-flash <?= h($pageFlash['type']) ?>"><?= h($pageFlash['message']) ?></div>
        <?php endif; ?>
        <?php if ($selectedIsOutOfStock): ?>
          <div class="store-flash error product-stock-flash" data-stock-flash>
            This ring selection is currently out of stock.
          </div>
        <?php endif; ?>

        <div class="product-highlights"<?= $options['features'] === [] ? ' hidden' : '' ?> data-base-features="<?= h((string) json_encode(array_values(array_filter(array_map(static fn (mixed $item): string => clean_string((string) $item, 160), (array) ($options['features'] ?? [])), static fn (string $item): bool => $item !== '')), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">
          <?php foreach ($options['features'] as $feature): ?>
            <div class="product-highlight-chip">
              <i class="fas fa-check"></i>
              <span><?= h($feature) ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <form method="<?= $isEngagementRing ? 'get' : 'post' ?>" action="<?= h($isEngagementRing ? resolve_link('/diamond/index.php') : product_url($product)) ?>" class="product-form">
          <?php if (!$isEngagementRing): ?>
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="add-to-cart">
          <?php else: ?>
            <input type="hidden" name="product_id" value="<?= h($product['id']) ?>">
          <?php endif; ?>
          
          <?php if ($isEngagementRing): ?>
            <div class="product-option-block">
              <span>Diamond Shape</span>
              <div class="option-card-grid option-card-grid-shapes">
                <?php foreach ($options['diamond_shapes'] as $shape): ?>
                  <?php
                  $shapeValue = (string) ($shape['value'] ?? '');
                  $shapeLabel = (string) ($shape['label'] ?? '');
                  $shapeVisual = $diamondShapeVisualMap[strtolower($shapeValue)] ?? '';
                  ?>
                  <label class="option-card option-card-shape">
                    <input type="radio" name="shape" value="<?= h((string) $shape['value']) ?>" <?= $selectedVariant['diamond_shape'] === (string) $shape['value'] ? 'checked' : '' ?>>
                    <span class="option-card-shape-media">
                      <?php if ($shapeVisual !== ''): ?><img src="<?= h($shapeVisual) ?>" alt="<?= h($shapeLabel) ?> diamond"><?php endif; ?>
                    </span>
                    <span class="option-card-shape-copy">
                      <strong><?= h($shapeLabel) ?></strong>
                      <small>Centre stone shape</small>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!$isMatrixProduct): ?>
          <div class="product-option-block">
            <span><?= h((string) ($options['color_label'] ?? 'Color')) ?></span>
            <?php if (($options['color_display'] ?? 'compact') === 'jewellery-metals'): ?>
              <div class="option-card-grid option-card-grid-jewellery">
                <?php foreach (($options['color_choices'] ?? []) as $choice): ?>
                  <label class="option-card option-card-jewel tone-<?= h((string) ($choice['tone'] ?? 'white')) ?>">
                    <input type="radio" name="color" value="<?= h((string) ($choice['value'] ?? '')) ?>" <?= $selectedVariant['color'] === (string) ($choice['value'] ?? '') ? 'checked' : '' ?>>
                    <span class="option-card-jewel-orb"><em><?= h((string) ($choice['kicker'] ?? '')) ?></em></span>
                    <span class="option-card-jewel-meta"><?= h((string) ($choice['label'] ?? '')) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="option-card-grid option-card-grid-colors">
                <?php foreach ($options['colors'] as $index => $color): ?>
                  <label class="option-card option-card-compact">
                    <input type="radio" name="color" value="<?= h($color) ?>" <?= $selectedVariant['color'] === $color ? 'checked' : '' ?>>
                    <span><?= h($color) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if ($isRingProduct && $isMatrixProduct && !$isEngagementRing): ?>
            <div class="product-option-block">
              <span>Diamond Shape</span>
              <div class="option-card-grid option-card-grid-shapes">
                <?php foreach ($options['diamond_shapes'] as $shape): ?>
                  <?php
                  $shapeValue = (string) ($shape['value'] ?? '');
                  $shapeLabel = (string) ($shape['label'] ?? '');
                  $shapeVisual = $diamondShapeVisualMap[strtolower($shapeValue)] ?? '';
                  ?>
                  <label class="option-card option-card-shape">
                    <input type="radio" name="diamond_shape" value="<?= h((string) $shape['value']) ?>" <?= $selectedVariant['diamond_shape'] === (string) $shape['value'] ? 'checked' : '' ?>>
                    <span class="option-card-shape-media">
                      <?php if ($shapeVisual !== ''): ?><img src="<?= h($shapeVisual) ?>" alt="<?= h($shapeLabel) ?> diamond"><?php endif; ?>
                    </span>
                    <span class="option-card-shape-copy">
                      <strong><?= h($shapeLabel) ?></strong>
                      <small>Selected for your centre stone styling</small>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($isMatrixProduct): ?>
            <div class="product-option-block">
              <span>Metal</span>
              <div class="option-card-grid option-card-grid-two">
                <?php foreach ($options['metal_options'] as $metal): ?>
                  <?php $metalTone = $optionToneFor((string) ($metal['label'] ?? '')); ?>
                  <?php $metalGalleryJson = (string) json_encode(array_values(array_filter(array_map(static fn (mixed $item): string => clean_image((string) $item), (array) ($metal['gallery'] ?? [])), static fn (string $item): bool => $item !== '')), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
                  <?php $metalFeaturesJson = (string) json_encode(array_values(array_filter(array_map(static fn (mixed $item): string => clean_string((string) $item, 160), (array) (($metal['features'] ?? []) !== [] ? $metal['features'] : ($options['features'] ?? []))), static fn (string $item): bool => $item !== '')), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
                  <?php $metalBandOptionsJson = (string) json_encode(array_values(array_filter((array) ($metal['band_options'] ?? []), static fn (mixed $item): bool => is_array($item) && clean_string((string) ($item['value'] ?? ''), 80) !== '')), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
                  <label class="option-card option-card-luxury option-card-metal tone-<?= h($metalTone) ?>">
                    <input type="radio" name="metal" value="<?= h((string) $metal['value']) ?>" data-base-price="<?= h((string) ($metal['base_price'] ?? 0)) ?>" data-shapes="<?= h(implode(',', (array)($metal['shapes'] ?? []))) ?>" data-sizes="<?= h(implode(',', (array)($metal['sizes'] ?? []))) ?>" data-bands="<?= h(implode(',', (array)($metal['bands'] ?? []))) ?>" data-band-options="<?= h($metalBandOptionsJson) ?>" data-desc="<?= h($metal['metal_desc'] ?? '') ?>" data-gallery="<?= h($metalGalleryJson) ?>" data-features="<?= h($metalFeaturesJson) ?>" data-inventory-tracked="<?= !empty($metal['inventory_tracked']) ? '1' : '0' ?>" data-inventory-quantity="<?= h((string) clean_int($metal['inventory_quantity'] ?? 0, 0, 1000000)) ?>" <?= $selectedVariant['metal'] === (string) $metal['value'] ? 'checked' : '' ?>>
                    <div class="option-card-title-row">
                      <span><?= h((string) $metal['label']) ?></span>
                      <em class="option-card-badge">Premium Metal</em>
                    </div>
                    <small><?= h((string) $metal['description']) ?></small>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <?php if ($isRingProduct): ?>
            <div class="product-option-block">
              <span>Band / Claw Metal</span>
              <div class="option-card-grid option-card-grid-two" id="band-claw-options-grid">
                <?php foreach ($selectedMetalBandOptions as $bandMetal): ?>
                  <?php
                  $bandTone = $optionToneFor((string) ($bandMetal['label'] ?? ''));
                  $bandSurcharge = (float) ($bandMetal['surcharge'] ?? 0);
                  ?>
                  <label class="option-card option-card-luxury option-card-band tone-<?= h($bandTone) ?>">
                    <input type="radio" name="band_claw_metal" value="<?= h((string) $bandMetal['value']) ?>" data-surcharge="<?= h((string) ($bandMetal['surcharge'] ?? 0)) ?>" <?= $selectedVariant['band_claw_metal'] === (string) $bandMetal['value'] ? 'checked' : '' ?>>
                    <span class="option-card-band-thumb" aria-hidden="true">
                      <?= $renderProductMedia($productPrimaryMedia, (string) ($product['name'] ?? 'Product'), 'option-card-band-image', [], false) ?>
                    </span>
                    <div class="option-card-title-row">
                      <span><?= h((string) $bandMetal['label']) ?></span>
                      <?php if ($bandSurcharge > 0): ?><em class="option-card-badge">+<?= h(money_format($bandSurcharge)) ?></em><?php endif; ?>
                    </div>
                    <?php if (clean_string((string) ($bandMetal['description'] ?? ''), 120) !== ''): ?>
                      <small><?= h((string) $bandMetal['description']) ?></small>
                    <?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
          <?php endif; ?>

          <div class="product-option-block">
            <span><?= h((string) ($options['size_label'] ?? 'Size')) ?></span>
            <?php if (($options['size_display'] ?? 'compact') === 'stone-weights'): ?>
              <div class="option-card-grid option-card-grid-stone-weights">
                <?php foreach (($options['size_choices'] ?? []) as $choice): ?>
                  <label class="option-card option-card-stone tone-<?= h((string) ($choice['tone'] ?? 'neutral')) ?>">
                    <input type="radio" name="size" value="<?= h((string) ($choice['value'] ?? '')) ?>" <?= $selectedVariant['size'] === (string) ($choice['value'] ?? '') ? 'checked' : '' ?>>
                    <span class="option-card-stone-orb"></span>
                    <span class="option-card-stone-meta">
                      <strong><?= h((string) ($choice['label'] ?? '')) ?></strong>
                      <?php if (($choice['caption'] ?? '') !== ''): ?>
                        <small><?= h((string) $choice['caption']) ?></small>
                      <?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="option-card-grid option-card-grid-sizes">
                <?php foreach ($options['sizes'] as $index => $size): ?>
                  <label class="option-card option-card-size-pill">
                    <input type="radio" name="size" value="<?= h($size) ?>" <?= $selectedVariant['size'] === $size ? 'checked' : '' ?>>
                    <span class="option-card-size-copy">
                      <strong><?= h($size) ?></strong>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($isMatrixProduct): ?>
            <div class="product-option-block">
              <span>Delivery Timeline</span>
              <div class="option-card-grid option-card-grid-delivery">
                <?php foreach ($options['delivery_options'] as $delivery): ?>
                  <label class="option-card option-card-luxury option-card-delivery">
                    <input type="radio" name="delivery_option" value="<?= h((string) $delivery['value']) ?>" <?= $selectedVariant['delivery_option'] === (string) $delivery['value'] ? 'checked' : '' ?>>
                    <span class="option-card-delivery-mark" aria-hidden="true"></span>
                    <div class="option-card-title-row">
                      <span><?= h((string) $delivery['label']) ?></span>
                      <em class="option-card-badge"><?= h((string) $delivery['badge']) ?></em>
                    </div>
                    <small><?= h((string) $delivery['description']) ?></small>
                    <strong class="option-card-price"><?= h((string) $delivery['price_label']) ?></strong>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="product-purchase-row">
            <?php if ($isEngagementRing): ?>
              <button type="submit" class="store-btn-primary store-btn-primary-wide" <?= $selectedIsOutOfStock ? 'disabled aria-disabled="true"' : '' ?> data-ring-journey-button><?= $selectedIsOutOfStock ? 'Out of Stock' : 'Choose This Design' ?></button>
            <?php else: ?>
              <div class="store-qty" data-qty-wrap>
                <button type="button" data-qty-step="-1">−</button>
                <input type="number" min="1" max="99" name="quantity" value="1" data-qty-input>
                <button type="button" data-qty-step="1">+</button>
              </div>
              <button type="submit" class="store-btn-secondary">Add to Cart</button>
              <button type="submit" name="intent" value="buy-now" class="store-btn-primary">Buy Now</button>
            <?php endif; ?>
          </div>

          <div class="product-security-strip">
            <div><i class="fas fa-lock"></i><span>Secure Checkout</span></div>
            <div><i class="fas fa-undo"></i><span>30 Day Returns</span></div>
            <div><i class="fas fa-truck"></i><span>Free Shipping</span></div>
            <div><i class="fas fa-certificate"></i><span>Certified Quality</span></div>
          </div>
        </form>

        <div class="product-meta-strip product-meta-strip-luxury">
          <div><span>SKU</span><strong><?= h($options['sku']) ?></strong></div>
          <div><span>Metal Options</span><strong id="live-product-metals"><?= h(implode(', ', $metaMetalLabels)) ?></strong></div>
          <?php if (!empty($product['styles'])): ?>
            <?php 
                $styleNames = [];
                $allStyles = available_ring_styles();
                foreach ($product['styles'] as $sKey) {
                    if (isset($allStyles[$sKey])) $styleNames[] = $allStyles[$sKey];
                }
            ?>
            <div><span>Design Styles</span><strong><?= h(implode(', ', $styleNames)) ?></strong></div>
          <?php endif; ?>
          <?php if (!empty($product['subcategories'])): ?>
            <div><span>Subcategories</span><strong><?= h(implode(', ', (array) $product['subcategories'])) ?></strong></div>
          <?php endif; ?>
          <div><span>Delivery</span><strong id="live-product-delivery"><?= h($selectedDeliveryMeta) ?></strong></div>
        </div>
      </div>
    </div>

    <?php if ($relatedProducts !== []): ?>
      <div class="commerce-related">
        <div class="sec-hdr-premium" data-reveal>
          <span class="shop-style-kicker">You May Also Like</span>
          <div class="sec-hdr-title-row">
              <span class="sec-line"></span>
              <h2>Related Pieces</h2>
              <span class="sec-line"></span>
          </div>
        </div>
        <div class="shop-product-grid">
          <?php foreach ($relatedProducts as $item): ?>
            <?php render_product_card($item); ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const livePriceEl = document.getElementById('live-product-price');
    if (!livePriceEl) return;
    
    const metalInputs = document.querySelectorAll('input[name="metal"]');
    const bandInputs = document.querySelectorAll('input[name="band_claw_metal"]');
    const deliveryInputs = document.querySelectorAll('input[name="delivery_option"]');
    const shapeInputs = document.querySelectorAll('input[name="shape"], input[name="diamond_shape"]');
    const sizeInputs = document.querySelectorAll('input[name="size"]');
    const galleryStage = document.querySelector('[data-product-stage]');
    const galleryThumbRow = document.querySelector('[data-product-thumbs]');
    const highlightsWrap = document.querySelector('.product-highlights');
    const metaDelivery = document.getElementById('live-product-delivery');
    const bandOptionsGrid = document.getElementById('band-claw-options-grid');
    const stockFlash = document.querySelector('[data-stock-flash]');
    const ringJourneyButton = document.querySelector('[data-ring-journey-button]');
    const baseFeatures = highlightsWrap ? parseJsonList(highlightsWrap.dataset.baseFeatures || '[]') : [];
    const initialGallery = (() => {
        if (!galleryStage) return [];

        try {
            const parsed = JSON.parse(galleryStage.dataset.initialGallery || '[]');
            return Array.isArray(parsed) ? parsed.filter(item => typeof item === 'string' && item !== '') : [];
        } catch (error) {
            return [];
        }
    })();
    
    if (metalInputs.length === 0) return;

    function mediaTypeFor(src) {
        const cleanSrc = String(src || '').split('?')[0].toLowerCase();
        if (cleanSrc.endsWith('.mp4') || cleanSrc.endsWith('.webm') || cleanSrc.endsWith('.ogv') || cleanSrc.endsWith('.mov') || cleanSrc.endsWith('.m4v')) {
            return 'video';
        }
        return 'image';
    }

    function parseJsonList(raw) {
        try {
            const parsed = JSON.parse(raw || '[]');
            return Array.isArray(parsed) ? parsed.filter(item => typeof item === 'string' && item.trim() !== '') : [];
        } catch (error) {
            return [];
        }
    }

    function mediaMimeFor(src) {
        const cleanSrc = String(src || '').split('?')[0].toLowerCase();
        if (cleanSrc.endsWith('.webm')) return 'video/webm';
        if (cleanSrc.endsWith('.ogv')) return 'video/ogg';
        if (cleanSrc.endsWith('.mov')) return 'video/quicktime';
        if (cleanSrc.endsWith('.m4v')) return 'video/x-m4v';
        return 'video/mp4';
    }

    function syncOptionCards() {
        document.querySelectorAll('.option-card input[type="radio"]').forEach((input) => {
            const card = input.closest('.option-card');
            if (!card) return;
            card.classList.toggle('is-selected', input.checked);
        });
    }

    function renderHighlights(items) {
        if (!highlightsWrap) return;

        const features = Array.isArray(items) ? items.filter(item => typeof item === 'string' && item.trim() !== '') : [];
        highlightsWrap.replaceChildren();

        if (features.length === 0) {
            highlightsWrap.hidden = true;
            return;
        }

        highlightsWrap.hidden = false;
        features.forEach((feature) => {
            const row = document.createElement('div');
            row.className = 'product-highlight-chip';
            const icon = document.createElement('i');
            icon.className = 'fas fa-check';
            const text = document.createElement('span');
            text.textContent = feature;
            row.appendChild(icon);
            row.appendChild(text);
            highlightsWrap.appendChild(row);
        });
    }

    function syncDeliveryMeta() {
        if (!metaDelivery) return;

        const activeDelivery = document.querySelector('input[name="delivery_option"]:checked');
        if (!activeDelivery) {
            metaDelivery.textContent = 'Insured premium shipping';
            return;
        }

        const optionCard = activeDelivery.closest('.option-card');
        const badge = optionCard?.querySelector('.option-card-badge')?.textContent?.trim() || '';
        const label = optionCard?.querySelector('.option-card-title-row span')?.textContent?.trim() || '';
        metaDelivery.textContent = [badge, label].filter(Boolean).join(' / ') || 'Insured premium shipping';
    }

    function buildMediaNode(src, type, isStage) {
        if (!src) return null;

        if ((type || mediaTypeFor(src)) === 'video') {
            const video = document.createElement('video');
            video.className = isStage ? 'product-gallery-media' : 'product-thumb-media';
            video.playsInline = true;
            video.preload = 'metadata';
            video.muted = true;
            if (isStage) {
                video.controls = true;
                video.autoplay = true;
                video.loop = true;
                video.setAttribute('data-product-main-media', 'true');
            } else {
                video.setAttribute('aria-hidden', 'true');
            }
            const source = document.createElement('source');
            source.src = src;
            source.type = mediaMimeFor(src);
            video.appendChild(source);
            return video;
        }

        const image = document.createElement('img');
        image.className = isStage ? 'product-gallery-media' : 'product-thumb-media';
        image.src = src;
        image.alt = galleryStage?.dataset.productAlt || 'Product';
        if (isStage) {
            image.setAttribute('data-product-main-media', 'true');
        }
        return image;
    }

    function renderGallery(mediaItems) {
        if (!galleryStage || !galleryThumbRow) return;

        const nextGallery = Array.isArray(mediaItems) && mediaItems.length > 0 ? mediaItems : initialGallery;
        if (nextGallery.length === 0) return;

        const stageMedia = buildMediaNode(nextGallery[0], mediaTypeFor(nextGallery[0]), true);
        if (!stageMedia) return;
        galleryStage.replaceChildren(stageMedia);

        galleryThumbRow.replaceChildren();
        nextGallery.forEach((src, index) => {
            const thumb = document.createElement('button');
            thumb.type = 'button';
            thumb.className = 'product-thumb' + (index === 0 ? ' is-active' : '');
            thumb.setAttribute('data-product-thumb', '');
            thumb.dataset.mediaSrc = src;
            thumb.dataset.mediaType = mediaTypeFor(src);
            thumb.setAttribute('aria-label', 'View media ' + String(index + 1));

            const thumbMedia = buildMediaNode(src, thumb.dataset.mediaType, false);
            if (thumbMedia) {
                thumb.appendChild(thumbMedia);
            }
            galleryThumbRow.appendChild(thumb);
        });
    }

    function parseBandOptions(raw) {
        try {
            const parsed = JSON.parse(raw || '[]');
            return Array.isArray(parsed)
                ? parsed
                    .filter((item) => item && typeof item === 'object' && String(item.value || '').trim() !== '')
                    .map((item) => ({
                        value: String(item.value || ''),
                        label: String(item.label || ''),
                        description: String(item.description || ''),
                        surcharge: Number.parseFloat(String(item.surcharge || 0)) || 0,
                    }))
                : [];
        } catch (error) {
            return [];
        }
    }

    function currentBandPreview() {
        const activeMetal = document.querySelector('input[name="metal"]:checked');
        if (activeMetal?.dataset.gallery) {
            const galleryItems = parseJsonList(activeMetal.dataset.gallery);
            const firstImage = galleryItems.find((item) => mediaTypeFor(item) === 'image');
            if (firstImage) {
                return firstImage;
            }
        }

        const initialImage = initialGallery.find((item) => mediaTypeFor(item) === 'image');
        return initialImage || '';
    }

    function renderBandOptions(items, forceDefaultSelection = false) {
        if (!bandOptionsGrid) return;

        const options = Array.isArray(items) ? items : [];
        const currentChecked = forceDefaultSelection ? '' : (document.querySelector('input[name="band_claw_metal"]:checked')?.value || '');
        const previewSrc = currentBandPreview();
        bandOptionsGrid.replaceChildren();

        let firstFreeIndex = options.findIndex((item) => Number.parseFloat(String(item.surcharge || 0)) <= 0);
        if (firstFreeIndex < 0) {
            firstFreeIndex = 0;
        }
        const hasCurrentChecked = currentChecked !== '' && options.some((item) => String(item.value || '') === currentChecked);

        options.forEach((option, index) => {
            const labelText = String(option.label || '');
            const normalizedLabel = labelText.toLowerCase();
            let tone = 'forest';
            if (normalizedLabel.includes('rose')) {
                tone = 'rose';
            } else if (normalizedLabel.includes('yellow')) {
                tone = 'yellow';
            } else if (normalizedLabel.includes('platinum')) {
                tone = 'platinum';
            } else if (normalizedLabel.includes('white')) {
                tone = 'white';
            }

            const label = document.createElement('label');
            label.className = 'option-card option-card-luxury option-card-band tone-' + tone;

            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'band_claw_metal';
            input.value = String(option.value || '');
            input.dataset.surcharge = String(option.surcharge || 0);
            if ((!forceDefaultSelection && hasCurrentChecked && currentChecked === input.value) || ((!hasCurrentChecked || forceDefaultSelection) && index === firstFreeIndex)) {
                input.checked = true;
            }

            const thumb = document.createElement('span');
            thumb.className = 'option-card-band-thumb';
            thumb.setAttribute('aria-hidden', 'true');
            if (previewSrc) {
                const thumbImage = document.createElement('img');
                thumbImage.className = 'option-card-band-image';
                thumbImage.src = previewSrc;
                thumbImage.alt = '';
                thumb.appendChild(thumbImage);
            }

            const titleRow = document.createElement('div');
            titleRow.className = 'option-card-title-row';
            const title = document.createElement('span');
            title.textContent = labelText;
            titleRow.appendChild(title);
            if ((Number.parseFloat(String(option.surcharge || 0)) || 0) > 0) {
                const badge = document.createElement('em');
                badge.className = 'option-card-badge';
                badge.textContent = '+£' + Number.parseFloat(String(option.surcharge || 0)).toFixed(2).replace(/\.00$/, '');
                titleRow.appendChild(badge);
            }

            label.appendChild(input);
            label.appendChild(thumb);
            label.appendChild(titleRow);

            const description = String(option.description || '').trim();
            if (description !== '') {
                const small = document.createElement('small');
                small.textContent = description;
                label.appendChild(small);
            }

            bandOptionsGrid.appendChild(label);
        });

        syncOptionCards();
    }
    
    function updateLivePrice(shouldResetBandSelection = false) {
        let basePrice = 0;
        let surcharge = 0;
        let allowedShapes = [];
        let allowedSizes = [];
        let allowedBands = [];
        let inventoryTracked = false;
        let inventoryQuantity = 0;
        
        const activeMetal = document.querySelector('input[name="metal"]:checked');
        const descEl = document.getElementById('live-product-desc');
        if (activeMetal) {
            if (parseFloat(activeMetal.dataset.basePrice) > 0) {
                basePrice = parseFloat(activeMetal.dataset.basePrice);
            }
            if (activeMetal.dataset.shapes) {
                allowedShapes = activeMetal.dataset.shapes.split(',');
            }
            if (activeMetal.dataset.sizes) {
                allowedSizes = activeMetal.dataset.sizes.split(',');
            }
            if (activeMetal.dataset.bands) {
                allowedBands = activeMetal.dataset.bands.split(',');
            }
            inventoryTracked = activeMetal.dataset.inventoryTracked === '1';
            inventoryQuantity = Number.parseInt(activeMetal.dataset.inventoryQuantity || '0', 10) || 0;
            if (descEl) {
                descEl.textContent = activeMetal.dataset.desc || descEl.dataset.baseDesc;
            }
            renderBandOptions(parseBandOptions(activeMetal.dataset.bandOptions), shouldResetBandSelection);
            const nextFeatures = parseJsonList(activeMetal.dataset.features);
            renderHighlights(nextFeatures.length > 0 ? nextFeatures : baseFeatures);
            if (activeMetal.dataset.gallery) {
                renderGallery(parseJsonList(activeMetal.dataset.gallery));
            } else {
                renderGallery(initialGallery);
            }
        } else {
            // fallback to original string if no matrix price is set
            livePriceEl.textContent = livePriceEl.dataset.originalPrice;
            if (descEl) {
                descEl.textContent = descEl.dataset.baseDesc;
            }
            renderHighlights(baseFeatures);
            renderGallery(initialGallery);
            if (stockFlash) {
                stockFlash.hidden = true;
            }
            if (ringJourneyButton) {
                ringJourneyButton.disabled = false;
                ringJourneyButton.textContent = 'Choose This Design';
                ringJourneyButton.setAttribute('aria-disabled', 'false');
            }
            return;
        }
        
        const activeBand = document.querySelector('input[name="band_claw_metal"]:checked');
        if (activeBand && parseFloat(activeBand.dataset.surcharge) > 0) {
            surcharge = parseFloat(activeBand.dataset.surcharge);
        }
        
        const total = basePrice + surcharge;
        if (total > 0) {
            livePriceEl.textContent = '£' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        } else {
            livePriceEl.textContent = livePriceEl.dataset.originalPrice;
        }
        
        // Toggle shapes based on selected metal
        if (allowedShapes.length > 0 && shapeInputs.length > 0) {
            let hasVisibleCheckedShape = false;
            
            shapeInputs.forEach(input => {
                const card = input.closest('.option-card');
                if (!card) return;
                
                if (allowedShapes.includes(input.value)) {
                    card.style.display = '';
                    if (input.checked) hasVisibleCheckedShape = true;
                } else {
                    card.style.display = 'none';
                    if (input.checked) input.checked = false;
                }
            });
            
            // Auto-select first visible shape if none are checked
            if (!hasVisibleCheckedShape) {
                for (const input of shapeInputs) {
                    if (allowedShapes.includes(input.value)) {
                        input.checked = true;
                        const card = input.closest('.option-card');
                        if (card) {
                            card.classList.add('is-selected');
                        }
                        break;
                    }
                }
            }
            syncOptionCards();
        }
        
        // Toggle sizes based on selected metal
        if (allowedSizes.length > 0 && sizeInputs.length > 0) {
            let hasVisibleCheckedSize = false;
            
            sizeInputs.forEach(input => {
                const card = input.closest('.option-card');
                if (!card) return;
                
                if (allowedSizes.includes(input.value)) {
                    card.style.display = '';
                    if (input.checked) hasVisibleCheckedSize = true;
                } else {
                    card.style.display = 'none';
                    if (input.checked) input.checked = false;
                }
            });
            
            if (!hasVisibleCheckedSize) {
                for (const input of sizeInputs) {
                    if (allowedSizes.includes(input.value)) {
                        input.checked = true;
                        const card = input.closest('.option-card');
                        if (card) {
                            card.classList.add('is-selected');
                        }
                        break;
                    }
                }
            }
            syncOptionCards();
        }
        
        // Toggle bands based on selected metal
        if (document.querySelectorAll('input[name="band_claw_metal"]').length > 0) {
            let hasVisibleCheckedBand = false;
            
            document.querySelectorAll('input[name="band_claw_metal"]').forEach(input => {
                const card = input.closest('.option-card');
                if (!card) return;
                
                if (allowedBands.length === 0 || allowedBands.includes(input.value)) {
                    card.style.display = '';
                    if (input.checked) hasVisibleCheckedBand = true;
                } else {
                    card.style.display = 'none';
                    if (input.checked) input.checked = false;
                }
            });
            
            if (!hasVisibleCheckedBand) {
                for (const input of document.querySelectorAll('input[name="band_claw_metal"]')) {
                    if (allowedBands.length === 0 || allowedBands.includes(input.value)) {
                        input.checked = true;
                        const card = input.closest('.option-card');
                        if (card) {
                            card.classList.add('is-selected');
                        }
                        break;
                    }
                }
            }
            syncOptionCards();
            
            // Recalculate surcharge if band was auto-selected
            const newActiveBand = document.querySelector('input[name="band_claw_metal"]:checked');
            if (newActiveBand && parseFloat(newActiveBand.dataset.surcharge) > 0) {
                surcharge = parseFloat(newActiveBand.dataset.surcharge);
            } else {
                surcharge = 0;
            }
            
            const newTotal = basePrice + surcharge;
            if (newTotal > 0) {
                livePriceEl.textContent = '£' + newTotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            } else {
                livePriceEl.textContent = livePriceEl.dataset.originalPrice;
            }
        }

        syncDeliveryMeta();

        const isOutOfStock = inventoryTracked && inventoryQuantity <= 0;
        if (stockFlash) {
            stockFlash.hidden = !isOutOfStock;
        }
        if (ringJourneyButton) {
            ringJourneyButton.disabled = isOutOfStock;
            ringJourneyButton.textContent = isOutOfStock ? 'Out of Stock' : 'Choose This Design';
            ringJourneyButton.setAttribute('aria-disabled', isOutOfStock ? 'true' : 'false');
        }
    }
    
    metalInputs.forEach((input) => input.addEventListener('change', () => updateLivePrice(true)));
    deliveryInputs.forEach(i => i.addEventListener('change', syncDeliveryMeta));
    document.addEventListener('change', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.name === 'band_claw_metal') {
            updateLivePrice(false);
        }
    });
    
    // Initial calculation
    updateLivePrice(false);
});
</script>
