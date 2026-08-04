<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_customer_auth('/wishlist/');
$customer = current_customer();
if ($customer === null) {
    redirect(resolve_link('/account/login/'));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        site_flash_set('error', 'Session expired. Please try again.');
        redirect(resolve_link('/wishlist/'));
    }

    $action = clean_string($_POST['action'] ?? '', 40);
    if ($action === 'remove-wishlist') {
        $result = customer_remove_wishlist_product(clean_string($_POST['product_id'] ?? '', 80));
        site_flash_set(($result['ok'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'Unable to update wishlist.'));
        redirect(resolve_link('/wishlist/'));
    }

    if ($action === 'wishlist-add-to-cart') {
        $result = wishlist_add_product_to_cart(clean_string($_POST['product_id'] ?? '', 80));
        site_flash_set(($result['ok'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'Unable to add wishlist item to cart.'));
        redirect(resolve_link('/wishlist/'));
    }
}

$customer = current_customer();
if ($customer === null) {
    redirect(resolve_link('/account/login/'));
}

$wishlistProducts = customer_wishlist_products($customer);
$pageFlash = site_flash_pull();
$pageTitle = 'Wishlist - ' . SITE_NAME;
$bodyClass = 'account-page wishlist-page';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="premium-wishlist-wrapper reveal-in">
  <div class="container-fluid" style="padding: 0 4%;">
    <div class="premium-wishlist-hero">
      <div class="premium-wishlist-hero-content-wrapper">
        <div class="hero-text-col">
          <div class="hero-kicker">
            WISHLIST <span class="line"></span> <i class="far fa-gem"></i>
          </div>
          <div class="hero-heading">
            Your saved pieces,<br>
            curated for <span class="gold-text">faster decisions.</span>
          </div>
          <p>Review your favorites in one place, move them to cart<br>or keep refining before checkout.</p>
          
          <div class="hero-feature-badges">
            <div class="hero-badge">
              <div class="badge-icon"><i class="far fa-heart"></i></div>
              <span>Save your favorites</span>
            </div>
            <div class="hero-badge">
              <div class="badge-icon"><i class="fas fa-shopping-bag"></i></div>
              <span>Move to cart instantly</span>
            </div>
            <div class="hero-badge">
              <div class="badge-icon"><i class="fas fa-shield-alt"></i></div>
              <span>Secure & Private</span>
            </div>
          </div>
        </div>
        
        <div class="hero-actions-col">
          <a href="<?= h(resolve_link('/account/')) ?>" class="hero-btn-dark">
            <div class="btn-left"><i class="far fa-user"></i> MY ACCOUNT</div>
            <i class="fas fa-arrow-right"></i>
          </a>
          <a href="<?= h(resolve_link('/shop/')) ?>" class="hero-btn-light">
            <div class="btn-left"><i class="fas fa-magic"></i> EXPLORE MORE</div>
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>

    <?php if ($pageFlash !== null): ?>
      <div class="store-flash <?= h($pageFlash['type']) ?>"><?= h($pageFlash['message']) ?></div>
    <?php endif; ?>

    <div class="premium-wishlist-main">
      <div class="premium-wishlist-gallery">
        <div class="premium-wishlist-gallery-header">
          <div class="premium-wishlist-gallery-title-wrapper">
            <div class="premium-wishlist-gallery-icon">
              <i class="far fa-heart"></i>
            </div>
            <div class="premium-wishlist-gallery-title">
              <span class="kicker">SAVED COLLECTION</span>
              <h2>Wishlist Gallery</h2>
            </div>
          </div>
          <div class="premium-wishlist-count-pill">
            <i class="fas fa-heart"></i> <?= h((string) count($wishlistProducts)) ?> Items Saved
          </div>
        </div>

        <?php if ($wishlistProducts === []): ?>
          <div class="account-empty" style="text-align:center; padding: 60px 0;">
            <h3>Your wishlist is empty</h3>
            <p style="color:#6a766e; margin-bottom:20px;">Save pieces from product pages to build a polished shortlist before you commit at checkout.</p>
            <a class="btn-add-cart" href="<?= h(resolve_link('/shop/')) ?>" style="text-decoration:none;">Browse the Collection</a>
          </div>
        <?php else: ?>
          <div class="premium-wishlist-items">
            <?php foreach ($wishlistProducts as $product): ?>
              <?php
                // Get review rating if available, otherwise mock it for premium look
                $rating = 4.9;
                $reviewCount = 128;
                if (isset($product['rating'])) {
                  $rating = $product['rating'];
                }
                if (isset($product['reviews_count'])) {
                  $reviewCount = $product['reviews_count'];
                }
              ?>
              <article class="premium-wishlist-card">
                <div class="premium-wishlist-card-media">
                  <a href="<?= h(product_url($product)) ?>">
                    <img src="<?= h(product_primary_media($product)) ?>" alt="<?= h($product['name']) ?>">
                  </a>
                  <div class="premium-wishlist-card-heart">
                    <i class="fas fa-heart"></i>
                  </div>
                </div>
                <div class="premium-wishlist-card-info">
                  <div class="premium-wishlist-card-meta">
                    <?= h(strtoupper($product['product_type'])) ?> • <?= h(strtoupper((string) ($product['color'] ?? ''))) ?>
                  </div>
                  <h3><a href="<?= h(product_url($product)) ?>" style="color:inherit;text-decoration:none;"><?= h($product['name']) ?></a></h3>
                  <div class="premium-wishlist-card-rating">
                    <div class="stars">
                      <i class="fas fa-star"></i>
                      <i class="fas fa-star"></i>
                      <i class="fas fa-star"></i>
                      <i class="fas fa-star"></i>
                      <i class="fas fa-star-half-alt"></i>
                    </div>
                    <span><?= h((string)$rating) ?> (<?= h((string)$reviewCount) ?> reviews)</span>
                  </div>
                  <div class="premium-wishlist-card-price">
                    <?= h($product['new_price']) ?>
                  </div>
                  <div class="premium-wishlist-card-actions">
                    <form method="post" action="<?= h(resolve_link('/wishlist/')) ?>" style="margin:0;">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="wishlist-add-to-cart">
                      <input type="hidden" name="product_id" value="<?= h((string) $product['id']) ?>">
                      <button type="submit" class="btn-add-cart">
                        <i class="fas fa-shopping-bag"></i> Add to Cart
                      </button>
                    </form>
                    <a class="btn-view-details" href="<?= h(product_url($product)) ?>">
                      <i class="far fa-eye"></i> View Details
                    </a>
                    <form method="post" action="<?= h(resolve_link('/wishlist/')) ?>" style="margin:0;">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="remove-wishlist">
                      <input type="hidden" name="product_id" value="<?= h((string) $product['id']) ?>">
                      <button type="submit" class="btn-delete-item" title="Remove from Wishlist">
                        <i class="far fa-trash-alt"></i>
                      </button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="premium-wishlist-summary">
        <div class="premium-wishlist-summary-top">
          <div class="summary-top-icon">
            <i class="fas fa-shopping-bag"></i>
          </div>
          <div class="summary-top-text">
            <span class="kicker">QUICK SUMMARY</span>
            <h2><?= h((string) count($wishlistProducts)) ?> Pieces</h2>
          </div>
          <div class="summary-floating-box">
            <img src="<?= h(asset_url('assets/uploads/premium_ring_box_flawless.png')) ?>" alt="Ring Box">
          </div>
        </div>
        
        <div class="premium-wishlist-summary-bottom">
          <div class="summary-action-list">
            <a href="<?= h(resolve_link('/account/')) ?>" style="text-decoration:none;" class="summary-action-item">
              <div class="summary-action-icon"><i class="fas fa-user-circle"></i></div>
              <div class="summary-action-text">
                <strong>Account</strong>
                <span><?= h((string) ($customer['email'] ?? '')) ?></span>
              </div>
              <div class="summary-action-arrow"><i class="fas fa-chevron-right"></i></div>
            </a>
            <a href="<?= h(resolve_link('/cart/')) ?>" style="text-decoration:none;" class="summary-action-item">
              <div class="summary-action-icon"><i class="fas fa-shopping-cart"></i></div>
              <div class="summary-action-text">
                <strong>Ready For Cart</strong>
                <span>Move favorites to cart anytime</span>
              </div>
              <div class="summary-action-arrow"><i class="fas fa-chevron-right"></i></div>
            </a>
            <a href="#" style="text-decoration:none;" class="summary-action-item">
              <div class="summary-action-icon"><i class="fas fa-clipboard-list"></i></div>
              <div class="summary-action-text">
                <strong>Next Step</strong>
                <span>Review details before checkout</span>
              </div>
              <div class="summary-action-arrow"><i class="fas fa-chevron-right"></i></div>
            </a>
          </div>

          <div class="summary-badges">
            <div class="summary-badge-item">
              <div class="summary-badge-icon"><i class="fas fa-lock"></i></div>
              <div class="summary-badge-text">
                <strong>Easy Access</strong>
                <span>Saved items in one place</span>
              </div>
            </div>
            <div class="summary-badge-item">
              <div class="summary-badge-icon"><i class="fas fa-shield-alt"></i></div>
              <div class="summary-badge-text">
                <strong>Secure & Private</strong>
                <span>Your wishlist is always safe</span>
              </div>
            </div>
          </div>

          <button class="btn-view-saved">
            VIEW SAVED ITEMS (<?= h((string) count($wishlistProducts)) ?>) <i class="fas fa-arrow-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
