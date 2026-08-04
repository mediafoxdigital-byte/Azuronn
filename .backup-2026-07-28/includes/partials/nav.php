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
      ?>
        
        <div class="mnav-item has-mega">
          <a href="<?php e(resolve_link($item['url'])); ?>" class="" data-mobile-nav-link><?php e($item['label']); ?> <?php if (!empty($item['columns']) || strtoupper($item['label']) === 'ENGAGEMENT RINGS' || strtoupper($item['label']) === 'RINGS'): ?><i class="fas fa-chevron-down" style="font-size:0.7em; color:#c9a96e; margin-left:4px;"></i><?php endif; ?><span class="nav-underline"></span></a>
          <button class="mnav-item-toggle" type="button" aria-label="Toggle <?php e($item['label']); ?> menu" aria-expanded="false" data-mobile-submenu-toggle>
            <i class="fas fa-chevron-down" aria-hidden="true"></i>
          </button>
          <?php if (strtoupper($item['label']) === 'ENGAGEMENT RINGS' || strtoupper($item['label']) === 'RINGS'): ?>
            <?php 
              $styles = array_values(available_ring_style_cards());
              $shapes = site_content()['diamond_shapes']['items'] ?? [];
              $collections = catalog_attribute_profile('Ring')['selector_cards'] ?? [];
              if (empty($collections)) {
                  $collections = [
                      ['value' => 'lab-diamond', 'label' => 'Lab Diamond Rings', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product4.jpg'],
                      ['value' => 'solitaire', 'label' => 'Classic Solitaires', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product3.jpg'],
                      ['value' => 'halo', 'label' => 'Modern Halo Rings', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product10.jpg'],
                      ['value' => 'vintage', 'label' => 'Vintage Rings', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product2.jpg'],
                      ['value' => 'toi-et-moi', 'label' => 'Toi et Moi Rings', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product1.jpg'],
                      ['value' => 'sidestones', 'label' => 'Sidestone Rings', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product8.jpg'],
                  ];
              }
            ?>
            <div class="mega-drop mega-drop-wide">
              <div class="mega-inner luxury-mega-menu" style="grid-template-columns: 1fr 1fr 1fr 1.4fr;">
                
                <div class="mega-col mega-col-style">
                  <div class="mega-col-title">SHOP BY STYLE</div>
                  <?php foreach (array_slice($styles, 0, 4) as $style): ?>
                    <a href="<?php e(resolve_link('/shop/?type=Ring&style=' . $style['value'])); ?>" class="mega-link-with-image">
                      <div class="img-wrap"><img src="<?php e($style['image']); ?>" alt="<?php e($style['label']); ?>"></div>
                      <span><?php e($style['label']); ?></span>
                    </a>
                  <?php endforeach; ?>
                  <a href="<?php e(resolve_link('/shop/?type=Ring')); ?>" class="mega-show-all-btn">Show All Styles</a>
                </div>

                <div class="mega-col mega-col-shape">
                  <div class="mega-col-title">SHOP BY SHAPE</div>
                  <?php foreach (array_slice($shapes, 0, 4) as $navShape): ?>
                    <a href="<?php e(resolve_link($navShape['url'])); ?>" class="mega-link-with-image">
                      <div class="img-wrap shape-img-wrap"><img src="<?php e($navShape['icon_image'] ?: $navShape['image']); ?>" alt="<?php e($navShape['name']); ?>"></div>
                      <span><?php e($navShape['name']); ?></span>
                    </a>
                  <?php endforeach; ?>
                  <a href="<?php e(resolve_link('/shop/?type=Ring')); ?>" class="mega-show-all-btn">Show All Shapes</a>
                </div>

                <div class="mega-col mega-col-collections">
                  <div class="mega-col-title">FEATURED COLLECTIONS</div>
                  <?php foreach (array_slice($collections, 0, 4) as $collection): ?>
                    <a href="<?php e(resolve_link('/shop/?type=Ring&collection=' . $collection['value'])); ?>" class="mega-link-with-large-image">
                      <div class="img-wrap large-img-wrap"><img src="<?php e($collection['image']); ?>" alt="<?php e($collection['label']); ?>"></div>
                      <span><?php e($collection['label']); ?></span>
                    </a>
                  <?php endforeach; ?>
                  <a href="<?php e(resolve_link('/shop/?type=Ring')); ?>" class="mega-show-all-btn">Show All Collections</a>
                </div>
                
                <div class="mega-col mega-col-feature-card">
                  <div class="feature-card-inner">
                    <div class="feature-img">
                      <img src="/assets/uploads/featured-diamond-ring.jpg" alt="Diamond Rings">
                    </div>
                    <div class="feature-content">
                      <h3>Diamond Rings</h3>
                      <p><?php e($item['feature']['subtitle'] ?? 'Discover ring styles, shapes, and custom design options in one beautiful collection.'); ?></p>
                      <a href="<?php e(resolve_link('/shop/?type=Ring')); ?>" class="btn-explore"><span>EXPLORE COLLECTION</span> <span class="btn-explore-arrow">&rarr;</span></a>
                    </div>
                  </div>
                </div>

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
          <?php else: ?>
            <div class="mega-drop <?= !empty($item['compact']) ? 'mega-drop-sm' : '' ?> mega-drop-wide">
              <?php 
                $colCount = count($item['columns']);
                $gridCols = str_repeat('1fr ', $colCount) . (!empty($item['feature']['title']) ? ' 1.6fr' : '');
              ?>
              <div class="mega-inner luxury-mega-menu" style="grid-template-columns: <?= h(trim($gridCols)) ?>;">
                <?php foreach ($item['columns'] as $column): ?>
                  <div class="mega-col">
                    <div class="mega-col-title"><?php e(strtoupper($column['title'])); ?></div>
                    <?php foreach (array_slice($column['links'], 0, 4) as $link): 
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
