<?php $footer = site_content()['footer']; ?>
<footer class="footer-premium" style="background-image: url('<?php e(asset_url('assets/uploads/premium_footer_bg.png')); ?>');">
  <div class="footer-overlay"></div>
  <div class="container-fluid relative-z" style="padding: 0 4%;">
    <div class="foot-grid">
      <!-- Col 1: Brand & Contact -->
      <div class="foot-col foot-col-brand">
        <div class="footer-logo">
          <img src="<?php e(resolve_link('/assets/final_logo.png')); ?>" alt="<?php e(SITE_NAME . ' Logo'); ?>" style="max-height: 36px; max-width: 160px; width: auto; object-fit: contain; filter: brightness(0) invert(1);">
        </div>
        <p class="footer-desc">Timeless elegance, ethically crafted jewelry designed to celebrate life's most precious moments.</p>
        <div class="footer-contact">
          <div class="contact-item">
            <i class="fas fa-map-marker-alt"></i>
            <span>123 Diamond Street, London,<br>United Kingdom</span>
          </div>
          <div class="contact-item">
            <i class="fas fa-phone-alt"></i>
            <span><?php e(STORE_PHONE); ?></span>
          </div>
          <div class="contact-item">
            <i class="far fa-envelope"></i>
            <span><?php e(STORE_EMAIL); ?></span>
          </div>
        </div>
      </div>

      <!-- Col 2: Information -->
      <div class="foot-col">
        <h4><?php e($footer['information_title']); ?></h4>
        <div class="footer-ornament">━━ <i class="fas fa-gem"></i> ━━</div>
        <ul>
          <?php foreach ($footer['information_links'] as $link): ?>
            <li><a href="<?php e(resolve_link($link['url'])); ?>"><i class="fas fa-chevron-right"></i> <?php e($link['label']); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Col 3: Account -->
      <div class="foot-col">
        <h4><?php e($footer['account_title']); ?></h4>
        <div class="footer-ornament">━━ <i class="fas fa-gem"></i> ━━</div>
        <ul>
          <?php foreach ($footer['account_links'] as $link): ?>
            <li><a href="<?php e(resolve_link($link['url'])); ?>"><i class="fas fa-chevron-right"></i> <?php e($link['label']); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Col 4: Newsletter & Social -->
      <div class="foot-col foot-col-newsletter" id="footer-newsletter">
        <h4>SUBSCRIBE TO NEWSLETTER</h4>
        <div class="footer-ornament">━━ <i class="fas fa-gem"></i> ━━</div>
        <p class="footer-nl-desc">Join our VIP circle for exclusive fine jewelry releases, private sales, and styling guides.</p>
        <?php $footerFlash = site_flash_pull(); ?>
        <?php if ($footerFlash !== null): ?>
          <div class="store-flash <?= h((string) ($footerFlash['type'] ?? 'success')) ?>" style="margin-top:10px; font-size:12px; padding:8px; border-radius:4px;">
            <?= h((string) ($footerFlash['message'] ?? '')) ?>
          </div>
        <?php endif; ?>
        <form action="<?= h(resolve_link('/#footer-newsletter')) ?>" method="POST" class="footer-nl-form">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="newsletter-subscribe">
          <input type="email" name="email" placeholder="Your email address..." required>
          <button type="submit" aria-label="Subscribe"><i class="fas fa-paper-plane"></i></button>
        </form>

        <h5 class="footer-follow-title" style="margin-top: 26px; margin-bottom: 12px;">FOLLOW US</h5>
        <div class="social-row">
          <a href="<?php e(resolve_link(SOCIAL_FACEBOOK)); ?>" class="soc-btn" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="<?php e(resolve_link(SOCIAL_TWITTER)); ?>" class="soc-btn" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="<?php e(resolve_link(SOCIAL_RSS)); ?>" class="soc-btn" aria-label="Pinterest"><i class="fab fa-pinterest-p"></i></a>
          <a href="<?php e(resolve_link(SOCIAL_GOOGLEPLUS)); ?>" class="soc-btn" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="<?php e(resolve_link(SOCIAL_YOUTUBE)); ?>" class="soc-btn" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        </div>
      </div>
    </div>

    <div class="footer-features-box">
      <div class="feature-item">
        <i class="far fa-gem"></i>
        <div>
          <strong>FREE UK DELIVERY</strong>
          <span>On all orders, no minimum</span>
        </div>
      </div>
      <div class="feature-item">
        <i class="fas fa-shield-alt"></i>
        <div>
          <strong>LIFETIME WARRANTY</strong>
          <span>On all fine jewellery</span>
        </div>
      </div>
      <div class="feature-item">
        <i class="fas fa-sync-alt"></i>
        <div>
          <strong>30 DAYS RETURNS</strong>
          <span>Hassle-free returns</span>
        </div>
      </div>
      <div class="feature-item">
        <i class="fas fa-gift"></i>
        <div>
          <strong>PREMIUM PACKAGING</strong>
          <span>Signature gift box</span>
        </div>
      </div>
    </div>

    <div class="foot-btm-links">
      <?php foreach ($footer['bottom_links'] as $index => $link): ?>
        <a href="<?php e(resolve_link($link['url'])); ?>"><?php e($link['label']); ?></a>
        <?php if ($index < count($footer['bottom_links']) - 1): ?>
          <span class="sep">|</span>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (($footer['bottom_links'] ?? []) !== []): ?><span class="sep">|</span><?php endif; ?>
      <button type="button" class="footer-cookie-settings" data-cookie-preferences-trigger>Cookie Settings</button>
    </div>
    
    <div class="foot-copy">
      © <?php e($footer['copyright_year']); ?> <span class="brand-name"><?php e($footer['copyright_brand']); ?>.</span> All Rights Reserved. Made with <i class="fas fa-heart" style="color: #c89d58;"></i> by <span class="author-name"><?php e($footer['copyright_author']); ?></span>
    </div>
    
    <div class="pay-row">
      <img src="<?php e($footer['payment_image']); ?>" alt="<?php e($footer['payment_alt']); ?>">
    </div>
  </div>
</footer>

<div class="cookie-settings-launcher" data-cookie-settings-launcher hidden>
  <button type="button" class="cookie-settings-pill" data-cookie-preferences-trigger aria-label="Open cookie settings">
    <i class="fas fa-cookie-bite" aria-hidden="true"></i>
    <span>Cookie Settings</span>
  </button>
</div>

<section class="cookie-banner" data-cookie-banner hidden aria-labelledby="cookie-banner-title">
  <div class="cookie-banner-card">
    <p class="cookie-banner-kicker">Cookie Choices</p>
    <h2 id="cookie-banner-title">Control non-essential cookies</h2>
    <p class="cookie-banner-copy">
      We use strictly necessary cookies for security, sign-in, checkout, basket continuity, and to remember this choice.
      We do not enable non-essential cookies on this storefront unless you choose to allow them.
      Read our <a href="<?php e(resolve_link('/cookie-policy/')); ?>">Cookie Policy</a>.
    </p>
    <div class="cookie-banner-actions">
      <button type="button" class="cookie-btn cookie-btn-primary" data-cookie-action="accept-all">Accept All</button>
      <button type="button" class="cookie-btn cookie-btn-secondary" data-cookie-action="reject-all">Reject Non-Essential</button>
      <button type="button" class="cookie-btn cookie-btn-tertiary" data-cookie-action="open-settings">Choose Settings</button>
    </div>
  </div>
</section>

<div class="cookie-modal-shell" data-cookie-modal hidden>
  <div class="cookie-modal-backdrop" data-cookie-modal-close></div>
  <section class="cookie-modal" role="dialog" aria-modal="true" aria-labelledby="cookie-modal-title">
    <button type="button" class="cookie-modal-close" data-cookie-modal-close aria-label="Close cookie settings">
      <i class="fas fa-times" aria-hidden="true"></i>
    </button>
    <p class="cookie-banner-kicker">Cookie Settings</p>
    <h2 id="cookie-modal-title">Manage your cookie choices</h2>
    <p class="cookie-modal-copy">
      Strictly necessary cookies stay on because this website needs them to run securely and to provide the basket,
      checkout, account sign-in, and consent controls you request. Optional categories stay off unless you switch them on.
    </p>

    <div class="cookie-category-list">
      <article class="cookie-category-card is-locked">
        <div class="cookie-category-head">
          <div>
            <h3>Strictly Necessary</h3>
            <p>Required for sessions, CSRF protection, account sign-in, basket continuity, checkout flow, and remembering your cookie preferences.</p>
          </div>
          <span class="cookie-status-chip is-on">Always active</span>
        </div>
      </article>

      <article class="cookie-category-card">
        <div class="cookie-category-head">
          <div>
            <h3>Analytics</h3>
            <p>Helps measure traffic and performance. This public storefront does not currently deploy analytics cookies unless you opt in and we activate them later.</p>
          </div>
          <label class="cookie-toggle">
            <input type="checkbox" data-cookie-category="analytics">
            <span class="cookie-toggle-ui" aria-hidden="true"></span>
            <span class="cookie-toggle-label">Allow analytics</span>
          </label>
        </div>
      </article>

      <article class="cookie-category-card">
        <div class="cookie-category-head">
          <div>
            <h3>Marketing</h3>
            <p>Would allow advertising, remarketing, or social media tracking technologies. This storefront does not currently place these cookies unless you choose to allow them and they are introduced later.</p>
          </div>
          <label class="cookie-toggle">
            <input type="checkbox" data-cookie-category="marketing">
            <span class="cookie-toggle-ui" aria-hidden="true"></span>
            <span class="cookie-toggle-label">Allow marketing</span>
          </label>
        </div>
      </article>
    </div>

    <div class="cookie-modal-actions">
      <button type="button" class="cookie-btn cookie-btn-primary" data-cookie-action="save-settings">Save Choices</button>
      <button type="button" class="cookie-btn cookie-btn-secondary" data-cookie-action="reject-all">Reject Non-Essential</button>
      <button type="button" class="cookie-btn cookie-btn-tertiary" data-cookie-action="accept-all">Accept All</button>
    </div>
  </section>
</div>

<div class="scroll-top" id="scrollTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">
  <i class="fas fa-angle-double-up"></i>
</div>

<script src="<?php e(asset_url('assets/js/main.js')); ?>"></script>
<script src="<?php e(asset_url('assets/js/script.js')); ?>"></script>
<script src="<?php e(asset_url('assets/js/store.js')); ?>"></script>

</body>
</html>
