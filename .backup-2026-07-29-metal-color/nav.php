<?php $navItems = site_content()['navigation']['items']; ?>
<nav class="mnav luxury-mnav" data-mobile-nav style="background:transparent; border:none; padding:0; width:100%;">
  <div class="d-flex-nav" style="width:100%; display:flex; justify-content:center;">
    <div class="luxury-mnav-pill" style="box-shadow:none; background:transparent; padding:0; border:none;">
      <button class="mnav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" data-mobile-nav-toggle>
        <span></span>
        <span></span>
        <span></span>
      </button>

      
      <?php
      foreach ($navItems as $index => $item):
        $navLabelUpper = strtoupper((string) ($item['label'] ?? ''));
        $isRingMega = in_array($navLabelUpper, ['ENGAGEMENT RINGS', 'WEDDING RINGS', 'RINGS'], true);
        $navRingSection = $navLabelUpper === 'WEDDING RINGS' ? 'wedding' : 'engagement';
      ?>

        <div class="mnav-item has-mega">
          <a href="<?php e(resolve_link($item['url'])); ?>" class="" data-mobile-nav-link><?php e($item['label']); ?> <?php if (!empty($item['columns']) || $isRingMega): ?><i class="fas fa-chevron-down" style="font-size:0.7em; color:#c9a96e; margin-left:4px;"></i><?php endif; ?><span class="nav-underline"></span></a>
          <button class="mnav-item-toggle" type="button" aria-label="Toggle <?php e($item['label']); ?> menu" aria-expanded="false" data-mobile-submenu-toggle>
            <i class="fas fa-chevron-down" aria-hidden="true"></i>
          </button>
          <?php if ($isRingMega): ?>
            <?php
              $styles = array_values(available_ring_style_cards($navRingSection));
              $shapes = site_content()['diamond_shapes']['items'] ?? [];
              $ringSectionQuery = ['type' => 'Ring', 'ring_category' => $navRingSection];
              // Metals actually present on ring products, so every link has results.
              $navRingMetalColors = array_values(array_filter(array_unique(array_map(
                  static fn (array $p): string => (string) ($p['color'] ?? ''),
                  array_filter(catalog_expanded_products(), static fn (array $p): bool => str_starts_with(strtolower((string) ($p['product_type'] ?? '')), 'ring'))
              ))));
              sort($navRingMetalColors);
              $navRingMetalColors = array_slice($navRingMetalColors, 0, 4);
            ?>
            <?php
              // Build each mega-menu column once, then compose per section so the
              // admin-driven Style / Metal columns stay single-source. Wedding leads
              // with two large gender hero boxes (no Shape, no feature card); the
              // engagement menu keeps Style + Shape + Metal + feature card.
              $ringProfile = catalog_attribute_profile('Ring');
              $ringMetalOptions = $ringProfile['option_metal_options'] ?? [];
              $wedMetalColor = static function (string $name) use ($ringMetalOptions): string {
                  $n = strtolower($name);
                  foreach ($ringMetalOptions as $opt) {
                      if (strtolower($opt['label'] ?? '') === $n && !empty($opt['color_hex'])) {
                          return $opt['color_hex'];
                      }
                  }
                  if (str_contains($n, 'rose')) return '#d8a48f';
                  if (str_contains($n, 'white') || str_contains($n, 'platinum') || str_contains($n, 'silver')) return '#cfcfcf';
                  if (str_contains($n, 'yellow') || str_contains($n, 'gold')) return '#cda434';
                  return '#c9a96e';
              };
              ob_start();
            ?>
                <div class="mega-col mega-col-style">
                  <div class="mega-col-title">SHOP BY STYLE</div>
                  <?php foreach (array_slice($styles, 0, 4) as $style): ?>
                    <a href="<?php e(resolve_link('/shop/?' . http_build_query($ringSectionQuery + ['style' => $style['value']]))); ?>" class="mega-link-with-image">
                      <div class="img-wrap"><img src="<?php e($style['image']); ?>" alt="<?php e($style['label']); ?>"></div>
                      <span><?php e($style['label']); ?></span>
                    </a>
                  <?php endforeach; ?>
                  <a href="<?php e(resolve_link('/shop/?' . http_build_query($ringSectionQuery))); ?>" class="mega-show-all-btn">Show All Styles</a>
                </div>
            <?php $megaStyleCol = ob_get_clean(); ob_start(); ?>
                <div class="mega-col mega-col-collections">
                  <div class="mega-col-title">SHOP BY METAL</div>
                  <?php foreach ($navRingMetalColors as $navMetalColor): ?>
                    <a href="<?php e(resolve_link('/shop/?' . http_build_query($ringSectionQuery + ['color' => $navMetalColor]))); ?>" class="mega-link-with-image">
                      <div class="img-wrap"><?php if ($navRingSection === 'wedding'): ?><i class="fas fa-ring" style="font-size:1.15rem; color:<?= h($wedMetalColor($navMetalColor)); ?>;"></i><?php else: ?><i class="fas fa-circle" style="font-size:1.1rem; color:<?= h($wedMetalColor($navMetalColor)); ?>;"></i><?php endif; ?></div>
                      <span><?php e($navMetalColor); ?></span>
                    </a>
                  <?php endforeach; ?>
                  <a href="<?php e(resolve_link('/shop/?' . http_build_query($ringSectionQuery))); ?>" class="mega-show-all-btn">Show All Metals</a>
                </div>
            <?php $megaMetalCol = ob_get_clean(); ob_start(); ?>
                <div class="mega-col mega-col-shape">
                  <div class="mega-col-title">SHOP BY SHAPE</div>
                  <?php foreach (array_slice($shapes, 0, 4) as $navShape): ?>
                    <?php
                      // Scope each shape chip to this ring section (engagement) so the
                      // mega-menu lands on the right filtered collection, not all rings.
                      parse_str((string) parse_url((string) ($navShape['url'] ?? ''), PHP_URL_QUERY), $navShapeQuery);
                      $navShapeSlug = (string) ($navShapeQuery['shape'] ?? '');
                      if ($navShapeSlug === '') {
                          $navShapeSlug = content_slug((string) ($navShape['name'] ?? ''), '');
                      }
                    ?>
                    <a href="<?php e(resolve_link('/shop/?' . http_build_query($ringSectionQuery + ['shape' => $navShapeSlug]))); ?>" class="mega-link-with-image">
                      <div class="img-wrap shape-img-wrap"><img src="<?php e($navShape['icon_image'] ?: $navShape['image']); ?>" alt="<?php e($navShape['name']); ?>"></div>
                      <span><?php e($navShape['name']); ?></span>
                    </a>
                  <?php endforeach; ?>
                  <a href="<?php e(resolve_link('/shop/?' . http_build_query($ringSectionQuery))); ?>" class="mega-show-all-btn">Show All Shapes</a>
                </div>
            <?php $megaShapeCol = ob_get_clean(); ob_start(); ?>
                <div class="mega-col mega-col-feature-card">
                  <div class="feature-card-inner">
                    <div class="feature-img">
                      <img src="/assets/uploads/featured-diamond-ring.jpg" alt="Diamond Rings">
                    </div>
                    <div class="feature-content">
                      <h3>Diamond Rings</h3>
                      <p><?php e($item['feature']['subtitle'] ?? 'Discover ring styles, shapes, and custom design options in one beautiful collection.'); ?></p>
                      <a href="<?php e(resolve_link('/shop/?' . http_build_query($ringSectionQuery))); ?>" class="btn-explore"><span>EXPLORE COLLECTION</span> <span class="btn-explore-arrow">&rarr;</span></a>
                    </div>
                  </div>
                </div>
            <?php $megaFeatureCol = ob_get_clean(); ob_start(); ?>
                <?php foreach (ring_gender_box_cards() as $navGenderCard): ?>
                <div class="wedding-mega-boxcol">
                  <a href="<?php e(resolve_link('/shop/?' . http_build_query($ringSectionQuery + ['gender' => $navGenderCard['key']]))); ?>" class="wedding-mega-box">
                    <div class="wedding-mega-box-img"><img src="<?php e($navGenderCard['image']); ?>" alt="<?php e($navGenderCard['label']); ?>"></div>
                    <div class="wedding-mega-box-title"><?php e($navGenderCard['label']); ?></div>
                  </a>
                </div>
                <?php endforeach; ?>
            <?php $megaBoxCols = ob_get_clean(); ?>
            <div class="mega-drop mega-drop-wide">
              <div class="mega-inner luxury-mega-menu <?= $navRingSection === 'wedding' ? 'wedding-mega-menu' : ''; ?>"<?= $navRingSection === 'wedding' ? '' : ' style="grid-template-columns: 1fr 1fr 1fr 1.4fr;"'; ?>>
                <?php if ($navRingSection === 'wedding'): ?>
                  <?= $megaBoxCols ?>
                  <?= $megaStyleCol ?>
                  <?= $megaMetalCol ?>
                <?php else: ?>
                  <?= $megaStyleCol ?>
                  <?= $megaShapeCol ?>
                  <?= $megaMetalCol ?>
                  <?= $megaFeatureCol ?>
                <?php endif; ?>
              </div>
            </div>
          <?php elseif (strtoupper($item['label']) === 'JEWELLERY' || strtoupper($item['label']) === 'JEWELRY'): ?>
            <?php 
              $jewelleryCategories = [
                [
                  'label' => 'NECKLACES',
                  'url' => '/shop/?type=Necklace',
                  'image' => '/assets/uploads/necklace_collection_bg.png'
                ],
                [
                  'label' => 'PENDANTS',
                  'url' => '/shop/?type=Pendant',
                  'image' => '/assets/uploads/pendant_collection_bg.png'
                ],
                [
                  'label' => 'EARRINGS',
                  'url' => '/shop/?type=Earring',
                  'image' => '/assets/uploads/earring_collection_bg.png'
                ],
                [
                  'label' => 'BRACELETS & BANGLES',
                  'url' => '/shop/?type=Bracelet',
                  'image' => '/assets/uploads/bracelet_collection_bg.png'
                ],
                [
                  'label' => 'MANGALSUTRA',
                  'url' => '/shop/?type=Mangalsutra',
                  'image' => '/assets/uploads/mangalsutra_collection_bg.png'
                ]
              ];
            ?>
            <div class="mega-drop mega-drop-wide">
              <div class="mega-inner luxury-mega-menu jewellery-grid-menu" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 24px; padding: 32px 36px;">
                <?php foreach ($jewelleryCategories as $jcat): ?>
                  <a href="<?php e(resolve_link($jcat['url'])); ?>" class="jewellery-card" style="text-decoration: none; display: flex; flex-direction: column;">
                    <div class="jewellery-card-img">
                      <img src="<?php e($jcat['image']); ?>" alt="<?php e($jcat['label']); ?>">
                    </div>
                    <div class="jewellery-card-title"><?php e($jcat['label']); ?></div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php elseif (!empty($item['columns']) || !empty($item['feature']['title'])): ?>
            <div class="mega-drop <?= !empty($item['compact']) ? 'mega-drop-sm' : '' ?> mega-drop-wide">
              <?php 
                $colCount = count($item['columns'] ?? []);
                $gridCols = str_repeat('1fr ', $colCount) . (!empty($item['feature']['title']) ? ' 1.6fr' : '');
              ?>
              <div class="mega-inner luxury-mega-menu" style="grid-template-columns: <?= h(trim($gridCols)) ?>;">
                <?php foreach ($item['columns'] ?? [] as $column): ?>
                  <div class="mega-col">
                    <div class="mega-col-title"><?php e(strtoupper($column['title'] ?? '')); ?></div>
                    <?php foreach (array_slice($column['links'] ?? [], 0, 4) as $link): 
                      $lbl = strtolower($link['label'] ?? '');
                      $icon = 'far fa-gem';
                      if (strpos($lbl, 'band') !== false || strpos($lbl, 'ring') !== false || strpos($lbl, 'solitaire') !== false || strpos($lbl, 'sizing') !== false) {
                        $icon = 'fas fa-ring';
                      } elseif (strpos($lbl, 'necklace') !== false || strpos($lbl, 'pendant') !== false || strpos($lbl, 'chain') !== false) {
                        $icon = 'fas fa-award';
                      } elseif (strpos($lbl, 'earring') !== false || strpos($lbl, 'stud') !== false || strpos($lbl, 'hoop') !== false) {
                        $icon = 'far fa-dot-circle';
                      } elseif (strpos($lbl, 'bracelet') !== false || strpos($lbl, 'bangle') !== false || strpos($lbl, 'cuff') !== false) {
                        $icon = 'fas fa-circle-notch';
                      } elseif (strpos($lbl, 'gift') !== false || strpos($lbl, 'anniversary') !== false || strpos($lbl, 'birthday') !== false || strpos($lbl, 'bestsell') !== false || strpos($lbl, 'new') !== false) {
                        $icon = 'fas fa-gift';
                      } elseif (strpos($lbl, 'custom') !== false || strpos($lbl, 'bespoke') !== false || strpos($lbl, 'design') !== false) {
                        $icon = 'fas fa-magic';
                      } elseif (strpos($lbl, 'story') !== false || strpos($lbl, 'about') !== false || strpos($lbl, 'craft') !== false) {
                        $icon = 'fas fa-book-open';
                      } elseif (strpos($lbl, 'ethical') !== false || strpos($lbl, 'sourcing') !== false) {
                        $icon = 'fas fa-leaf';
                      } elseif (strpos($lbl, 'clean') !== false || strpos($lbl, 'maint') !== false || strpos($lbl, 'warranty') !== false) {
                        $icon = 'fas fa-shield-alt';
                      } elseif (strpos($lbl, 'contact') !== false || strpos($lbl, 'consult') !== false || strpos($lbl, 'appoint') !== false) {
                        $icon = 'far fa-calendar-check';
                      } elseif (strpos($lbl, 'faq') !== false || strpos($lbl, 'question') !== false) {
                        $icon = 'far fa-question-circle';
                      } elseif (strpos($lbl, 'ship') !== false || strpos($lbl, 'return') !== false) {
                        $icon = 'fas fa-shipping-fast';
                      }
                    ?>
                      <a href="<?php e(resolve_link($link['url'])); ?>" class="mega-link-with-image">
                        <div class="img-wrap"><i class="<?= $icon ?>" style="font-size:1.35rem; color:#c9a96e; transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);"></i></div>
                        <span><?php e($link['label']); ?></span>
                      </a>
                    <?php endforeach; ?>
                    <a href="<?php e(resolve_link($item['url'] ?? '/shop/')); ?>" class="mega-show-all-btn">Show All <?php e(str_ireplace(["Women's ", "Men's ", "Diamond ", "Fine ", "Our "], "", $column['title'])); ?></a>
                  </div>
                <?php endforeach; ?>
                <?php if (!empty($item['feature']['title'])): ?>
                  <div class="mega-col mega-col-feature-card">
                    <div class="feature-card-inner">
                      <div class="feature-img">
                        <img src="<?php e($item['feature']['image'] ?? ''); ?>" alt="<?php e($item['feature']['alt'] ?? ''); ?>">
                      </div>
                      <div class="feature-content">
                        <i class="far fa-gem"></i>
                        <h3><?php e($item['feature']['title']); ?></h3>
                        <p><?php e($item['feature']['subtitle'] ?? ''); ?></p>
                        <a href="<?php e(resolve_link($item['url'] ?? '/shop/')); ?>" class="btn-explore"><span>EXPLORE COLLECTION</span> <span class="btn-explore-arrow">&rarr;</span></a>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</nav>
