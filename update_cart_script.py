import re

with open('cart/index.php', 'r') as f:
    content = f.read()

new_css = """<style>
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
    content: "\\f00c";
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
    grid-template-columns: 1fr 380px;
    gap: 40px;
    align-items: start;
    margin-top: 0;
  }
  
  .cart-lines-panel {
    background: #fff;
    border: 1px solid #eae1d0;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
  }
  
  .cart-line-card {
    display: grid;
    grid-template-columns: 200px 1fr 150px;
    gap: 30px;
    align-items: stretch;
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
  }
  .cart-media-tile {
    background: #fdfaf5;
    border: 1px solid #eae1d0;
    border-radius: 12px;
    padding: 15px 10px;
    text-align: center;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
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
    width: 100%;
    height: 90px;
    object-fit: contain;
    display: block;
    mix-blend-mode: multiply;
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
    margin-bottom: 20px;
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
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 15px;
  }
  .cart-line-spec {
    padding: 12px 15px;
    border: 1px solid #eae1d0;
    border-radius: 12px;
    background: #fcfcf9;
    display: flex;
    flex-direction: column;
    position: relative;
    padding-left: 35px;
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
    font-size: 0.85rem;
    line-height: 1.4;
    font-weight: 500;
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
    content: "\\f290";
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
    .cart-layout {
      grid-template-columns: 1fr;
    }
    .summary-card {
      position: static;
    }
    .cart-line-card {
      grid-template-columns: 1fr;
    }
    .cart-line-specs {
      grid-template-columns: 1fr;
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
</style>"""

parts = content.split('<style>', 1)
if len(parts) == 2:
    start_content = parts[0]
    end_content = parts[1].split('</style>', 1)[1]
    content = start_content + new_css + end_content

# Hero Replace
old_hero = """<section class="collection-hero reveal-in" style="padding-bottom: 0;">
  <div class="container">
    <h1>Your selected pieces</h1>
    <p>Review selections, apply your coupon, and move into a refined checkout flow.</p>
  </div>
</section>"""
new_hero = """<section class="collection-hero reveal-in" style="padding-bottom: 0;">
  <div class="container">
    <h1>Your selected pieces</h1>
    <p>Review selections, apply your coupon, and move into a refined checkout flow.</p>
    <div class="hero-ornament">
      <span class="hero-ornament-line"></span>
      <i class="far fa-gem"></i>
      <span class="hero-ornament-line"></span>
    </div>
  </div>
</section>"""
content = content.replace(old_hero, new_hero)

# Flash alignment logic
# The original code renders flash inside cart-shell. The css margin: -30px auto 40px !important handles the offset.
# Just need to make sure the flash container has 'style="display: flex"' stripped out of its inline styles so the new css takes effect properly.
content = content.replace('<div class="store-flash <?= h($flash[\'type\']) ?>" style="margin-bottom: 30px; text-align: center;">', '<div style="text-align:center;"><div class="store-flash <?= h($flash[\'type\']) ?>"><?= h($flash[\'message\']) ?></div></div><?php /* ')
content = content.replace('<?= h($flash[\'message\']) ?></div>\n    <?php endif; ?>', ' */ endif; ?>')

# Cart Line Layout logic
def replace_spec(match, icon, name, value):
    return f'<div class="cart-line-spec"><i class="{icon}"></i><span>{name}</span><strong>{value}</strong></div>'

# Actually I'll use simple string replace for the specs.
content = content.replace('<div class="cart-line-spec"><span>Ring Metal</span>', '<div class="cart-line-spec"><i class="far fa-gem"></i><span>Ring Metal</span>')
content = content.replace('<div class="cart-line-spec"><span>Band / Claw</span>', '<div class="cart-line-spec"><i class="fas fa-ring"></i><span>Band / Claw</span>')
content = content.replace('<div class="cart-line-spec"><span>Diamond</span>', '<div class="cart-line-spec"><i class="far fa-gem"></i><span>Diamond</span>')
content = content.replace('<div class="cart-line-spec"><span>Size</span>', '<div class="cart-line-spec"><i class="fas fa-compress-arrows-alt"></i><span>Size</span>')
content = content.replace('<div class="cart-line-spec"><span>Delivery</span>', '<div class="cart-line-spec"><i class="fas fa-truck"></i><span>Delivery</span>')
content = content.replace('<div class="cart-line-spec"><span>Variant</span>', '<div class="cart-line-spec"><i class="fas fa-list-ul"></i><span>Variant</span>')

content = content.replace('<a class="store-link-btn" style="color: #6a7c73; text-decoration: none;" href="<?= h(resolve_link(\'/shop/\')) ?>"><i class="fas fa-arrow-left"></i> Continue Shopping</a>', '<a class="store-link-btn" style="text-decoration: none;" href="<?= h(resolve_link(\'/shop/\')) ?>"><i class="fas fa-arrow-left"></i> CONTINUE SHOPPING</a>')
content = content.replace('<button type="submit" name="action" value="update-cart" class="btn-outline-green">Update Cart</button>', '<button type="submit" name="action" value="update-cart" class="btn-dark-green">UPDATE CART <i class="fas fa-shopping-bag"></i></button>')
content = content.replace('<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px;">', '<div class="cart-footer-actions">')

# Summary Layout logic
content = content.replace('<h2>Order Summary</h2>', '<h2>Order Summary</h2><div class="summary-ornament"><span class="summary-ornament-line"></span><i class="fas fa-star-of-life"></i><span class="summary-ornament-line"></span></div>')
content = content.replace('<input type="text" name="coupon_code" placeholder="Enter coupon code">\n                </label>', '<div class="coupon-input-wrap"><i class="fas fa-tag"></i><input type="text" name="coupon_code" placeholder="Enter coupon code"></div>\n                </label>')
content = content.replace('<button type="submit" class="btn-outline-gold">Apply Coupon</button>', '<button type="submit" class="btn-outline-gold">APPLY COUPON</button>')
content = content.replace('<button type="submit" class="btn-outline-gold">Remove Coupon</button>', '<button type="submit" class="btn-outline-gold">REMOVE COUPON</button>')
content = content.replace('<a class="btn-gold" href="<?= h(resolve_link(\'/checkout/\')) ?>"><?= customer_is_logged_in() ? \'Proceed to Checkout\' : \'Sign In for Checkout\' ?></a>', '<a class="btn-gold" href="<?= h(resolve_link(\'/checkout/\')) ?>"><i class="fas fa-lock"></i> <?= customer_is_logged_in() ? \'PROCEED TO CHECKOUT\' : \'SIGN IN FOR CHECKOUT\' ?></a>')

with open('cart/index.php', 'w') as f:
    f.write(content)
