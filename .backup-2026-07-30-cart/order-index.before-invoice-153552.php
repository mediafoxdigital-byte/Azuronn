<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/security.php';
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

require_customer_auth('/account/order/');
$customer = current_customer();
if ($customer === null) {
    redirect(resolve_link('/account/login/'));
}

$orderId = clean_string($_GET['id'] ?? '', 80);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        site_flash_set('error', 'Session expired. Please try again.');
    } else {
        $action = clean_string($_POST['action'] ?? '', 40);
        if ($action === 'request-order-action') {
            $result = customer_request_order_action(
                $orderId,
                clean_string($_POST['request_type'] ?? '', 20),
                clean_multiline($_POST['request_reason'] ?? '', 500)
            );
            site_flash_set(($result['ok'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'Unable to send the request.'));
        }
    }

    redirect(resolve_link('/account/order/?id=' . urlencode($orderId)));
}

$order = customer_order_by_id($customer, $orderId);
if ($order === null) {
    site_flash_set('error', 'Order record was not found for this account.');
    redirect(resolve_link('/account/'));
}

$presented = order_presenter_data($order, $customer);
$pageFlash = site_flash_pull();
$pageTitle = 'Order ' . (string) ($order['id'] ?? '') . ' - ' . SITE_NAME;
$bodyClass = 'account-page order-detail-page';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<section class="account-shell reveal-in">
  <div class="container">
    <div class="account-hero order-hero">
      <div>
        <span class="auth-kicker">Order Detail</span>
        <h1><?= h((string) ($order['id'] ?? 'Invoice')) ?></h1>
        <p>Invoice-style record with payment status, delivery destination, and every product line tied to this order.</p>
      </div>
      <div class="account-hero-actions">
        <a class="store-btn-secondary" href="<?= h(resolve_link('/account/')) ?>">Back to Account</a>
        <a class="store-btn-secondary" href="<?= h(resolve_link('/account/order/invoice/?id=' . urlencode((string) ($order['id'] ?? '')))) ?>">Download PDF</a>
        <button class="store-btn-secondary" type="button" data-print-order>Print Invoice</button>
      </div>
    </div>

    <?php if ($pageFlash !== null): ?>
      <div class="store-flash <?= h($pageFlash['type']) ?>"><?= h($pageFlash['message']) ?></div>
    <?php endif; ?>

    <div class="order-detail-layout">
      <div class="account-panel invoice-panel">
        <div class="invoice-head">
          <div>
            <span class="auth-kicker">Azuronn Invoice</span>
            <h2>Order Summary</h2>
          </div>
          <div class="invoice-head-meta">
            <span class="status-pill"><?= h((string) ($presented['status_label'] ?? '')) ?></span>
            <span class="status-pill"><?= h((string) ($presented['payment_status_label'] ?? '')) ?></span>
            <?php if (is_array($presented['request_summary'] ?? null)): ?>
              <span class="status-pill status-pill-accent"><?= h((string) (($presented['request_summary']['label'] ?? 'Request Submitted'))) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <div class="invoice-grid">
          <article class="invoice-info-card">
            <span>Invoice Number</span>
            <strong><?= h((string) ($order['id'] ?? '')) ?></strong>
            <small>Placed <?= h((string) (($presented['placed_at_formatted'] ?? '') !== '' ? $presented['placed_at_formatted'] : 'Recently')) ?></small>
          </article>
          <article class="invoice-info-card">
            <span>Payment Method</span>
            <strong><?= h((string) ($presented['payment_method_label'] ?? '')) ?></strong>
            <small><?= h((string) (($order['payment_reference'] ?? '') !== '' ? 'Reference ' . $order['payment_reference'] : 'Reference available after dispatch when needed')) ?></small>
          </article>
          <article class="invoice-info-card">
            <span>Customer</span>
            <strong><?= h((string) ($order['customer_name'] ?? ($customer['name'] ?? ''))) ?></strong>
            <small><?= h((string) ($order['customer_email'] ?? ($customer['email'] ?? ''))) ?></small>
          </article>
        </div>

        <div class="invoice-section-head">
          <span class="auth-kicker">Items</span>
          <h2>Order Lines</h2>
        </div>

        <?php if (($presented['items'] ?? []) === []): ?>
          <div class="account-empty account-empty-compact invoice-empty-state">
            <h3>Legacy order record</h3>
            <p>This order was stored before detailed line items were available. Payment and status information are preserved here.</p>
          </div>
        <?php else: ?>
          <div class="invoice-line-list">
            <?php foreach ($presented['items'] as $line): ?>
              <article class="invoice-line-card">
                <div class="invoice-line-media">
                  <img src="<?= h((string) ($line['image'] ?? '')) ?>" alt="<?= h((string) ($line['product_name'] ?? 'Ordered item')) ?>">
                </div>
                <div class="invoice-line-copy">
                  <strong><?= h((string) ($line['product_name'] ?? 'Item')) ?></strong>
                  <span><?= h(line_variant_summary($line)) ?></span>
                  <small>Unit <?= h((string) ($line['price'] ?? money_format(0))) ?> · Qty <?= h((string) ($line['quantity'] ?? 1)) ?></small>
                </div>
                <strong class="invoice-line-total"><?= h((string) ($line['line_total'] ?? money_format(0))) ?></strong>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="invoice-section-head">
          <span class="auth-kicker">Notes</span>
          <h2>Order Notes</h2>
        </div>
        <div class="invoice-note-block">
          <p><?= h((string) (($order['notes'] ?? '') !== '' ? $order['notes'] : 'No additional notes were stored with this order.')) ?></p>
        </div>
      </div>

      <aside class="order-side-stack">
        <?php if (is_array($presented['request_summary'] ?? null)): ?>
          <div class="account-panel order-request-card">
            <span class="auth-kicker">Customer Request</span>
            <h2><?= h((string) ($presented['request_summary']['label'] ?? 'Request Submitted')) ?></h2>
            <p><?= h((string) (($presented['request_summary']['requested_at_formatted'] ?? '') !== '' ? 'Submitted on ' . $presented['request_summary']['requested_at_formatted'] . '.' : 'Your request has been logged on this order.')) ?></p>
            <?php if (($presented['request_summary']['reason'] ?? '') !== ''): ?>
              <div class="invoice-note-block compact-note-block">
                <p><?= h((string) $presented['request_summary']['reason']) ?></p>
              </div>
            <?php endif; ?>
          </div>
        <?php elseif (is_array($presented['available_action'] ?? null)): ?>
          <div class="account-panel order-request-card">
            <span class="auth-kicker">Order Action</span>
            <h2><?= h((string) ($presented['available_action']['headline'] ?? 'Need help with this order?')) ?></h2>
            <p><?= h((string) ($presented['available_action']['description'] ?? '')) ?></p>
            <form method="post" action="<?= h(resolve_link('/account/order/?id=' . urlencode((string) ($order['id'] ?? '')))) ?>" class="auth-form order-request-form">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="request-order-action">
              <input type="hidden" name="request_type" value="<?= h((string) ($presented['available_action']['type'] ?? '')) ?>">
              <label class="store-field">
                <span><?= h((string) ($presented['available_action']['reason_label'] ?? 'Reason')) ?></span>
                <textarea name="request_reason" required placeholder="Add the detail the team should review before responding."></textarea>
              </label>
              <button type="submit" class="store-btn-primary"><?= h((string) ($presented['available_action']['button_label'] ?? 'Send Request')) ?></button>
            </form>
          </div>
        <?php endif; ?>

        <div class="account-panel invoice-summary-card">
          <span class="auth-kicker">Invoice Totals</span>
          <h2><?= h((string) ($presented['total_label'] ?? '')) ?></h2>
          <div class="summary-row"><span>Items</span><strong><?= h((string) ($presented['item_count'] ?? 0)) ?></strong></div>
          <div class="summary-row"><span>Subtotal</span><strong><?= h((string) ($presented['subtotal_label'] ?? '')) ?></strong></div>
          <div class="summary-row"><span>Discount</span><strong>-<?= h((string) ($presented['discount_label'] ?? '')) ?></strong></div>
          <div class="summary-row"><span>Shipping</span><strong><?= h((string) ($presented['shipping_label'] ?? '')) ?></strong></div>
          <?php if (($order['coupon_code'] ?? '') !== ''): ?>
            <div class="summary-row"><span>Coupon</span><strong><?= h((string) $order['coupon_code']) ?></strong></div>
          <?php endif; ?>
          <div class="summary-row summary-row-total"><span>Total</span><strong><?= h((string) ($presented['total_label'] ?? '')) ?></strong></div>
        </div>

        <div class="account-panel invoice-address-card">
          <span class="auth-kicker">Delivery Destination</span>
          <h2>Shipping Address</h2>
          <?php if (($presented['address_lines'] ?? []) === []): ?>
            <p class="invoice-muted-copy">No shipping address was stored on this order record.</p>
          <?php else: ?>
            <div class="invoice-address-block">
              <strong><?= h((string) ($order['customer_name'] ?? ($customer['name'] ?? ''))) ?></strong>
              <?php foreach ($presented['address_lines'] as $line): ?>
                <p><?= h($line) ?></p>
              <?php endforeach; ?>
              <p><?= h((string) (($order['customer_phone'] ?? '') !== '' ? $order['customer_phone'] : ($customer['phone'] ?? ''))) ?></p>
            </div>
          <?php endif; ?>
        </div>

        <div class="account-panel invoice-meta-card">
          <span class="auth-kicker">Status Snapshot</span>
          <h2>Operational Detail</h2>
          <div class="account-details profile-detail-list">
            <div><span>Order Status</span><strong><?= h((string) ($presented['status_label'] ?? '')) ?></strong></div>
            <div><span>Payment Status</span><strong><?= h((string) ($presented['payment_status_label'] ?? '')) ?></strong></div>
            <div><span>Payment Method</span><strong><?= h((string) ($presented['payment_method_label'] ?? '')) ?></strong></div>
            <div><span>Reference</span><strong><?= h((string) (($order['payment_reference'] ?? '') !== '' ? $order['payment_reference'] : 'Not issued')) ?></strong></div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</section>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
