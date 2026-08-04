<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_customer_auth('/account/');
$customer = current_customer();
if ($customer === null) {
    redirect(resolve_link('/account/login/'));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        site_flash_set('error', 'Session expired. Please try again.');
        redirect(resolve_link('/account/'));
    }

    $action = clean_string($_POST['action'] ?? '', 40);
    if ($action === 'save-address') {
        $addressIndexRaw = clean_string($_POST['address_index'] ?? '', 20);
        $addressIndex = ctype_digit($addressIndexRaw) ? clean_int($addressIndexRaw, 0, 50) : null;
        $result = customer_save_address($_POST['address'] ?? [], $addressIndex);
        site_flash_set(($result['ok'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'Unable to save address.'));
        redirect(resolve_link('/account/'));
    }

    if ($action === 'delete-address') {
        $result = customer_delete_address(clean_int($_POST['address_index'] ?? 0, 0, 50));
        site_flash_set(($result['ok'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'Unable to delete address.'));
        redirect(resolve_link('/account/'));
    }

    if ($action === 'remove-wishlist') {
        $result = customer_remove_wishlist_product(clean_string($_POST['product_id'] ?? '', 80));
        site_flash_set(($result['ok'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'Unable to update wishlist.'));
        redirect(resolve_link('/account/'));
    }

    if ($action === 'wishlist-add-to-cart') {
        $result = wishlist_add_product_to_cart(clean_string($_POST['product_id'] ?? '', 80));
        site_flash_set(($result['ok'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'Unable to add wishlist item to cart.'));
        redirect(resolve_link('/account/'));
    }
}

$customer = current_customer();
if ($customer === null) {
    redirect(resolve_link('/account/login/'));
}

$orders = customer_orders($customer);
$savedAddresses = customer_saved_addresses($customer);
$wishlistProducts = customer_wishlist_products($customer);
$pageFlash = site_flash_pull();
$addressEditIndexRaw = clean_string($_GET['address_edit'] ?? '', 20);
$addressEditIndex = ctype_digit($addressEditIndexRaw) ? clean_int($addressEditIndexRaw, 0, 50) : null;
$editingAddress = ($addressEditIndex !== null && isset($savedAddresses[$addressEditIndex])) ? $savedAddresses[$addressEditIndex] : null;
$fullAddress = trim((string) (($customer['address_line_1'] ?? '') . ' ' . ($customer['address_line_2'] ?? '')));
$pageTitle = 'My Account - ' . SITE_NAME;
$bodyClass = 'account-page';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="premium-account-wrapper reveal-in">
  <div class="container-fluid" style="padding: 0 4%;">
    <div class="premium-account-hero">
      <div class="hero-text-col">
        <div class="hero-kicker">CUSTOMER ACCOUNT</div>
        <div class="hero-heading"><?= h($customer['name']) ?></div>
        <p>Manage order history, saved delivery addresses,<br>and your personal wishlist in one place.</p>
      </div>
      <div class="hero-actions-col-row">
        <a class="hero-btn-dark-outline" href="<?= h(resolve_link('/account/profile/')) ?>">
          <i class="far fa-user"></i> EDIT PROFILE
        </a>
        <a class="hero-btn-gold" href="<?= h(resolve_link('/account/logout/')) ?>">
          LOGOUT <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </div>

    <?php if ($pageFlash !== null): ?>
      <div class="store-flash <?= h($pageFlash['type']) ?>"><?= h($pageFlash['message']) ?></div>
    <?php endif; ?>

    <div class="premium-account-main">
      <div class="premium-account-col">
        <!-- Account Snapshot -->
        <div class="premium-account-panel panel-watermark-bottom">
          <div class="panel-header">
            <div class="panel-title">
              <i class="far fa-user"></i> ACCOUNT SNAPSHOT
            </div>
            <a class="panel-action" href="<?= h(resolve_link('/account/profile/')) ?>">MANAGE <i class="fas fa-cog"></i></a>
          </div>
          <div class="snapshot-grid">
            <div class="snapshot-item">
              <i class="fas fa-shopping-bag"></i>
              <strong><?= h((string) $customer['total_orders']) ?></strong>
              <span>Total Orders</span>
            </div>
            <div class="snapshot-item">
              <i class="fas fa-pound-sign"></i>
              <strong><?= h($customer['total_spent']) ?></strong>
              <span>Lifetime Spend</span>
            </div>
            <div class="snapshot-item">
              <i class="far fa-heart"></i>
              <strong><?= h((string) count($wishlistProducts)) ?></strong>
              <span>Wishlist Items</span>
            </div>
          </div>
          <div class="snapshot-details">
            <div class="detail-row"><i class="far fa-envelope"></i> <div><span>EMAIL</span><strong><?= h($customer['email']) ?></strong></div></div>
            <div class="detail-row"><i class="fas fa-phone-alt"></i> <div><span>PHONE</span><strong><?= h($customer['phone']) ?></strong></div></div>
            <div class="detail-row"><i class="fas fa-map-marker-alt"></i> <div><span>ADDRESS</span><strong><?= $fullAddress !== '' ? h($fullAddress) : 'Complete this during checkout<br>or save an address below' ?></strong></div></div>
            <div class="detail-row"><i class="fas fa-book"></i> <div><span>JOINED</span><strong><?= h($customer['joined_at']) ?></strong></div></div>
          </div>
        </div>

        <!-- Saved Addresses -->
        <div class="premium-account-panel">
          <div class="panel-header">
            <div class="panel-title">
              <i class="fas fa-map-marker-alt"></i> SAVED ADDRESSES
            </div>
            <a class="panel-action-btn" href="<?= h(resolve_link('/account/' . ($editingAddress !== null ? '' : '?address_edit=new'))) ?>">ADD ADDRESS</a>
          </div>

          <?php if ($savedAddresses === []): ?>
            <div class="premium-empty-state">
              <i class="fas fa-map-marked-alt"></i>
              <h3>No saved addresses</h3>
              <p>Save delivery addresses here so checkout<br>is faster the next time you order.</p>
            </div>
          <?php else: ?>
            <div class="address-card-grid">
              <?php foreach ($savedAddresses as $index => $address): ?>
                <article class="address-card">
                  <div class="address-card-top">
                    <strong><?= h($address['label']) ?></strong>
                    <span><?= h($address['recipient_name']) ?></span>
                  </div>
                  <p><?= h($address['address_line_1']) ?></p>
                  <?php if (($address['address_line_2'] ?? '') !== ''): ?><p><?= h($address['address_line_2']) ?></p><?php endif; ?>
                  <p><?= h($address['city']) ?>, <?= h($address['state']) ?> <?= h($address['postal_code']) ?></p>
                  <p><?= h($address['country']) ?> · <?= h($address['phone']) ?></p>
                  <div class="address-card-actions">
                    <a class="store-link-inline" href="<?= h(resolve_link('/account/?address_edit=' . $index)) ?>">Edit</a>
                    <form method="post" action="<?= h(resolve_link('/account/')) ?>">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="delete-address">
                      <input type="hidden" name="address_index" value="<?= h((string) $index) ?>">
                      <button type="submit" class="store-link-btn">Delete</button>
                    </form>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if (isset($_GET['address_edit']) || $savedAddresses === []): ?>
          <!-- Address Form -->
          <div class="premium-account-panel">
            <div class="panel-header">
              <div class="panel-title">
                <i class="fas fa-clipboard-check"></i> <?= $editingAddress !== null ? 'UPDATE ADDRESS' : 'SAVE A NEW ADDRESS' ?>
              </div>
            </div>
            <form method="post" action="<?= h(resolve_link('/account/')) ?>" class="premium-address-form">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="save-address">
              <?php if ($editingAddress !== null): ?><input type="hidden" name="address_index" value="<?= h((string) $addressEditIndex) ?>"><?php endif; ?>

              <div class="form-grid-2">
                <label class="premium-field">
                  <span>LABEL</span>
                  <input type="text" name="address[label]" required value="<?= h((string) ($editingAddress['label'] ?? 'Home')) ?>" placeholder="e.g. Home">
                </label>
                <label class="premium-field">
                  <span>RECIPIENT NAME</span>
                  <input type="text" name="address[recipient_name]" required value="<?= h((string) ($editingAddress['recipient_name'] ?? $customer['name'])) ?>" placeholder="abdul">
                </label>
              </div>

              <div class="form-grid-2">
                <label class="premium-field">
                  <span>PHONE</span>
                  <input type="text" name="address[phone]" required value="<?= h((string) ($editingAddress['phone'] ?? $customer['phone'])) ?>" placeholder="1234567890">
                </label>
                <label class="premium-field">
                  <span>COUNTRY</span>
                  <select name="address[country]" required>
                    <option value="India" <?= (($editingAddress['country'] ?? ($customer['country'] ?: 'India')) === 'India') ? 'selected' : '' ?>>India</option>
                  </select>
                </label>
              </div>

              <label class="premium-field">
                <span>ADDRESS LINE 1</span>
                <input type="text" name="address[address_line_1]" required value="<?= h((string) ($editingAddress['address_line_1'] ?? '')) ?>" placeholder="House no., Building, Street">
              </label>

              <label class="premium-field">
                <span>ADDRESS LINE 2 (OPTIONAL)</span>
                <input type="text" name="address[address_line_2]" value="<?= h((string) ($editingAddress['address_line_2'] ?? '')) ?>" placeholder="Apartment, suite, unit, etc.">
              </label>

              <div class="form-grid-3">
                <label class="premium-field">
                  <span>CITY</span>
                  <input type="text" name="address[city]" required value="<?= h((string) ($editingAddress['city'] ?? $customer['city'])) ?>" placeholder="Surat">
                </label>
                <label class="premium-field">
                  <span>STATE</span>
                  <select name="address[state]" required>
                    <option value="Gujarat" <?= (($editingAddress['state'] ?? $customer['state']) === 'Gujarat') ? 'selected' : '' ?>>Gujarat</option>
                  </select>
                </label>
                <label class="premium-field">
                  <span>POSTAL CODE</span>
                  <input type="text" name="address[postal_code]" required value="<?= h((string) ($editingAddress['postal_code'] ?? $customer['postal_code'])) ?>" placeholder="395001">
                </label>
              </div>

              <button type="submit" class="btn-full-gold"><i class="fas fa-lock"></i> <?= $editingAddress !== null ? 'UPDATE ADDRESS' : 'SAVE ADDRESS' ?></button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <div class="premium-account-col">
        <!-- Order History -->
        <div class="premium-account-panel panel-watermark-right">
          <div class="panel-header">
            <div class="panel-title">
              <i class="fas fa-shopping-bag"></i> ORDER HISTORY
            </div>
            <a class="panel-action-btn" href="<?= h(resolve_link('/shop/')) ?>">CONTINUE SHOPPING <i class="fas fa-arrow-right"></i></a>
          </div>

          <?php if ($orders === []): ?>
            <div class="premium-empty-state">
              <div class="icon-circle"><i class="fas fa-shopping-bag"></i></div>
              <h3>No orders yet</h3>
              <p>Your completed checkout orders will<br>appear here with payment and item details.</p>
              <a href="<?= h(resolve_link('/shop/')) ?>" class="btn-solid-gold">START SHOPPING <i class="fas fa-arrow-right"></i></a>
            </div>
          <?php else: ?>
            <div class="account-orders">
              <?php foreach ($orders as $order): ?>
                <?php $requestSummary = order_customer_request_summary($order); ?>
                <article class="order-card">
                  <div class="order-card-top">
                    <div>
                      <strong><?= h($order['id']) ?></strong>
                      <span><?= h($order['placed_at']) ?></span>
                    </div>
                    <div class="order-card-meta">
                      <span class="status-pill"><?= h($order['status']) ?></span>
                      <span class="status-pill"><?= h(strtolower((string) ($order['payment_method'] ?? 'online')) === 'cash' ? 'cash on delivery' : 'online payment') ?></span>
                      <?php if (is_array($requestSummary)): ?>
                        <span class="status-pill status-pill-accent"><?= h((string) ($requestSummary['label'] ?? 'Request Submitted')) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="order-card-summary">
                    <div><span>Total</span><strong><?= h($order['total']) ?></strong></div>
                    <div><span>Payment</span><strong><?= h($order['payment_status']) ?></strong></div>
                    <div><span>Items</span><strong><?= h($order['item_count']) ?></strong></div>
                  </div>
                  <?php if (($order['items'] ?? []) !== []): ?>
                    <div class="order-line-list">
                      <?php foreach ($order['items'] as $line): ?>
                        <div class="order-line-item">
                          <img src="<?= h($line['image']) ?>" alt="<?= h($line['product_name']) ?>">
                          <div>
                            <strong><?= h($line['product_name']) ?></strong>
                            <span><?= h(line_variant_summary($line)) ?> / Qty <?= h((string) $line['quantity']) ?></span>
                          </div>
                          <strong><?= h($line['line_total']) ?></strong>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <div class="order-card-actions">
                    <a class="store-btn-secondary" href="<?= h(resolve_link('/account/order/?id=' . urlencode((string) $order['id']))) ?>">View Invoice</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Saved Pieces -->
        <div class="premium-account-panel panel-watermark-bottom">
          <div class="panel-header">
            <div class="panel-title">
              <i class="far fa-heart"></i> SAVED PIECES (WISHLIST)
            </div>
            <a class="panel-action-btn" href="<?= h(resolve_link('/wishlist/')) ?>">OPEN WISHLIST</a>
          </div>

          <?php if ($wishlistProducts === []): ?>
            <div class="premium-empty-state">
              <i class="far fa-heart"></i>
              <h3>No wishlist items yet</h3>
              <p>Save pieces from the product page and they will stay here for quick access later.</p>
            </div>
          <?php else: ?>
            <div class="premium-wishlist-mini-list">
              <?php foreach (array_slice($wishlistProducts, 0, 2) as $product): ?>
                <div class="mini-wishlist-card">
                  <img src="<?= h(product_primary_media($product)) ?>" alt="<?= h($product['name']) ?>">
                  <div class="mini-card-info">
                    <strong><?= h($product['name']) ?></strong>
                    <span><?= h($product['product_type']) ?> / <?= h($product['color']) ?></span>
                  </div>
                  <div class="mini-card-actions">
                    <form method="post" action="<?= h(resolve_link('/account/')) ?>">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="remove-wishlist">
                      <input type="hidden" name="product_id" value="<?= h($product['id']) ?>">
                      <button type="submit" class="action-btn" title="Remove from wishlist"><i class="fas fa-heart"></i></button>
                    </form>
                    <form method="post" action="<?= h(resolve_link('/account/')) ?>">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="wishlist-add-to-cart">
                      <input type="hidden" name="product_id" value="<?= h($product['id']) ?>">
                      <button type="submit" class="action-btn" title="Move to cart"><i class="fas fa-shopping-bag"></i></button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="panel-footer">
              <span class="footer-note"><i class="far fa-check-circle"></i> <?= h((string) count($wishlistProducts)) ?> saved pieces ready to review</span>
              <a href="<?= h(resolve_link('/wishlist/')) ?>" class="footer-link">MANAGE FULL WISHLIST <i class="fas fa-arrow-right"></i></a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
