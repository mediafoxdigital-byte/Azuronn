<?php

declare(strict_types=1);

/**
 * Stripe Checkout Success Page
 *
 * Stripe redirects here after a successful payment with ?session_id=cs_...
 * We verify the session server-side, confirm the order belongs to the
 * logged-in customer, and idempotently flip it to paid/processing.
 */

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/stripe.php';

$customer = current_customer();
$sessionId = clean_string($_GET['session_id'] ?? '', 120);

$confirmed = false;
$orderId = '';
$errorMsg = '';

if ($sessionId === '') {
    $errorMsg = 'Missing session reference. Please contact support if payment was taken.';
} elseif ($customer === null) {
    $errorMsg = 'Please sign in to view your order confirmation.';
} elseif (!stripe_configured()) {
    $errorMsg = 'Payment verification is not available. Please contact support.';
} else {
    $sessionResult = stripe_retrieve_session($sessionId);
    if (!($sessionResult['ok'] ?? false)) {
        $errorMsg = 'Unable to verify payment. Please contact support with your session ID.';
    } else {
        $session = $sessionResult['data'] ?? [];
        $sessionPaymentStatus = strtolower((string) ($session['payment_status'] ?? ''));
        $sessionOrderId = (string) ($session['metadata']['order_id'] ?? '');
        $paymentIntentId = (string) ($session['payment_intent'] ?? '');

        if ($sessionPaymentStatus !== 'paid') {
            $errorMsg = 'Payment has not been confirmed yet. Please wait a moment and refresh, or contact support.';
        } elseif ($sessionOrderId === '') {
            $errorMsg = 'Payment was received but could not be linked to an order. Please contact support.';
        } else {
            // Verify the order belongs to this customer
            $order = customer_order_by_id($customer, $sessionOrderId);
            if ($order === null) {
                $errorMsg = 'Order not found. Please contact support.';
            } else {
                $orderId = $sessionOrderId;
                // Idempotent flip
                stripe_confirm_order_payment($orderId, $paymentIntentId);
                $confirmed = true;
            }
        }
    }
}

$pageTitle = $confirmed ? 'Payment Confirmed - ' . SITE_NAME : 'Payment Status - ' . SITE_NAME;
$bodyClass = 'stripe-result-page';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="checkout-shell reveal-in">
  <div class="container" style="max-width:640px; text-align:center; padding:60px 20px;">
    <?php if ($confirmed): ?>
      <div style="font-size:3rem; margin-bottom:16px;">&#10003;</div>
      <h1 style="font-family:var(--serif); font-size:2rem; margin-bottom:12px;">Payment Confirmed</h1>
      <p style="color:#5c6360; font-size:1rem; line-height:1.7; margin-bottom:24px;">
        Thank you for your purchase. Your order <strong><?= h($orderId) ?></strong> has been placed and is now being processed.
        You will receive a confirmation email shortly.
      </p>
      <a href="<?= h(resolve_link('/account/')) ?>" class="store-btn-primary" style="display:inline-block; padding:14px 32px;">View My Orders</a>
      <a href="<?= h(resolve_link('/')) ?>" class="store-btn-secondary" style="display:inline-block; padding:14px 32px; margin-left:12px;">Continue Shopping</a>
    <?php else: ?>
      <div style="font-size:3rem; margin-bottom:16px;">&#9888;</div>
      <h1 style="font-family:var(--serif); font-size:2rem; margin-bottom:12px;">Payment Not Confirmed</h1>
      <p style="color:#5c6360; font-size:1rem; line-height:1.7; margin-bottom:24px;">
        <?= h($errorMsg) ?>
      </p>
      <a href="<?= h(resolve_link('/cart/')) ?>" class="store-btn-primary" style="display:inline-block; padding:14px 32px;">Return to Cart</a>
      <a href="<?= h(resolve_link('/account/')) ?>" class="store-btn-secondary" style="display:inline-block; padding:14px 32px; margin-left:12px;">My Account</a>
    <?php endif; ?>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
