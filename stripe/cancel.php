<?php

declare(strict_types=1);

/**
 * Stripe Checkout Cancel Page
 *
 * Stripe redirects here when the customer abandons payment.
 * The order stays as pending/awaiting — the customer can retry or
 * the admin can cancel it later.
 */

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$orderId = clean_string($_GET['order_id'] ?? '', 80);

$pageTitle = 'Payment Cancelled - ' . SITE_NAME;
$bodyClass = 'stripe-result-page';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="checkout-shell reveal-in">
  <div class="container" style="max-width:640px; text-align:center; padding:60px 20px;">
    <div style="font-size:3rem; margin-bottom:16px;">&#8634;</div>
    <h1 style="font-family:var(--serif); font-size:2rem; margin-bottom:12px;">Payment Not Completed</h1>
    <p style="color:#5c6360; font-size:1rem; line-height:1.7; margin-bottom:24px;">
      Your payment was not completed. No charge has been made.
      <?php if ($orderId !== ''): ?>
        Your order <strong><?= h($orderId) ?></strong> is saved and awaiting payment.
      <?php endif; ?>
      You can retry payment or choose a different method below.
    </p>
    <a href="<?= h(resolve_link('/checkout/')) ?>" class="store-btn-primary" style="display:inline-block; padding:14px 32px;">Retry Payment</a>
    <a href="<?= h(resolve_link('/cart/')) ?>" class="store-btn-secondary" style="display:inline-block; padding:14px 32px; margin-left:12px;">Return to Cart</a>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
