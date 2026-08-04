<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$productId = clean_string($_GET['product_id'] ?? '', 80);
$shape = sanitize_text((string) ($_GET['shape'] ?? 'round'));
$color = sanitize_text((string) ($_GET['color'] ?? ''));
$size = sanitize_text((string) ($_GET['size'] ?? ''));
$metal = sanitize_text((string) ($_GET['metal'] ?? ''));
$bandClawMetal = sanitize_text((string) ($_GET['band_claw_metal'] ?? ''));
$deliveryOption = sanitize_text((string) ($_GET['delivery_option'] ?? ''));
$diamondId = clean_string($_GET['diamond_id'] ?? ($_POST['diamond_id'] ?? ''), 80);

$product = product_by_id($productId);
if ($product === null) {
    site_flash_set('error', 'Ring design not found. Please restart your journey.');
    redirect(resolve_link('/shop/'));
}
$productOptions = product_option_data($product);
if (!($productOptions['is_ring_product'] ?? false)) {
    site_flash_set('error', 'This product does not use the ring builder.');
    redirect(product_url($product));
}

$summarySelection = product_normalize_selection($product, [
    'color' => $color,
    'size' => $size,
    'diamond_shape' => $shape,
    'metal' => $metal,
    'band_claw_metal' => $bandClawMetal,
    'delivery_option' => $deliveryOption,
], true);
$shape = $summarySelection['diamond_shape'] !== '' ? (string) $summarySelection['diamond_shape'] : $shape;

function diamond_shape_label(string $shape): string
{
    $shapes = available_diamond_shapes();
    return $shapes[$shape] ?? ucfirst(str_replace('-', ' ', $shape));
}

function diamond_shape_svg_uri(string $shape, int $index = 0): string
{
    $shape = strtolower($shape);
    $tones = [
        ['#f5f7fb', '#dce3ec', '#fafcff'],
        ['#f4f2fb', '#dad8e8', '#ffffff'],
        ['#f4f5f2', '#d7dbd5', '#ffffff'],
        ['#f2f4f5', '#d9dde0', '#ffffff'],
        ['#f6f4ef', '#e2d9cd', '#ffffff'],
    ];
    [$bgStart, $bgEnd, $shine] = $tones[$index % count($tones)];

    $shapeMarkup = match ($shape) {
        'round' => '<circle cx="240" cy="220" r="128" />',
        'oval' => '<ellipse cx="240" cy="220" rx="114" ry="152" />',
        'pear' => '<path d="M240 82 C190 82 146 132 146 196 C146 292 224 354 240 370 C256 354 334 292 334 196 C334 132 290 82 240 82 Z" />',
        'emerald' => '<path d="M178 84 H302 L352 132 V308 L302 356 H178 L128 308 V132 Z" />',
        'princess' => '<rect x="128" y="108" width="224" height="224" rx="24" ry="24" />',
        'cushion' => '<rect x="124" y="96" width="232" height="248" rx="72" ry="72" />',
        'marquise' => '<path d="M240 74 C290 98 336 154 348 220 C336 286 290 342 240 366 C190 342 144 286 132 220 C144 154 190 98 240 74 Z" />',
        'radiant' => '<path d="M188 84 H292 L364 156 V284 L292 356 H188 L116 284 V156 Z" />',
        'asscher' => '<path d="M182 82 H298 L378 162 V278 L298 358 H182 L102 278 V162 Z" />',
        'heart' => '<path d="M240 362 C218 336 118 266 118 184 C118 130 162 94 214 94 C232 94 248 102 240 118 C232 102 248 94 266 94 C318 94 362 130 362 184 C362 266 262 336 240 362 Z" />',
        default => '<ellipse cx="240" cy="220" rx="114" ry="152" />',
    };

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 440">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$bgStart}"/>
      <stop offset="100%" stop-color="{$bgEnd}"/>
    </linearGradient>
    <radialGradient id="gem" cx="42%" cy="28%" r="78%">
      <stop offset="0%" stop-color="{$shine}"/>
      <stop offset="42%" stop-color="#eef2f7"/>
      <stop offset="100%" stop-color="#bcc6d0"/>
    </radialGradient>
    <linearGradient id="edge" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#ffffff" stop-opacity="0.9"/>
      <stop offset="100%" stop-color="#a8b1bc" stop-opacity="0.95"/>
    </linearGradient>
    <clipPath id="clip">
      {$shapeMarkup}
    </clipPath>
  </defs>
  <rect width="480" height="440" fill="url(#bg)"/>
  <g clip-path="url(#clip)">
    <rect x="80" y="40" width="320" height="360" fill="url(#gem)"/>
    <polygon points="240,70 292,142 240,220 188,142" fill="#ffffff" fill-opacity="0.72"/>
    <polygon points="240,220 326,150 352,230 300,322" fill="#eef2f8" fill-opacity="0.78"/>
    <polygon points="240,220 180,314 128,230 154,150" fill="#eef1f6" fill-opacity="0.8"/>
    <polygon points="188,142 240,220 154,150" fill="#d9e2eb" fill-opacity="0.95"/>
    <polygon points="292,142 326,150 240,220" fill="#dbe3ea" fill-opacity="0.95"/>
    <polygon points="180,314 240,220 300,322 240,356" fill="#d4dde7" fill-opacity="0.86"/>
    <polygon points="116,232 154,150 180,314" fill="#c3ccd6" fill-opacity="0.72"/>
    <polygon points="326,150 364,232 300,322" fill="#c0c9d2" fill-opacity="0.7"/>
    <polygon points="198,104 240,70 282,104 240,122" fill="#ffffff" fill-opacity="0.92"/>
    <circle cx="208" cy="180" r="9" fill="#111" fill-opacity="0.78"/>
    <circle cx="272" cy="180" r="9" fill="#111" fill-opacity="0.78"/>
  </g>
  <g fill="none" stroke="url(#edge)" stroke-width="8" stroke-linejoin="round">
    {$shapeMarkup}
  </g>
</svg>
SVG;

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function diamond_builder_url(array $baseParams, array $extraParams = []): string
{
    $params = array_merge($baseParams, $extraParams);
    $params = array_filter($params, static fn (string $value): bool => $value !== '');

    return resolve_link('/diamond/?' . http_build_query($params));
}

function diamond_inventory_title(array $diamond, string $shape): string
{
    $diamondTitle = clean_string((string) ($diamond['title'] ?? ''), 140);
    if ($diamondTitle !== '') {
        return $diamondTitle;
    }

    $diamondShape = clean_string((string) ($diamond['shape'] ?? ''), 40);
    if ($diamondShape === '' || $diamondShape === 'all') {
        $diamondShape = $shape;
    }

    return trim((string) ($diamond['carat'] ?? '') . 'ct ' . (string) ($diamond['color'] ?? '') . ' / ' . (string) ($diamond['clarity'] ?? '') . ' ' . diamond_shape_label($diamondShape));
}

function diamond_inventory_visual(array $diamond, string $shape, int $index = 0): string
{
    $diamondShape = clean_string((string) ($diamond['shape'] ?? ''), 40);
    if ($diamondShape === '' || $diamondShape === 'all') {
        $diamondShape = $shape;
    }

    $diamondVisual = clean_image($diamond['image'] ?? '');
    if ($diamondVisual === '' || $diamondVisual === '#') {
        $diamondVisual = diamond_shape_svg_uri($diamondShape, $index);
    }

    return $diamondVisual;
}

function diamond_inventory_description(array $diamond): string
{
    $diamondDescription = clean_multiline((string) ($diamond['description'] ?? ''), 280);
    if ($diamondDescription !== '') {
        return $diamondDescription;
    }

    return trim((string) ($diamond['cut'] ?? 'Excellent')) . ' cut lab-grown diamond selected for bright face-up performance, balanced proportions, and a refined premium look in this setting.';
}

$diamondJourneyParams = [
    'product_id' => $productId,
    'shape' => $shape,
    'color' => $color,
    'size' => $size,
    'metal' => $metal,
    'band_claw_metal' => $bandClawMetal,
    'delivery_option' => $deliveryOption,
];
$ringBackUrl = product_url($product, array_filter([
    'color' => $summarySelection['color'] ?? '',
    'size' => $summarySelection['size'] ?? '',
    'shape' => $summarySelection['diamond_shape'] ?? $shape,
    'metal' => $summarySelection['metal'] ?? '',
    'band_claw_metal' => $summarySelection['band_claw_metal'] ?? '',
    'delivery_option' => $summarySelection['delivery_option'] ?? '',
], static fn (string $value): bool => $value !== ''));
$ringInventoryStatus = product_inventory_status($product, $summarySelection);
if (!empty($ringInventoryStatus['out_of_stock'])) {
    site_flash_set('error', 'This ring selection is currently out of stock.');
    redirect($ringBackUrl);
}

$diamonds = product_diamond_inventory($product, $shape);
$selectedDiamond = $diamondId !== '' ? product_diamond_inventory_item($product, $diamondId, $shape) : null;
$isDetailView = $selectedDiamond !== null;

if ($diamondId !== '' && $selectedDiamond === null) {
    site_flash_set('error', 'That diamond is no longer available. Please choose another option.');
    redirect(diamond_builder_url($diamondJourneyParams));
}

$diamondProfile = catalog_attribute_profile((string) ($product['product_type'] ?? 'Ring'));
$diamondIntroKicker = clean_string((string) ($product['diamond_intro_kicker'] ?? ''), 80);
if ($diamondIntroKicker === '') {
    $diamondIntroKicker = clean_string((string) ($diamondProfile['diamond_intro_kicker'] ?? ''), 80);
}
if ($diamondIntroKicker === '') {
    $diamondIntroKicker = 'Select Your Centre Stone';
}
$diamondIntroText = clean_multiline((string) ($product['diamond_intro_text'] ?? ''), 320);
if ($diamondIntroText === '') {
    $diamondIntroText = clean_multiline((string) ($diamondProfile['diamond_intro_text'] ?? ''), 320);
}
if ($diamondIntroText === '') {
    $diamondIntroText = 'Compare premium lab-grown stones curated for this ring design, then choose the diamond that best matches your preferred balance of size, colour, clarity, and brilliance.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        site_flash_set('error', 'Session expired. Please try again.');
    } else {
        if ($selectedDiamond !== null) {
            $summarySelection = product_normalize_selection($product, [
                'color' => $color,
                'size' => $size,
                'diamond_shape' => $shape,
                'metal' => $metal,
                'band_claw_metal' => $bandClawMetal,
                'delivery_option' => $deliveryOption,
            ], true);

            $result = cart_add_item($productId, 1, [
                'color' => $summarySelection['color'],
                'size' => $summarySelection['size'],
                'diamond_shape' => $shape,
                'diamond_id' => (string) ($selectedDiamond['id'] ?? ''),
                'diamond_title' => diamond_inventory_title($selectedDiamond, $shape),
                'diamond_price' => (string) ((float) ($selectedDiamond['price'] ?? 0)),
                'diamond_image' => clean_string((string) ($selectedDiamond['image'] ?? ''), 2048),
                'metal' => $summarySelection['metal'],
                'band_claw_metal' => $summarySelection['band_claw_metal'],
                'delivery_option' => $summarySelection['delivery_option'],
            ], true);
            if ($result['ok'] ?? false) {
                site_flash_set('success', 'Ring and diamond added to your cart.');
                redirect(resolve_link('/cart/'));
            } else {
                site_flash_set('error', (string) ($result['message'] ?? 'Unable to add the product.'));
            }
        } else {
            site_flash_set('error', 'Select a diamond first.');
        }
    }
}

$pageTitle = ($isDetailView ? 'Complete Ring' : 'Select Diamond') . ' - ' . SITE_NAME;
$bodyClass = 'diamond-page';
require_once dirname(__DIR__) . '/includes/header.php';

$metalLabel = product_option_label($productOptions['metal_options'] ?? [], (string) ($summarySelection['metal'] ?? ''));
$bandClawLabel = product_option_label($productOptions['band_claw_metal_options'] ?? [], (string) ($summarySelection['band_claw_metal'] ?? ''));
$ringSummaryMedia = product_selection_primary_media($product, $summarySelection);
$ringSummaryAlt = trim(implode(' ', array_filter([
    clean_string((string) ($product['name'] ?? ''), 140),
    clean_string($metalLabel, 120),
], static fn (string $item): bool => $item !== '')));
$settingPrice = product_selection_setting_price($product, $summarySelection);
$deliveryPrice = max(0, (float) ($summarySelection['delivery_surcharge'] ?? 0));
$settingTotal = $settingPrice + $deliveryPrice;
$detailTotal = product_selection_total_price($product, $summarySelection, (float) (($selectedDiamond['price'] ?? 0)));
?>

<div class="container">
  <div class="step-bar">
    <div class="step-item is-completed">
      <span><i class="fas fa-check"></i></span> Select Ring Design
    </div>
    <div class="step-item <?= $isDetailView ? 'is-completed' : 'is-active' ?>">
      <span><?= $isDetailView ? '<i class="fas fa-check"></i>' : '2' ?></span> Select Diamond
    </div>
    <div class="step-item <?= $isDetailView ? 'is-active' : '' ?>">
      <span>3</span> Complete Ring
    </div>
  </div>

  <div class="diamond-layout">
    <div class="diamond-main">
      <div class="diamond-hero-copy">
        <span class="diamond-kicker"><?= h($isDetailView ? 'Complete Your Ring' : $diamondIntroKicker) ?></span>
        <h2><?= h($isDetailView ? diamond_inventory_title($selectedDiamond, $shape) : 'Available ' . diamond_shape_label($shape) . ' Diamonds') ?></h2>
        <p><?= h($isDetailView ? 'Review the selected diamond in full detail, confirm the ring setting on the right, and add the finished piece to your cart with the correct combined price.' : $diamondIntroText) ?></p>
        <div class="diamond-hero-actions">
          <a class="store-btn-secondary" href="<?= h($ringBackUrl) ?>">Go Back</a>
        </div>
      </div>
      <?php if ($isDetailView): ?>
        <?php
          $detailShape = clean_string((string) ($selectedDiamond['shape'] ?? ''), 40);
          if ($detailShape === '' || $detailShape === 'all') {
              $detailShape = $shape;
          }
          $detailTitle = diamond_inventory_title($selectedDiamond, $shape);
          $detailVisual = diamond_inventory_visual($selectedDiamond, $shape, 0);
          $detailDescription = diamond_inventory_description($selectedDiamond);
          $detailBadge = clean_string((string) ($selectedDiamond['badge'] ?? ''), 40);
          if ($detailBadge === '') {
              $detailBadge = 'Selected Stone';
          }
          $detailSpecs = array_filter([
              'Shape' => diamond_shape_label($detailShape),
              'Carat' => clean_string((string) ($selectedDiamond['carat'] ?? ''), 20),
              'Color' => clean_string((string) ($selectedDiamond['color'] ?? ''), 20),
              'Clarity' => clean_string((string) ($selectedDiamond['clarity'] ?? ''), 20),
              'Cut' => clean_string((string) ($selectedDiamond['cut'] ?? ''), 40),
              'Ratio' => clean_string((string) ($selectedDiamond['ratio'] ?? ''), 40),
              'Measurement' => clean_string((string) ($selectedDiamond['measurement'] ?? ''), 80),
              'REF' => clean_string((string) ($selectedDiamond['ref'] ?? ''), 80),
              'IGI Certificate' => clean_string((string) ($selectedDiamond['igi_certificate'] ?? ''), 160),
          ], static fn (string $value): bool => $value !== '');
        ?>
        <article class="diamond-detail-shell">
          <div class="diamond-detail-media">
            <span class="diamond-badge"><?= h($detailBadge) ?></span>
            <div class="diamond-detail-frame">
              <img src="<?= h($detailVisual) ?>" alt="<?= h($detailTitle) ?>">
            </div>
          </div>

          <div class="diamond-detail-copy">
            <div class="diamond-detail-head">
              <h3><?= h($detailTitle) ?></h3>
              <p><?= h($detailDescription) ?></p>
            </div>

            <div class="diamond-detail-price">
              <div>
                <span>Diamond Price</span>
                <strong><?= h(money_format((float) ($selectedDiamond['price'] ?? 0))) ?></strong>
              </div>
              <div>
                <span>Ring + Diamond</span>
                <strong><?= h(money_format($detailTotal)) ?></strong>
              </div>
            </div>

            <dl class="diamond-detail-specs">
              <?php foreach ($detailSpecs as $specLabel => $specValue): ?>
                <div><dt><?= h($specLabel) ?></dt><dd><?= h($specValue) ?></dd></div>
              <?php endforeach; ?>
            </dl>

            <div class="diamond-detail-actions">
              <form method="post" action="">
                <?php csrf_field(); ?>
                <input type="hidden" name="diamond_id" value="<?= h((string) ($selectedDiamond['id'] ?? '')) ?>">
                <button type="submit" class="store-btn-primary">Purchase This Ring</button>
              </form>
              <a class="store-btn-secondary" href="<?= h(diamond_builder_url($diamondJourneyParams)) ?>">Choose Another Diamond</a>
            </div>
          </div>
        </article>
      <?php elseif ($diamonds === []): ?>
        <article class="diamond-empty-state">
          <h3>No <?= h(strtolower(diamond_shape_label($shape))) ?> diamonds are available right now.</h3>
          <p>Add active stones for this shape from the Diamonds admin page and they will appear here automatically.</p>
        </article>
      <?php else: ?>
        <div class="diamond-grid">
          <?php foreach ($diamonds as $index => $d): ?>
            <?php
              $diamondShape = clean_string((string) ($d['shape'] ?? ''), 40);
              if ($diamondShape === '' || $diamondShape === 'all') {
                  $diamondShape = $shape;
              }
              $diamondTitle = diamond_inventory_title($d, $shape);
              $diamondVisual = diamond_inventory_visual($d, $shape, $index);
              $diamondBadge = clean_string((string) ($d['badge'] ?? ''), 40);
              if ($diamondBadge === '') {
                  $diamondBadge = 'Lab Selected';
              }
              $diamondDescription = diamond_inventory_description($d);
              $diamondHighlights = array_filter([
                  trim((string) ($d['carat'] ?? '') . 'ct'),
                  clean_string((string) ($d['color'] ?? ''), 20),
                  clean_string((string) ($d['clarity'] ?? ''), 20),
                  clean_string((string) ($d['cut'] ?? ''), 40),
              ], static fn (string $value): bool => $value !== '');
            ?>
            <article class="diamond-card">
              <div class="diamond-visual">
                <div class="diamond-visual-frame">
                  <img src="<?= h($diamondVisual) ?>" alt="<?= h($diamondTitle) ?>">
                </div>
                <span class="diamond-badge"><?= h($diamondBadge) ?></span>
              </div>

              <div class="diamond-copy">
                <h3><?= h($diamondTitle) ?></h3>
                <?php if ($diamondHighlights !== []): ?>
                  <div class="diamond-card-meta"><?= h(implode(' • ', $diamondHighlights)) ?></div>
                <?php endif; ?>
                <p><?= h($diamondDescription) ?></p>
              </div>

              <div class="diamond-action">
                <div class="diamond-price-stack">
                  <span>Diamond Price</span>
                  <strong><?= h(money_format((float) ($d['price'] ?? 0))) ?></strong>
                </div>
                <a class="store-btn-primary" href="<?= h(diamond_builder_url($diamondJourneyParams, ['diamond_id' => (string) ($d['id'] ?? '')])) ?>">Select Diamond</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="diamond-side">
      <aside class="ring-summary">
        <span class="diamond-kicker">Your Design</span>
        <?= store_media_markup($ringSummaryMedia, $ringSummaryAlt !== '' ? $ringSummaryAlt : 'Ring Design', 'ring-summary-media') ?>
        <h3><?= h($product['name']) ?></h3>
        <p class="ring-summary-copy">Your selected setting stays visible here while you compare diamonds across the current shape.</p>
        <div class="ring-summary-grid">
          <div><span>Ring</span><strong><?= h(money_format($settingPrice)) ?></strong></div>
          <?php if ($deliveryPrice > 0): ?>
            <div><span>Delivery</span><strong><?= h(money_format($deliveryPrice)) ?></strong></div>
          <?php endif; ?>
          <?php if ($isDetailView): ?>
            <div><span>Diamond</span><strong><?= h(money_format((float) ($selectedDiamond['price'] ?? 0))) ?></strong></div>
            <div><span>Total</span><strong><?= h(money_format($detailTotal)) ?></strong></div>
          <?php endif; ?>
          <div><span>Metal</span><strong><?= h($metalLabel) ?></strong></div>
          <div><span>Band / Claw</span><strong><?= h($bandClawLabel) ?></strong></div>
          <div><span>Ring Size</span><strong><?= h($summarySelection['size']) ?></strong></div>
          <div><span>Delivery</span><strong><?= h($summarySelection['delivery_label']) ?></strong></div>
          <div><span>Shape</span><strong><?= h(diamond_shape_label($shape)) ?></strong></div>
        </div>
      </aside>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
