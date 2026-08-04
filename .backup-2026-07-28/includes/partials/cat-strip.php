<?php $categoryCards = site_content()['category_cards']; ?>
<?php if ($categoryCards !== []): ?>
<section class="category-showcase-section" data-reveal>
  <div class="container">
    <div class="category-showcase-heading" data-reveal>
      <span class="category-showcase-kicker">Shop by Category</span>
      <div class="category-showcase-title-row">
        <span aria-hidden="true"></span>
        <h2>Explore Signature Categories</h2>
        <span aria-hidden="true"></span>
      </div>
      <p>Explore signature collections with image-led category cards designed to feel editorial, refined, and easy to browse on every screen.</p>
    </div>

    <div class="category-showcase-shell" data-category-carousel>
      <button class="category-showcase-nav category-showcase-nav-prev" type="button" aria-label="Previous category" data-category-prev>
        <span class="category-showcase-nav-icon" aria-hidden="true"></span>
        <span class="category-showcase-nav-label">Prev</span>
      </button>

      <div class="category-showcase-viewport">
        <div class="category-showcase-track" data-category-track>
          <?php foreach ($categoryCards as $index => $card): ?>
            <a
              class="category-showcase-card"
              href="<?= h(resolve_link('/shop/?type=' . urlencode($card['title'] ?? ''))) ?>"
              style="--category-index: <?= (int) $index ?>;"
            >
              <div class="category-showcase-media">
                <img src="<?= h($card['image']) ?>" alt="<?= h($card['alt']) ?>" loading="lazy">
              </div>
              <div class="category-showcase-copy">
                <span class="category-showcase-name"><?= h($card['title']) ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="category-showcase-nav category-showcase-nav-next" type="button" aria-label="Next category" data-category-next>
        <span class="category-showcase-nav-label">Next</span>
        <span class="category-showcase-nav-icon" aria-hidden="true"></span>
      </button>
    </div>
  </div>
</section>
<?php endif; ?>
