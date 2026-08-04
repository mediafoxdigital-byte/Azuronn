<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        site_flash_set('error', 'Session expired. Please try again.');
        redirect(resolve_link('/cart/'));
    }

    $action = clean_string($_POST['action'] ?? '', 40);
    $removeKey = clean_string($_POST['remove_key'] ?? '', 80);
    if ($removeKey !== '') {
        cart_remove_item($removeKey);
        site_flash_set('success', 'Item removed from cart.');
    } elseif ($action === 'update-cart') {
        $result = cart_update_items(is_array($_POST['quantities'] ?? null) ? $_POST['quantities'] : []);
        site_flash_set(($result['ok'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'Cart updated.'));
    } elseif ($action === 'apply-coupon') {
        $result = cart_apply_coupon((string) ($_POST['coupon_code'] ?? ''));
        site_flash_set(($result['ok'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'Unable to apply coupon.'));
    } elseif ($action === 'clear-coupon') {
        cart_clear_coupon();
        site_flash_set('success', 'Coupon removed.');
    }

    redirect(resolve_link('/cart/'));
}

$cart = cart_state();
$flash = site_flash_pull();
$pageTitle = 'Cart - ' . SITE_NAME;
$bodyClass = 'cart-page';
$recommended = array_slice(catalog_products(), 0, 4);
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
  body.cart-page {
    background-color: #fdfaf5;
  }

  .store-breadcrumbs {    
    background: transparent;
    padding: 18px 0;
    border-bottom: none;
    font-size: 0.7rem;
    color: #8c8577;
    text-transform: uppercase;
    letter-spacing: 0.14em;
  }
  .store-breadcrumbs a {
    color: #4a453d;
    text-decoration: none;
    transition: color 0.2s;
    font-weight: 500;
  }
  .store-breadcrumbs a:hover {
    color: #c9a96e;
  }
  .store-breadcrumbs span {
    margin: 0 12px;
    color: #d1cbc0;
  }
  .store-breadcrumbs strong {
    color: #c9a96e;
    font-weight: 500;
  }

  .collection-hero {
    background: url('/assets/uploads/cart_hero_bg.png') no-repeat center center;
    background-size: cover;
    padding: 70px 20px 80px;
    text-align: center;
    position: relative;
    border-bottom: none;
  }
  .collection-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(253, 250, 245, 0) 40%, #fdfaf5 100%);
    pointer-events: none;
  }
  .collection-hero .container {
    position: relative;
    z-index: 1;
  }
  .collection-hero h1 {
    font-family: var(--serif);
    font-size: clamp(3rem, 5vw, 4rem);
    color: #1a1a1a;
    margin-bottom: 15px;
    font-weight: 500;
  }
  .collection-hero p {
    max-width: 600px;
    margin: 0 auto 30px;
    color: #4a453d;
    font-size: 1rem;
    line-height: 1.6;
    font-weight: 400;
  }
  .hero-ornament {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-top: 15px;
  }
  .hero-ornament-line {
    height: 1px;
    width: 60px;
    background: #c9a96e;
  }
  .hero-ornament i {
    color: #c9a96e;
    font-size: 0.8rem;
  }

  /* Success banner replacement */
  .store-flash {
    background: #192c25;
    color: #fff;
    border-radius: 30px;
    padding: 12px 25px;
    text-align: center;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 15px rgba(25, 44, 37, 0.15);
    margin: -30px auto 40px !important;
    position: relative;
    z-index: 10;
  }
  .store-flash.success::before {
    content: "\f00c";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    color: #c9a96e;
  }

  .cart-empty-state {
    background: #fff;
    border: 1px solid #eae1d0;
    border-radius: 20px;
    padding: 60px 30px;
    text-align: center;
    margin: 40px 0;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
  }
  .cart-empty-state h3 {
    font-family: var(--serif);
    font-size: 2.2rem;
    color: #1a1a1a;
    margin-bottom: 15px;
    font-weight: 500;
  }
  .cart-empty-state p {
    color: #5a5a5a;
    font-size: 1.05rem;
    max-width: 500px;
    margin: 0 auto 30px;
    line-height: 1.6;
  }

  .cart-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) 380px;
    gap: 38px;
    align-items: start;
    margin-top: 0;
  }
  .cart-shell .container {
    width: min(1560px, calc(100vw - 36px));
    max-width: min(1560px, calc(100vw - 36px));
  }
  
  .cart-lines-panel {
    background: #fff;
    border: 1px solid #eae1d0;
    border-radius: 20px;
    padding: 30px 32px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
  }
  
  .cart-line-card {
    display: grid;
    grid-template-columns: 246px minmax(0, 1fr) 156px;
    gap: 28px;
    align-items: start;
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #eae1d0;
  }
  .cart-line-card:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
  }
  .cart-line-media {
    display: flex;
    gap: 15px;
    text-decoration: none;
    color: inherit;
    align-self: start;
  }
  .cart-media-tile {
    background: #fdfaf5;
    border: 1px solid #eae1d0;
    border-radius: 12px;
    padding: 14px 12px 16px;
    text-align: center;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
    min-height: 190px;
  }
  .cart-media-tile strong {
    display: block;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #4a453d;
    margin-bottom: 15px;
  }
  .cart-media-tile img,
  .cart-media-tile video {
    width: min(100%, 108px);
    height: 108px;
    object-fit: contain;
    display: block;
    mix-blend-mode: multiply;
    margin: auto 0 0;
  }
  .cart-line-type {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    color: #8c8577;
    margin-bottom: 8px;
    display: block;
    font-weight: 600;
  }
  .cart-line-copy h2 {
    font-family: var(--serif);
    font-size: 1.8rem;
    color: #1a1a1a;
    margin-bottom: 18px;
    line-height: 1.2;
    font-weight: 500;
  }
  .cart-line-copy h2 a {
    color: inherit;
    text-decoration: none;
  }
  .cart-line-copy p {
    color: #5a5a5a;
    font-size: 0.95rem;
    margin: 0;
  }
  .cart-line-specs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px 16px;
    margin-top: 15px;
  }
  .cart-line-spec {
    padding: 12px 16px 13px;
    border: 1px solid #eae1d0;
    border-radius: 12px;
    background: #fcfcf9;
    display: flex;
    flex-direction: column;
    position: relative;
    min-height: 74px;
    padding-left: 38px;
  }
  .cart-line-spec.is-wide {
    grid-column: span 2;
  }
  .cart-line-spec i {
    position: absolute;
    left: 12px;
    top: 13px;
    color: #c9a96e;
    font-size: 0.9rem;
  }
  .cart-line-spec span {
    display: block;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #4a453d;
    margin-bottom: 4px;
    font-weight: 600;
  }
  .cart-line-spec strong {
    display: block;
    color: #1a1a1a;
    font-size: 0.92rem;
    line-height: 1.5;
    font-weight: 500;
    overflow-wrap: anywhere;
    word-break: break-word;
  }
  
  .cart-line-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: flex-start;
    gap: 20px;
    padding-top: 10px;
  }
  .store-qty {
    display: flex;
    align-items: center;
    border: 1px solid #eae1d0;
    border-radius: 20px;
    overflow: hidden;
    background: #fff;
    padding: 2px;
  }
  .store-qty button {
    background: transparent;
    border: none;
    padding: 5px 12px;
    color: #c9a96e;
    cursor: pointer;
    font-size: 1.2rem;
    transition: background 0.2s;
  }
  .store-qty input {
    width: 30px;
    text-align: center;
    border: none;
    font-weight: 600;
    color: #1a1a1a;
    background: transparent;
    -moz-appearance: textfield;
  }
  .store-qty input::-webkit-outer-spin-button,
  .store-qty input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }
  .cart-line-price-stack {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
  }
  .cart-line-price-stack span {
    font-size: 0.7rem;
    color: #8c8577;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 600;
  }
  .cart-line-actions strong {
    font-size: 1.4rem;
    color: #1a1a1a;
    font-weight: 600;
  }
  .store-link-btn {
    background: transparent;
    border: none;
    color: #c9a96e;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    cursor: pointer;
    padding: 0;
    text-decoration: underline;
    font-weight: 600;
    transition: color 0.2s;
  }
  .store-link-btn:hover {
    color: #1a1a1a;
  }

  .cart-footer-actions {
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid #eae1d0;
  }

  .summary-card {
    background: #fff;
    border: 1px solid #eae1d0;
    border-radius: 20px;
    padding: 30px;
    position: sticky;
    top: 40px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
  }
  .summary-card h2 {
    font-family: var(--serif);
    font-size: 1.8rem;
    color: #1a1a1a;
    margin-bottom: 20px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
  }
  .summary-card h2::before {
    content: "\f290";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    color: #c9a96e;
    background: #fdfcf9;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: 0 4px 10px rgba(201, 169, 110, 0.15);
  }
  
  .summary-ornament {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 30px;
  }
  .summary-ornament-line {
    height: 1px;
    flex: 1;
    background: #eae1d0;
  }
  .summary-ornament i {
    color: #c9a96e;
    font-size: 0.6rem;
  }

  .summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    color: #4a453d;
    font-size: 0.95rem;
  }
  .summary-row strong {
    color: #1a1a1a;
    font-weight: 600;
  }
  .summary-row-total {
    background: #fdfaf5;
    border: 1px solid #eae1d0;
    border-radius: 10px;
    padding: 20px;
    margin-top: 10px;
    font-size: 1.3rem;
    color: #1a1a1a;
    font-weight: 600;
    align-items: center;
  }
  .coupon-form {
    margin-top: 30px;
    margin-bottom: 25px;
  }
  .coupon-form .store-field span {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #8c8577;
    margin-bottom: 12px;
    display: block;
    font-weight: 600;
  }
  .coupon-input-wrap {
    position: relative;
    margin-bottom: 15px;
  }
  .coupon-input-wrap i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #c9a96e;
  }
  .coupon-form input {
    width: 100%;
    padding: 12px 15px 12px 40px;
    border: 1px solid #eae1d0;
    border-radius: 6px;
    font-family: var(--sans);
    font-size: 0.9rem;
    background: #fff;
    transition: all 0.3s;
    box-sizing: border-box;
  }
  .coupon-form input:focus {
    outline: none;
    border-color: #c9a96e;
  }
  
  /* Buttons */
  .btn-gold {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: #b18861;
    color: #fff;
    border: none;
    font-size: 0.75rem;
    letter-spacing: 0.1em;
    padding: 15px 30px;
    border-radius: 6px;
    text-transform: uppercase;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.3s ease;
    cursor: pointer;
    width: 100%;
    box-sizing: border-box;
  }
  .btn-gold:hover {
    background: #9a7350;
    color: #fff;
  }
  .btn-dark-green {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: #192c25;
    color: #fff;
    border: none;
    font-size: 0.75rem;
    letter-spacing: 0.1em;
    padding: 15px 40px;
    border-radius: 6px;
    text-transform: uppercase;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.3s ease;
    cursor: pointer;
  }
  .btn-dark-green:hover {
    background: #101c18;
  }
  .btn-outline-gold {
    display: inline-block;
    background: transparent;
    color: #b18861;
    border: 1px solid #b18861;
    font-size: 0.75rem;
    letter-spacing: 0.1em;
    padding: 14px 25px;
    border-radius: 6px;
    text-transform: uppercase;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
    width: 100%;
    box-sizing: border-box;
  }
  .btn-outline-gold:hover {
    background: #fdfcf9;
  }
  .summary-pill {
    background: #fdfcf9;
    border: 1px dashed #b18861;
    color: #b18861;
    padding: 12px 15px;
    border-radius: 6px;
    text-align: center;
    font-weight: 600;
    margin-bottom: 15px;
    letter-spacing: 0.05em;
  }

  @media (max-width: 900px) {
    .cart-shell .container {
      width: min(100%, calc(100vw - 24px));
      max-width: min(100%, calc(100vw - 24px));
    }
    .cart-layout {
      grid-template-columns: 1fr;
    }
    .summary-card {
      position: static;
    }
    .cart-line-card {
      grid-template-columns: 1fr;
    }
    .cart-line-media {
      width: 100%;
    }
    .cart-line-specs {
      grid-template-columns: 1fr;
    }
    .cart-line-spec.is-wide {
      grid-column: span 1;
    }
    .cart-line-actions {
      flex-direction: row;
      justify-content: space-between;
      align-items: center;
      border-top: 1px solid #eae1d0;
      padding-top: 15px;
    }
    .cart-footer-actions {
      flex-direction: column;
      gap: 20px;
    }
    .btn-dark-green { width: 100%; }
  }

  /* ============================================================
     CLASSIC / SIMPLE RESTYLE (presentation only — every form,
     field, data-* hook, link and PHP expression is unchanged).
     Appended last so it wins the cascade over the rules above.
     ============================================================ */
  body.cart-page { background: #ffffff; }
  .cart-shell .container {
    width: min(1120px, calc(100vw - 32px));
    max-width: min(1120px, calc(100vw - 32px));
  }

  /* Breadcrumbs — thin, quiet */
  .store-breadcrumbs { background: transparent; padding: 22px 0; border-bottom: 1px solid #ededed; font-size: 0.72rem; letter-spacing: 0.12em; }
  .store-breadcrumbs a { color: #555; }
  .store-breadcrumbs a:hover { color: #1a1a1a; }
  .store-breadcrumbs span { color: #cfcfcf; }
  .store-breadcrumbs strong { color: #1a1a1a; font-weight: 600; }

  /* Header — minimal, no photo, no ornament */
  .collection-hero { background: none; padding: 44px 20px 22px; text-align: left; border-bottom: none; }
  .collection-hero::before { display: none; }
  .collection-hero .container { text-align: left; }
  .collection-hero h1 { font-family: var(--sans); font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 700; color: #1a1a1a; margin: 0 0 6px; letter-spacing: -0.01em; }
  .collection-hero p { margin: 0; color: #777; font-size: 0.92rem; max-width: none; line-height: 1.5; }
  .hero-ornament { display: none; }

  /* Flash — slim neutral bar */
  .store-flash { background: #f3f3f3; color: #333; border-radius: 4px; padding: 10px 18px; font-size: 0.85rem; box-shadow: none; margin: 0 auto 24px !important; }
  .store-flash.success::before { color: #2e7d32; }

  /* Empty state — clean card */
  .cart-empty-state { background: #fff; border: 1px solid #ececec; border-radius: 6px; padding: 60px 30px; box-shadow: none; }
  .cart-empty-state h3 { font-family: var(--sans); font-size: 1.5rem; font-weight: 700; color: #1a1a1a; }

  /* Two-column page: lines | summary */
  .cart-layout { grid-template-columns: minmax(0, 1fr) 340px; gap: 48px; align-items: start; margin-top: 8px; }
  .cart-lines-panel { background: transparent; border: none; border-radius: 0; padding: 0; box-shadow: none; }

  /* Line item — image left, details right, actions as a footer row */
  .cart-line-card {
    grid-template-columns: 176px minmax(0, 1fr);
    gap: 24px 28px;
    align-items: start;
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #ededed;
  }
  .cart-line-card:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
  .cart-line-media { grid-row: 1 / span 2; align-self: start; gap: 10px; }
  .cart-line-copy { grid-column: 2; grid-row: 1; }
  .cart-line-actions {
    grid-column: 2; grid-row: 2;
    flex-direction: row; align-items: center; justify-content: flex-start;
    gap: 18px; margin-top: 18px; padding-top: 16px;
    border-top: 1px solid #f0f0f0;
  }

  /* Flat square thumbnail(s); hide the Ring/Diamond caption for a clean tile */
  .cart-media-tile {
    background: #f4f4f4; border: none; border-radius: 4px; padding: 12px;
    min-height: 0; aspect-ratio: 1 / 1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
  }
  .cart-media-tile strong { display: none; }
  .cart-media-tile img, .cart-media-tile video { width: 100%; height: 100%; object-fit: contain; mix-blend-mode: multiply; margin: 0; display: block; }

  /* Name + eyebrow — clean sans */
  .cart-line-type { font-size: 0.68rem; letter-spacing: 0.14em; color: #9a9a9a; margin-bottom: 6px; font-weight: 600; }
  .cart-line-copy h2 { font-family: var(--sans); font-size: 1.15rem; font-weight: 700; color: #1a1a1a; margin: 0 0 14px; line-height: 1.3; }
  .cart-line-copy h2 a { color: inherit; text-decoration: none; }

  /* Specs — borderless label : value rows (no icon boxes) */
  .cart-line-specs { display: flex; flex-direction: column; gap: 9px; margin-top: 4px; }
  .cart-line-spec {
    padding: 0; border: none; border-radius: 0; background: transparent; min-height: 0;
    display: flex; align-items: flex-start; gap: 10px;
  }
  .cart-line-spec.is-wide { grid-column: auto; }
  .cart-line-spec i { display: none; }
  .cart-line-spec span {
    flex: 0 0 104px; font-size: 0.82rem; text-transform: none; letter-spacing: 0;
    color: #8a8a8a; margin: 0; font-weight: 400; line-height: 1.4;
  }
  .cart-line-spec span::after { content: ":"; }
  .cart-line-spec strong { font-size: 0.9rem; color: #1a1a1a; font-weight: 600; line-height: 1.4; }
  .cart-metal-dot {
    display: inline-block; width: 12px; height: 12px; border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.12); vertical-align: -1px; margin-right: 7px;
  }
  .cart-line-copy p.cart-line-note { font-size: 0.78rem; color: #999; margin: 10px 0 0; line-height: 1.4; }

  /* Quantity / total / remove row */
  .store-qty { border: 1px solid #d9d9d9; border-radius: 4px; padding: 0; background: #fff; }
  .store-qty button { color: #444; padding: 6px 12px; font-size: 1rem; }
  .store-qty button:hover { background: #f3f3f3; }
  .store-qty input { width: 34px; font-weight: 600; color: #1a1a1a; }
  .cart-line-price-stack { margin-left: auto; flex-direction: row; align-items: baseline; gap: 8px; }
  .cart-line-price-stack span { font-size: 0.68rem; color: #9a9a9a; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
  .cart-line-actions strong { font-size: 1.1rem; color: #1a1a1a; font-weight: 700; }
  .store-link-btn { color: #9a9a9a; text-decoration: underline; text-underline-offset: 3px; font-size: 0.72rem; letter-spacing: 0.08em; }
  .store-link-btn:hover { color: #1a1a1a; }

  .cart-footer-actions { margin-top: 30px; padding-top: 24px; border-top: 1px solid #ededed; }

  /* Order summary — clean panel, left aligned, hairline rows */
  .summary-card { background: #fff; border: 1px solid #ededed; border-radius: 6px; padding: 26px 24px; box-shadow: none; position: sticky; top: 24px; }
  .summary-card h2 { font-family: var(--sans); font-size: 1.15rem; font-weight: 700; color: #1a1a1a; text-align: left; justify-content: flex-start; margin: 0 0 18px; gap: 0; }
  .summary-card h2::before { display: none; }
  .summary-ornament { display: none; }
  .summary-row { margin-bottom: 14px; font-size: 0.9rem; color: #555; }
  .summary-row span { color: #555; }
  .summary-row strong { color: #1a1a1a; font-weight: 600; }
  .summary-row-total { background: transparent; border: none; border-top: 1px solid #ededed; border-radius: 0; padding: 16px 0 0; margin-top: 6px; font-size: 1.1rem; }

  /* Coupon — underline input + boxed Apply on one row */
  .coupon-form { display: flex; align-items: flex-end; gap: 12px; margin: 22px 0 18px; }
  .coupon-form .store-field { flex: 1 1 auto; margin: 0; }
  .coupon-form .store-field span { display: none; }
  .coupon-input-wrap { position: relative; margin: 0; }
  .coupon-input-wrap i { display: none; }
  .coupon-form input { width: 100%; padding: 10px 2px; border: none; border-bottom: 1px solid #cfcfcf; border-radius: 0; background: transparent; font-size: 0.82rem; letter-spacing: 0.05em; box-sizing: border-box; }
  .coupon-form input::placeholder { text-transform: uppercase; color: #9a9a9a; letter-spacing: 0.1em; font-size: 0.72rem; }
  .coupon-form input:focus { outline: none; border-bottom-color: #1a1a1a; }
  .coupon-form .btn-outline-gold { flex: 0 0 auto; width: auto; align-self: flex-end; padding: 11px 20px; }
  .coupon-form .summary-pill { flex: 1 1 auto; margin: 0; align-self: flex-end; }

  /* Buttons — classic hierarchy: dark checkout, ghost update, outline apply */
  .btn-gold { background: #22303f; color: #fff; border: none; border-radius: 3px; font-size: 0.78rem; letter-spacing: 0.1em; padding: 15px 24px; }
  .btn-gold:hover { background: #131c27; color: #fff; }
  .btn-dark-green { background: transparent; color: #1a1a1a; border: 1px solid #cfcfcf; border-radius: 3px; padding: 13px 22px; }
  .btn-dark-green:hover { background: transparent; border-color: #1a1a1a; color: #1a1a1a; }
  .btn-outline-gold { background: #fff; color: #1a1a1a; border: 1px solid #1a1a1a; border-radius: 3px; font-size: 0.72rem; letter-spacing: 0.08em; padding: 11px 20px; }
  .btn-outline-gold:hover { background: #1a1a1a; color: #fff; }
  .summary-pill { background: #f4f4f4; border: 1px solid #d9d9d9; color: #1a1a1a; border-radius: 3px; padding: 10px 12px; letter-spacing: 0.06em; font-size: 0.8rem; }

  @media (max-width: 900px) {
    .cart-shell .container { width: min(100%, calc(100vw - 24px)); max-width: min(100%, calc(100vw - 24px)); }
    .cart-layout { grid-template-columns: 1fr; gap: 32px; }
    .summary-card { position: static; }
    .cart-line-card { grid-template-columns: 1fr; }
    .cart-line-media { grid-row: auto; width: 168px; }
    .cart-line-copy { grid-column: 1; grid-row: auto; }
    .cart-line-actions { grid-column: 1; grid-row: auto; justify-content: space-between; margin-top: 14px; }
    .cart-line-price-stack { margin-left: 0; }
    .cart-footer-actions { flex-direction: column; gap: 14px; align-items: stretch; }
    .btn-dark-green { width: 100%; }
    .btn-gold { width: 100%; }
    .coupon-form { flex-wrap: wrap; }
  }
  @media (max-width: 520px) {
    .cart-line-media { width: 100%; }
  }
</style>

<div class="store-breadcrumbs">
  <div class="container">
    <a href="<?= h(resolve_link('/')) ?>">Home</a>
    <span>/</span>
    <strong>Your Cart</strong>
  </div>
</div>

<section class="collection-hero reveal-in" style="padding-bottom: 0;">
  <div class="container">
    <h1>Your selected pieces</h1>
    <p>Review selections, apply your coupon, and move into a refined checkout flow.</p>
    <div class="hero-ornament">
      <span class="hero-ornament-line"></span>
      <i class="far fa-gem"></i>
      <span class="hero-ornament-line"></span>
    </div>
  </div>
</section>

<section class="cart-shell reveal-in" style="padding-top: 20px; padding-bottom: 80px;">
  <div class="container">

    <?php if ($flash !== null): ?>
      <div style="text-align:center;"><div class="store-flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div></div><?php /*  */ endif; ?>

    <?php if ($cart['items'] === []): ?>
      <div class="cart-empty-state">
        <h3>Your cart is empty</h3>
        <p>Start with the catalog and add products with your preferred color and size.</p>
        <a class="btn-gold" href="<?= h(resolve_link('/shop/')) ?>" style="width: auto;">Browse Collection</a>
      </div>
    <?php else: ?>
      <div class="cart-layout">
        <div class="cart-lines-panel">
          <form method="post" action="<?= h(resolve_link('/cart/')) ?>" class="cart-lines-form">
            <?php csrf_field(); ?>
            <div class="cart-line-list">
              <?php foreach ($cart['items'] as $line): ?>
                <article class="cart-line-card">
                  <a href="<?= h($line['url']) ?>" class="cart-line-media">
                    <div class="cart-media-tile">
                      <strong>Ring</strong>
                      <?= store_media_markup((string) ($line['ring_media'] ?? ''), (string) ($line['ring_media_alt'] ?? ($line['product']['name'] ?? 'Ring')), 'cart-line-media-asset') ?>
                    </div>
                    <?php if ((string) ($line['diamond_image'] ?? '') !== ''): ?>
                      <div class="cart-media-tile">
                        <strong>Diamond</strong>
                        <?= store_media_markup((string) ($line['diamond_image'] ?? ''), (string) ($line['diamond_title'] ?? 'Diamond'), 'cart-line-media-asset') ?>
                      </div>
                    <?php endif; ?>
                  </a>
                  <div class="cart-line-copy">
                    <span class="cart-line-type"><?= h($line['product']['product_type']) ?> / <?= h($line['product']['category']) ?></span>
                    <h2><a href="<?= h($line['url']) ?>"><?= h($line['product']['name']) ?></a></h2>
                    <div class="cart-line-specs">
                      <div class="cart-line-spec"><?php $cartMetalHex = (string) ($line['metal_color_hex'] ?? ''); ?><i class="far fa-gem"></i><span>Ring Metal</span><strong><?php if ($cartMetalHex !== ''): ?><span class="cart-metal-dot" style="background:<?= h($cartMetalHex) ?>;" aria-hidden="true"></span><?php endif; ?><?= h((string) ($line['metal_label'] ?? '')) ?></strong></div>
                      <?php if ((string) ($line['band_claw_metal_label'] ?? '') !== ''): ?>
                        <div class="cart-line-spec is-wide"><i class="fas fa-ring"></i><span>Band / Claw</span><strong><?= h((string) ($line['band_claw_metal_label'] ?? '')) ?></strong></div>
                      <?php endif; ?>
                      <?php if ((string) ($line['diamond_title'] ?? '') !== ''): ?>
                        <div class="cart-line-spec"><i class="far fa-gem"></i><span>Diamond</span><strong><?= h((string) ($line['diamond_title'] ?? '')) ?></strong></div>
                      <?php endif; ?>
                      <?php if ((string) ($line['size'] ?? '') !== ''): ?>
                        <div class="cart-line-spec"><i class="fas fa-compress-arrows-alt"></i><span>Size</span><strong><?= h((string) ($line['size'] ?? '')) ?></strong></div>
                      <?php endif; ?>
                      <div class="cart-line-spec"><i class="fas fa-truck"></i><span>Delivery</span><strong><?= h((string) (($line['delivery_surcharge'] ?? 0) > 0 ? 'Priority Delivery' : 'Basic Delivery')) ?></strong></div>
                      <div class="cart-line-spec is-wide"><i class="fas fa-list-ul"></i><span>Variant</span><strong><?= h(line_variant_summary($line)) ?></strong></div>
                    </div>
                    <?php if (($line['delivery_surcharge'] ?? 0) > 0): ?>
                      <p class="cart-line-note">Priority delivery upgrade: <?= h((string) $line['delivery_surcharge_label']) ?> each</p>
                    <?php endif; ?>
                  </div>
                  <div class="cart-line-actions">
                    <div class="store-qty" data-qty-wrap>
                      <button type="button" data-qty-step="-1">−</button>
                      <input type="number" min="1" max="99" name="quantities[<?= h($line['key']) ?>]" value="<?= h((string) $line['quantity']) ?>" data-qty-input>
                      <button type="button" data-qty-step="1">+</button>
                    </div>
                    <div class="cart-line-price-stack">
                      <span>Total</span>
                      <strong><?= h($line['line_total_label']) ?></strong>
                    </div>
                    <button type="submit" name="remove_key" value="<?= h($line['key']) ?>" class="store-link-btn">Remove</button>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
            
            <div class="cart-footer-actions">
                <a class="store-link-btn" style="text-decoration: none;" href="<?= h(resolve_link('/shop/')) ?>"><i class="fas fa-arrow-left"></i> CONTINUE SHOPPING</a>
                <button type="submit" name="action" value="update-cart" class="btn-dark-green">UPDATE CART <i class="fas fa-shopping-bag"></i></button>
            </div>
          </form>
        </div>

        <aside class="summary-panel">
          <div class="summary-card">
            <h2>Order Summary</h2><div class="summary-ornament"><span class="summary-ornament-line"></span><i class="fas fa-star-of-life"></i><span class="summary-ornament-line"></span></div>
            <div class="summary-row"><span>Subtotal</span><strong><?= h($cart['subtotal_label']) ?></strong></div>
            <div class="summary-row"><span><?= h((string) ($cart['delivery_summary_label'] ?? 'Delivery')) ?></span><strong><?= h((string) ($cart['delivery_total_label'] ?? 'Free')) ?></strong></div>
            <div class="summary-row"><span>Shipping</span><strong><?= h((string) $cart['shipping_label']) ?></strong></div>
            <?php if ($cart['discount'] > 0): ?>
              <div class="summary-row"><span>Discount</span><strong>-<?= h($cart['discount_label']) ?></strong></div>
            <?php endif; ?>
            <div class="summary-row summary-row-total"><span>Total</span><strong><?= h($cart['total_label']) ?></strong></div>

            <form method="post" action="<?= h(resolve_link('/cart/')) ?>" class="coupon-form">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="<?= $cart['coupon_code'] !== '' ? 'clear-coupon' : 'apply-coupon' ?>">
              <?php if ($cart['coupon_code'] === ''): ?>
                <label class="store-field">
                  <span>Coupon Code</span>
                  <div class="coupon-input-wrap"><i class="fas fa-tag"></i><input type="text" name="coupon_code" placeholder="Enter coupon code"></div>
                </label>
                <button type="submit" class="btn-outline-gold">APPLY COUPON</button>
              <?php else: ?>
                <div class="summary-pill"><?= h($cart['coupon_code']) ?></div>
                <button type="submit" class="btn-outline-gold">REMOVE COUPON</button>
              <?php endif; ?>
            </form>

            <a class="btn-gold" href="<?= h(resolve_link('/checkout/')) ?>"><i class="fas fa-lock"></i> <?= customer_is_logged_in() ? 'PROCEED TO CHECKOUT' : 'SIGN IN FOR CHECKOUT' ?></a>
          </div>
        </aside>
      </div>
    <?php endif; ?>

    <div class="commerce-related" style="margin-top: 80px;">
      <div class="sec-hdr-premium">
        <span class="shop-style-kicker">We Think You'll Love</span>
        <div class="sec-hdr-title-row">
            <span class="sec-line"></span>
            <h2>Recommended Pieces</h2>
            <span class="sec-line"></span>
        </div>
      </div>
      <div class="shop-product-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px;">
        <?php foreach ($recommended as $product): ?>
          <?php render_product_card($product); ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
