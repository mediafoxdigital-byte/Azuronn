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

<style>
  /* ============================================================
     ACCOUNT PAGE — Classic / elegant override (presentation only).
     Mirrors the cart / checkout / wishlist design language.
     Scoped to .account-page. The HERO (.premium-account-hero and
     every child: hero-kicker / hero-heading / hero-btn-*) is a
     SIBLING that lives OUTSIDE .premium-account-main, and NO rule
     below targets it or its ancestors' visual properties — so the
     hero is byte-for-byte unchanged. Only custom-property
     declarations sit on .account-page (they render nothing).
     No markup, form, CSRF token, link or order action is changed.
     ============================================================ */
  .account-page {
    --ac-serif: 'Cormorant Garamond', 'Jost', Georgia, serif;
    --ac-sans: 'Jost', 'Helvetica Neue', Arial, sans-serif;
    --ac-ink: #1c1c1c;
    --ac-ink-soft: #6b6b6b;
    --ac-mute: #9a948a;
    --ac-gold: #b08d57;
    --ac-line: #e7e2d9;
    --ac-bg-tint: #fbfaf7;
  }

  /* Base type for the content area only (hero is outside this node). */
  .account-page .premium-account-main {
    font-family: var(--ac-sans);
    color: var(--ac-ink);
    gap: 44px;
  }
  .account-page .premium-account-col { gap: 0; }

  /* ---- Flash banner (classic, like cart/checkout) ---- */
  .account-page .store-flash {
    background: var(--ac-bg-tint);
    color: var(--ac-ink);
    border: 1px solid var(--ac-line);
    border-radius: 4px;
    padding: 11px 20px;
    text-align: center;
    font-size: 0.85rem;
    box-shadow: none;
    margin: 0 0 8px;
  }

  /* ---- Panels -> open editorial sections (no cream box / watermark) ---- */
  .account-page .premium-account-panel {
    background: transparent;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    overflow: visible;
    padding: 0 0 40px;
    margin: 0 0 40px;
    border-bottom: 1px solid var(--ac-line);
  }
  .account-page .premium-account-col > .premium-account-panel:last-child {
    border-bottom: 0;
    padding-bottom: 0;
    margin-bottom: 0;
  }
  .account-page .panel-watermark-bottom::after,
  .account-page .panel-watermark-right::after { display: none; }

  .account-page .panel-header { margin-bottom: 22px; align-items: flex-end; }
  .account-page .panel-title {
    font-family: var(--ac-serif);
    font-size: 1.4rem;
    font-weight: 500;
    color: var(--ac-ink);
    font-variant: normal;
    text-transform: none;
    letter-spacing: 0.04em;
    gap: 0;
  }
  .account-page .panel-title i { display: none; }

  /* Panel action links / buttons -> classic square outline / text link */
  .account-page .panel-action {
    color: var(--ac-mute);
    font-family: var(--ac-sans);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }
  .account-page .panel-action:hover { color: var(--ac-gold); }
  .account-page .panel-action-btn {
    color: var(--ac-ink);
    border: 1px solid var(--ac-line);
    border-radius: 2px;
    padding: 9px 16px;
    background: transparent;
    font-family: var(--ac-sans);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    transition: border-color .25s, color .25s, background .25s;
  }
  .account-page .panel-action-btn:hover {
    border-color: var(--ac-ink);
    color: var(--ac-ink);
    background: transparent;
  }

  /* ---- Account snapshot: flat white stat cards + clean detail rows ---- */
  .account-page .snapshot-grid { gap: 16px; margin-bottom: 30px; }
  .account-page .snapshot-item {
    background: #fff;
    border: 1px solid var(--ac-line);
    border-radius: 3px;
    box-shadow: none;
    padding: 22px 16px;
  }
  .account-page .snapshot-item i {
    color: var(--ac-gold);
    -webkit-text-stroke: 0;
    font-size: 1.25rem;
    margin-bottom: 4px;
  }
  .account-page .snapshot-item strong {
    font-family: var(--ac-serif);
    font-size: 1.7rem;
    font-weight: 500;
    color: var(--ac-ink);
  }
  .account-page .snapshot-item span {
    font-size: 0.66rem;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: var(--ac-mute);
    font-weight: 600;
  }
  .account-page .snapshot-details { gap: 22px 28px; }
  .account-page .detail-row { padding-bottom: 14px; border-bottom: 1px solid var(--ac-line); }
  .account-page .detail-row i { color: var(--ac-gold); font-size: 0.95rem; }
  .account-page .detail-row span {
    color: var(--ac-mute);
    font-size: 0.62rem;
    letter-spacing: 0.14em;
    font-weight: 600;
  }
  .account-page .detail-row strong { color: var(--ac-ink); font-weight: 500; font-size: 0.92rem; }

  /* ---- Empty states: flat framed box, serif heading, classic button ---- */
  .account-page .premium-empty-state {
    background: #fff;
    border: 1px solid var(--ac-line);
    border-radius: 4px;
    padding: 54px 24px;
  }
  .account-page .premium-empty-state .icon-circle {
    background: transparent;
    box-shadow: none;
    border: 1px solid var(--ac-line);
  }
  .account-page .premium-empty-state .icon-circle i,
  .account-page .premium-empty-state > i {
    color: var(--ac-gold);
    -webkit-text-stroke: 0;
    opacity: 1;
  }
  .account-page .premium-empty-state h3 {
    font-family: var(--ac-serif);
    font-size: 1.8rem;
    font-weight: 500;
    color: var(--ac-ink);
  }
  .account-page .premium-empty-state p { color: var(--ac-ink-soft); }
  .account-page .btn-solid-gold {
    background: var(--ac-ink);
    color: #fff;
    border: 1px solid var(--ac-ink);
    border-radius: 2px;
    box-shadow: none;
    padding: 13px 26px;
    font-family: var(--ac-sans);
    font-size: 0.7rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    transition: background .3s, border-color .3s;
  }
  .account-page .btn-solid-gold:hover {
    background: var(--ac-gold);
    border-color: var(--ac-gold);
    color: #fff;
    transform: none;
    box-shadow: none;
  }

  /* ---- Address cards: flat, hairline, square ---- */
  .account-page .address-card {
    background: #fff;
    border: 1px solid var(--ac-line);
    border-radius: 3px;
    box-shadow: none;
    padding: 18px 20px;
  }
  .account-page .address-card-top strong {
    font-family: var(--ac-serif);
    font-size: 1.2rem;
    font-weight: 500;
    color: var(--ac-ink);
  }
  .account-page .address-card-top span {
    color: var(--ac-mute);
    font-size: 0.66rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    font-weight: 600;
  }
  .account-page .address-card p { color: var(--ac-ink-soft); }
  .account-page .address-card-actions { gap: 16px; }
  .account-page .store-link-inline,
  .account-page .store-link-btn {
    color: var(--ac-mute);
    font-family: var(--ac-sans);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    text-decoration: none;
    background: transparent;
    border: 0;
    padding: 0;
    cursor: pointer;
    transition: color .2s;
  }
  .account-page .store-link-inline:hover,
  .account-page .store-link-btn:hover { color: var(--ac-gold); }

  /* ---- Address form: clean thin-border fields + ink submit ---- */
  .account-page .premium-field span {
    color: var(--ac-mute);
    font-size: 0.62rem;
    letter-spacing: 0.14em;
    font-weight: 600;
  }
  .account-page .premium-field input,
  .account-page .premium-field select {
    border: 1px solid var(--ac-line);
    border-radius: 3px;
    background: #fff;
    color: var(--ac-ink);
    font-family: var(--ac-sans);
    padding: 12px 14px;
    transition: border-color .2s, box-shadow .2s;
  }
  .account-page .premium-field input:focus,
  .account-page .premium-field select:focus {
    border-color: var(--ac-gold);
    box-shadow: 0 0 0 1px rgba(176, 141, 87, 0.18);
    outline: none;
  }
  .account-page .btn-full-gold {
    background: var(--ac-ink);
    color: #fff;
    border: 1px solid var(--ac-ink);
    border-radius: 2px;
    box-shadow: none;
    font-family: var(--ac-sans);
    font-size: 0.72rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    transition: background .3s, border-color .3s;
  }
  .account-page .btn-full-gold:hover {
    background: var(--ac-gold);
    border-color: var(--ac-gold);
    color: #fff;
    transform: none;
    box-shadow: none;
  }

  /* ---- Order cards: flat framed, serif id, clean summary + lines ---- */
  .account-page .order-card {
    background: #fff;
    border: 1px solid var(--ac-line);
    border-radius: 4px;
    box-shadow: none;
    padding: 22px 24px;
  }
  .account-page .order-card-top { align-items: flex-start; }
  .account-page .order-card-top > div:first-child {
    display: flex;
    flex-direction: column;
    gap: 3px;
  }
  .account-page .order-card-top strong {
    font-family: var(--ac-serif);
    font-size: 1.25rem;
    font-weight: 500;
    color: var(--ac-ink);
  }
  .account-page .order-card-top span {
    color: var(--ac-mute);
    font-size: 0.72rem;
    letter-spacing: 0.04em;
  }
  .account-page .order-card-meta { gap: 8px; }
  .account-page .status-pill {
    background: transparent;
    border: 1px solid var(--ac-line);
    border-radius: 2px;
    color: var(--ac-ink-soft);
    font-family: var(--ac-sans);
    font-size: 0.6rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 4px 9px;
  }
  .account-page .status-pill-accent {
    border-color: var(--ac-gold);
    color: var(--ac-gold);
  }
  .account-page .order-card-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    border: 1px solid var(--ac-line);
    border-radius: 3px;
    margin: 16px 0;
    overflow: hidden;
  }
  .account-page .order-card-summary > div {
    padding: 12px 16px;
    border-right: 1px solid var(--ac-line);
  }
  .account-page .order-card-summary > div:last-child { border-right: 0; }
  .account-page .order-card-summary div span {
    color: var(--ac-mute);
    font-size: 0.62rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    font-weight: 600;
  }
  .account-page .order-card-summary div strong { color: var(--ac-ink); font-weight: 500; }
  .account-page .order-line-list { gap: 0; }
  .account-page .order-line-item {
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--ac-line);
  }
  .account-page .order-line-item:last-child { border-bottom: 0; }
  .account-page .order-line-item img {
    width: 56px;
    height: 56px;
    object-fit: contain;
    border: 1px solid var(--ac-line);
    border-radius: 3px;
    background: var(--ac-bg-tint);
    padding: 5px;
    mix-blend-mode: multiply;
  }
  .account-page .order-line-item strong {
    font-family: var(--ac-serif);
    font-size: 1.05rem;
    font-weight: 500;
    color: var(--ac-ink);
  }
  .account-page .order-line-item span {
    color: var(--ac-mute);
    font-size: 0.72rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }
  .account-page .order-line-item > strong:last-child {
    font-family: var(--ac-sans);
    font-size: 0.95rem;
    font-weight: 600;
  }
  .account-page .order-card-actions { margin-top: 6px; }
  .account-page .store-btn-secondary {
    background: transparent;
    color: var(--ac-ink);
    border: 1px solid var(--ac-ink);
    border-radius: 2px;
    padding: 11px 22px;
    font-family: var(--ac-sans);
    font-size: 0.68rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background .25s, color .25s;
  }
  .account-page .store-btn-secondary:hover { background: var(--ac-ink); color: #fff; }

  /* ---- Wishlist mini list: open rows, framed thumbnails, square actions ---- */
  .account-page .premium-wishlist-mini-list { gap: 0; }
  .account-page .mini-wishlist-card {
    display: grid;
    grid-template-columns: 64px minmax(0, 1fr) auto;
    gap: 16px;
    align-items: center;
    background: transparent;
    border: 0;
    border-bottom: 1px solid var(--ac-line);
    border-radius: 0;
    box-shadow: none;
    padding: 14px 0;
  }
  .account-page .mini-wishlist-card:last-child { border-bottom: 0; }
  .account-page .mini-wishlist-card img {
    width: 64px;
    height: 64px;
    object-fit: contain;
    border: 1px solid var(--ac-line);
    border-radius: 3px;
    background: var(--ac-bg-tint);
    padding: 6px;
    mix-blend-mode: multiply;
  }
  .account-page .mini-card-info strong {
    font-family: var(--ac-serif);
    font-size: 1.1rem;
    font-weight: 500;
    color: var(--ac-ink);
  }
  .account-page .mini-card-info span {
    color: var(--ac-mute);
    font-size: 0.66rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    font-weight: 600;
  }
  .account-page .action-btn {
    width: 34px;
    height: 34px;
    border-radius: 2px;
    border: 1px solid var(--ac-line);
    background: #fff;
    color: var(--ac-ink-soft);
    transition: border-color .2s, color .2s, background .2s;
  }
  .account-page .action-btn:hover { border-color: var(--ac-ink); color: var(--ac-ink); background: #fff; }
  .account-page .mini-card-actions form:first-child .action-btn:hover {
    border-color: #d98c8c;
    color: #b23a48;
    background: #fdf3f3;
  }
  .account-page .panel-footer {
    border-top: 1px solid var(--ac-line);
    margin-top: 8px;
    padding-top: 16px;
  }
  .account-page .footer-note { color: var(--ac-mute); font-size: 0.78rem; }
  .account-page .footer-note i { color: var(--ac-gold); }
  .account-page .footer-link {
    color: var(--ac-ink);
    font-family: var(--ac-sans);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    transition: color .2s;
  }
  .account-page .footer-link:hover { color: var(--ac-gold); }

  /* ---- Responsive ---- */
  @media (max-width: 900px) {
    .account-page .premium-account-main { grid-template-columns: 1fr; gap: 8px; }
  }
  @media (max-width: 560px) {
    .account-page .snapshot-grid { grid-template-columns: 1fr; }
    .account-page .snapshot-details { grid-template-columns: 1fr; }
    .account-page .order-card-summary { grid-template-columns: 1fr; }
    .account-page .order-card-summary > div { border-right: 0; border-bottom: 1px solid var(--ac-line); }
    .account-page .order-card-summary > div:last-child { border-bottom: 0; }
  }
</style>

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
