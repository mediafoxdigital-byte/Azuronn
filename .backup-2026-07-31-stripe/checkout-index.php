<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_customer_auth('/checkout/');
$customer = current_customer();
if ($customer === null) {
    redirect(resolve_link('/account/login/?next=' . urlencode('/checkout/')));
}

$savedAddresses = customer_saved_addresses($customer);
$savedAddressIndexInput = clean_string($_POST['saved_address_index'] ?? $_GET['saved_address_index'] ?? '', 20);
$savedAddressIndex = ctype_digit($savedAddressIndexInput) ? clean_int($savedAddressIndexInput, 0, 50) : null;
$selectedSavedAddress = ($savedAddressIndex !== null && isset($savedAddresses[$savedAddressIndex])) ? $savedAddresses[$savedAddressIndex] : customer_primary_saved_address($customer);

$cart = cart_state();
if ($cart['items'] === []) {
    site_flash_set('error', 'Add items to your cart before checkout.');
    redirect(resolve_link('/cart/'));
}

$pageFlash = site_flash_pull();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $pageFlash = ['type' => 'error', 'message' => 'Session expired. Please try again.'];
    } else {
        $result = checkout_place_order($_POST);
        if ($result['ok'] ?? false) {
            $order = $result['order'] ?? [];
            site_flash_set('success', 'Order ' . (string) ($order['id'] ?? '') . ' placed successfully.');
            redirect(resolve_link('/account/'));
        }
        $pageFlash = ['type' => 'error', 'message' => (string) ($result['message'] ?? 'Unable to place order.')];
    }
}

$pageTitle = 'Checkout - ' . SITE_NAME;
$bodyClass = 'checkout-page';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="checkout-shell reveal-in">
  <div class="container">
    <div class="commerce-hero">
      <div>
        <span class="auth-kicker">Checkout</span>
        <h1>Complete your order</h1>
        <p>Your payment method, delivery details, and order summary are connected to real stored order records.</p>
      </div>
    </div>

    <?php if ($pageFlash !== null): ?>
      <div class="store-flash <?= h($pageFlash['type']) ?>"><?= h($pageFlash['message']) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= h(resolve_link('/checkout/')) ?>" class="checkout-layout">
      <?php csrf_field(); ?>

      <div class="checkout-main">
        <div class="checkout-card">
          <div class="checkout-card-head">
            <span class="auth-kicker">Delivery</span>
            <h2>Shipping Details</h2>
          </div>
          <?php if ($savedAddresses !== []): ?>
            <div class="checkout-address-picker">
              <label class="store-field">
                <span>Use a Saved Address</span>
                <select onchange="window.location.href='<?= h(resolve_link('/checkout/')) ?>' + (this.value !== '' ? '?saved_address_index=' + encodeURIComponent(this.value) : '')">
                  <option value="">Choose from saved addresses</option>
                  <?php foreach ($savedAddresses as $index => $address): ?>
                    <option value="<?= h((string) $index) ?>" <?= $savedAddressIndex === $index ? 'selected' : '' ?>>
                      <?= h($address['label']) ?> - <?= h($address['city']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
          <?php endif; ?>
          <?php if ($savedAddressIndex !== null): ?><input type="hidden" name="saved_address_index" value="<?= h((string) $savedAddressIndex) ?>"><?php endif; ?>
          <div class="checkout-grid">
            <label class="store-field">
              <span>Full Name</span>
              <input type="text" name="full_name" required value="<?= h((string) ($_POST['full_name'] ?? ($selectedSavedAddress['recipient_name'] ?? $customer['name']))) ?>">
            </label>
            <label class="store-field">
              <span>Phone</span>
              <input type="text" name="phone" required value="<?= h((string) ($_POST['phone'] ?? ($selectedSavedAddress['phone'] ?? $customer['phone']))) ?>">
            </label>
            <label class="store-field store-field-wide">
              <span>Address Line 1</span>
              <input type="text" name="address_line_1" required value="<?= h((string) ($_POST['address_line_1'] ?? ($selectedSavedAddress['address_line_1'] ?? $customer['address_line_1']))) ?>">
            </label>
            <label class="store-field store-field-wide">
              <span>Address Line 2</span>
              <input type="text" name="address_line_2" value="<?= h((string) ($_POST['address_line_2'] ?? ($selectedSavedAddress['address_line_2'] ?? $customer['address_line_2']))) ?>">
            </label>
            <label class="store-field">
              <span>City</span>
              <input type="text" name="city" required value="<?= h((string) ($_POST['city'] ?? ($selectedSavedAddress['city'] ?? $customer['city']))) ?>">
            </label>
            <label class="store-field">
              <span>State</span>
              <input type="text" name="state" required value="<?= h((string) ($_POST['state'] ?? ($selectedSavedAddress['state'] ?? $customer['state']))) ?>">
            </label>
            <label class="store-field">
              <span>Postal Code</span>
              <input type="text" name="postal_code" required value="<?= h((string) ($_POST['postal_code'] ?? ($selectedSavedAddress['postal_code'] ?? $customer['postal_code']))) ?>">
            </label>
            <label class="store-field">
              <span>Country</span>
              <input type="text" name="country" required value="<?= h((string) ($_POST['country'] ?? ($selectedSavedAddress['country'] ?? ($customer['country'] ?: 'India')))) ?>">
            </label>
          </div>
        </div>

        <div class="checkout-card">
          <div class="checkout-card-head">
            <span class="auth-kicker">Payment</span>
            <h2>Choose Payment Method</h2>
          </div>
          <div class="option-card-grid">
            <label class="option-card">
              <input type="radio" name="payment_method" value="online" <?= (string) ($_POST['payment_method'] ?? 'online') === 'online' ? 'checked' : '' ?>>
              <span>Online Payment</span>
              <small>Simulated secure capture. Orders move into processing immediately.</small>
            </label>
            <label class="option-card">
              <input type="radio" name="payment_method" value="cash" <?= (string) ($_POST['payment_method'] ?? '') === 'cash' ? 'checked' : '' ?>>
              <span>Cash on Delivery</span>
              <small>Order is placed instantly and payment stays awaiting until delivered.</small>
            </label>
          </div>
          <label class="store-field">
            <span>Order Notes</span>
            <textarea name="notes" rows="4" placeholder="Any gifting or delivery instructions"><?= h((string) ($_POST['notes'] ?? '')) ?></textarea>
          </label>
        </div>
      </div>

      <aside class="summary-panel">
        <div class="summary-card">
          <span class="auth-kicker">Summary</span>
          <h2><?= count($cart['items']) ?> items</h2>
          <div class="checkout-mini-lines">
            <?php foreach ($cart['items'] as $line): ?>
              <div class="checkout-mini-line">
                <?= store_media_markup((string) ($line['ring_media'] ?? ''), (string) ($line['ring_media_alt'] ?? ($line['product']['name'] ?? 'Ring')), 'checkout-mini-media') ?>
                <div>
                  <strong><?= h($line['product']['name']) ?></strong>
                  <span><?= h(line_variant_summary($line)) ?> / Qty <?= h((string) $line['quantity']) ?></span>
                  <?php if ((string) ($line['diamond_title'] ?? '') !== ''): ?>
                    <span>Diamond: <?= h((string) ($line['diamond_title'] ?? '')) ?></span>
                  <?php endif; ?>
                </div>
                <strong><?= h($line['line_total_label']) ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="summary-row"><span>Subtotal</span><strong><?= h($cart['subtotal_label']) ?></strong></div>
          <div class="summary-row"><span><?= h((string) ($cart['delivery_summary_label'] ?? 'Delivery')) ?></span><strong><?= h((string) ($cart['delivery_total_label'] ?? 'Free')) ?></strong></div>
          <div class="summary-row"><span>Shipping</span><strong><?= h((string) $cart['shipping_label']) ?></strong></div>
          <?php if ($cart['discount'] > 0): ?>
            <div class="summary-row"><span>Discount</span><strong>-<?= h($cart['discount_label']) ?></strong></div>
          <?php endif; ?>
          <div class="summary-row summary-row-total"><span>Total</span><strong><?= h($cart['total_label']) ?></strong></div>
          <button type="submit" class="store-btn-primary">Place Order</button>
        </div>
      </aside>
    </form>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
