import re

with open('shop/index.php', 'r') as f:
    content = f.read()

new_css = """<style>
  body.shop-page {
    background: #fdfaf5;
  }

  .store-breadcrumbs {
    background: transparent;
    padding: 16px 0;
    font-size: 0.7rem;
    color: #8c8577;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    border-bottom: none;
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
    margin: 0 10px;
    color: #d1cbc0;
  }
  .store-breadcrumbs strong {
    color: #c9a96e;
    font-weight: 500;
  }

  .collection-hero {
    background: transparent;
    padding: 54px 20px 40px;
    text-align: center;
  }
  .collection-hero.ring-journey-hero {
    padding: 90px 20px 140px;
    background: url('/assets/uploads/luxurious_ring_bg.png') no-repeat center center;
    background-size: cover;
    border-bottom: none;
    position: relative;
    text-align: left;
  }
  .collection-hero.ring-journey-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, #fdfaf5 0%, rgba(253, 250, 245, 0.8) 40%, rgba(253, 250, 245, 0) 100%);
    pointer-events: none;
  }
  .collection-hero.ring-journey-hero .container {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
    padding-left: 5%;
  }
  .collection-hero h1 {
    font-family: var(--serif, serif);
    font-size: clamp(4rem, 7vw, 6.5rem);
    color: #1a1a1a;
    margin-bottom: 20px;
    font-weight: 400;
    line-height: 0.95;
    letter-spacing: -0.02em;
    display: flex;
    align-items: flex-start;
  }
  .collection-hero h1 span {
    font-size: 0.3em;
    color: #c9a96e;
    margin-left: 10px;
    margin-top: 15px;
  }
  .collection-hero p {
    max-width: 480px;
    margin: 0;
    color: #5a5a5a;
    font-size: 1.05rem;
    line-height: 1.6;
    font-weight: 400;
  }

  /* Custom badge in hero */
  .premium-hero-badge {
    position: absolute;
    top: 50%;
    right: 15%;
    transform: translateY(-70%);
    width: 140px;
    height: 140px;
    background: transparent;
    border-radius: 50%;
    border: 1px solid rgba(201, 169, 110, 0.3);
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .premium-hero-badge::before {
    content: "";
    position: absolute;
    inset: 5px;
    border: 1px dashed rgba(201, 169, 110, 0.5);
    border-radius: 50%;
  }
  .premium-hero-badge svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    animation: rotate-slow 20s linear infinite;
  }
  .premium-hero-badge .center-icon {
    position: absolute;
    color: #c9a96e;
    font-size: 1.8rem;
    animation: none;
  }
  @keyframes rotate-slow {
    100% { transform: rotate(360deg); }
  }

  .ring-journey-overview {
    background: transparent;
    border-bottom: none;
    margin-top: -60px; /* Overlap without squishing */
    position: relative;
    z-index: 5;
  }
  .premium-step-banner {
    background: #fcfcf9;
    width: 90%;
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 80px;
    font-family: var(--sans, sans-serif);
    border-radius: 8px 8px 0 0;
    border-top: 1px solid #eae1d0;
    box-shadow: 0 -10px 30px rgba(0,0,0,0.02);
  }
  .step-banner-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 40px;
    font-size: 0.8rem;
    font-weight: 500;
    letter-spacing: 0.12em;
    color: #a39f98;
    text-transform: uppercase;
    white-space: nowrap;
  }
  .step-banner-item.is-active {
    color: #1a1a1a;
    font-weight: 600;
  }
  .step-banner-item.start-text {
    font-style: italic;
    font-family: var(--serif, serif);
    font-size: 1.4rem;
    color: #1a1a1a;
    padding-right: 40px;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 400;
  }
  .step-banner-item.start-text::after {
    content: "✦";
    color: #c9a96e;
    font-size: 0.6em;
    margin-left: 8px;
    vertical-align: super;
    font-style: normal;
  }
  .step-banner-item span {
    font-size: 1.2rem;
    font-family: var(--serif, serif);
    color: inherit;
    font-weight: 400;
  }
  .step-banner-item.is-active span {
    color: #c9a96e;
  }
  .step-separator {
    width: 1px;
    height: 30px;
    background: #eae1d0;
    transform: none;
  }

  .ring-style-showcase {
    padding: 40px 0 50px;
    background: #fdfaf5;
  }
  .style-selector-row {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-bottom: 0;
    flex-wrap: wrap;
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
    position: relative;
    z-index: 2;
  }
  .ring-style-selector-row {
    row-gap: 30px;
  }
  .style-selector-form {
    margin: 0;
  }
  .style-selector-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #8c8577;
    opacity: 1;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    width: 90px;
    position: relative;
    z-index: 3;
    cursor: pointer;
    border: 0;
    background: transparent;
    font: inherit;
    padding: 0;
  }
  .style-selector-item:hover, .style-selector-item.is-active {
    transform: translateY(-5px);
    color: #1a1a1a;
  }
  .style-selector-item img {
    width: 84px;
    height: 84px;
    object-fit: contain;
    border-radius: 50%;
    margin-bottom: 12px;
    border: 2px solid transparent;
    background: #fff;
    padding: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.04);
    transition: all 0.3s ease;
  }
  .style-selector-item:hover img, .style-selector-item.is-active img {
    border-color: #c9a96e;
    box-shadow: 0 12px 25px rgba(201, 169, 110, 0.15);
  }
  .style-selector-item span {
    font-size: 0.65rem;
    font-weight: 600;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    line-height: 1.45;
    transition: color 0.3s ease;
  }

  .collection-filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 0 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    margin-bottom: 30px;
    background: transparent;
  }
  .filter-group {
    display: flex;
    gap: 20px;
    align-items: center;
  }
  .filter-label {
    font-weight: 500;
    color: #e5dac4;
    margin-right: 5px;
    font-size: 0.9rem;
  }
  .filter-dropdown {
    background: transparent;
    border: none;
    font-size: 0.9rem;
    color: #ffffff;
    font-weight: 400;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    outline: none;
  }
  .filter-dropdown option {
    background: #192c25;
    color: #fff;
  }
  .filter-dropdown i {
    font-size: 0.7rem;
    color: #c9a96e;
  }

  .shop-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
    width: 100%;
  }
  .shop-shell {
    background: #192c25;
    border-radius: 40px;
    padding: 40px 60px 80px;
    margin: 0 20px 60px;
    position: relative;
    box-shadow: 0 20px 40px rgba(25, 44, 37, 0.15);
  }
  .shop-shell::before {
    content: "";
    position: absolute;
    top: 0; left: 40px; right: 40px;
    height: 1px;
    background: transparent;
  }
  
  .shop-layout {
    display: block; 
  }
  .shop-sidebar { display: none; }
  .shop-results { width: 100%; }
  .shop-results-bar { display: none; }
  
  .shop-product-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
  }
  .shop-results-header {
      font-size: 1.4rem;
      color: #ffffff;
      font-family: var(--serif, serif);
      margin-bottom: 0;
      display: flex;
      align-items: center;
      padding-top: 0 !important;
  }
  .shop-results-header::after {
      content: "";
      display: inline-block;
      width: 40px;
      height: 1px;
      background: #c9a96e;
      margin-left: 20px;
  }

  /* PREMIUM PRODUCT CARDS CSS */
  .shop-page .prod-card {
    background: #fdfcf8;
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    position: relative;
    border: 1px solid #eae1d0;
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
  }
  .shop-page .prod-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.05);
  }
  .shop-page .prod-img-box {
    order: 1;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 15px;
    background: transparent; 
    position: relative;
  }
  .shop-page .prod-img-box img {
    mix-blend-mode: multiply;
  }
  .shop-page .prod-cat {
    order: 2;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    color: #8c8577;
    margin-bottom: 8px;
    font-weight: 600;
  }
  .shop-page .prod-name {
    order: 3;
    font-family: var(--serif, serif);
    font-size: 1.3rem;
    color: #1a1a1a;
    margin-bottom: 10px;
    line-height: 1.2;
  }
  .shop-page .prod-name a {
    color: inherit;
    text-decoration: none;
  }
  .shop-page .prod-prices {
    order: 4;
    font-size: 0.9rem;
    color: #1a1a1a;
    margin-bottom: 30px; /* extra margin so button doesn't overlap text */
  }
  .shop-page .prod-prices::before {
    content: "Starting From\\A ";
    white-space: pre;
    font-size: 0.65rem;
    color: #8c8577;
    display: block;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
  }
  .shop-page .prod-prices .new {
    color: #1a1a1a;
    font-size: 1.15rem;
    font-weight: 500;
  }
  /* Hide old decorations */
  .shop-page .prod-footer-decor,
  .shop-page .prod-stars,
  .shop-page .prod-craft-row,
  .shop-page .prod-ornament,
  .shop-page .prod-ornament-close {
    display: none !important;
  }
  
  /* Fix qv-popup to allow clicks through and remove background */
  .shop-page .qv-popup {
    position: absolute;
    inset: 0;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 15px;
    z-index: 10;
    visibility: visible !important;
    opacity: 1 !important;
    display: flex;
    flex-direction: column;
    pointer-events: none; /* Let clicks pass through popup */
    transform: none !important;
  }
  .shop-page .qv-popup-body {
    background: transparent !important;
    padding: 0 !important;
    border: none !important;
    box-shadow: none !important;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .shop-page .qv-popup-img,
  .shop-page .qv-popup-header,
  .shop-page .qv-popup-name,
  .shop-page .qv-stars,
  .shop-page .qv-desc {
    display: none !important;
  }
  .shop-page .qv-actions {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: flex-end;
    height: 100%;
    margin: 0;
    padding: 0;
    border: none !important;
    background: transparent !important;
    pointer-events: none;
  }
  .shop-page .qv-wishlist-form {
    pointer-events: auto;
    align-self: flex-end;
    margin-bottom: auto;
  }
  .shop-page .qv-icon-btn {
    pointer-events: auto;
    background: #fff;
    border: 1px solid rgba(201,169,110,0.5);
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #c9a96e;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
    opacity: 1 !important;
    visibility: visible !important;
    box-shadow: none !important;
  }
  .shop-page .qv-icon-btn:hover {
    background: #c9a96e;
    color: #fff;
  }
  .shop-page .qv-share-btn {
    display: none !important;
  }
  
  /* Select Options button masquerading as View Details */
  .shop-page .qv-add-btn {
    pointer-events: auto;
    align-self: center;
    display: block;
    width: 80%;
    padding: 12px 0;
    margin-top: auto;
    border: 1px solid #c9a96e;
    border-radius: 30px;
    color: transparent !important; /* Hide original text */
    font-size: 0; 
    background: transparent !important;
    transition: all 0.3s;
    text-transform: uppercase;
    text-decoration: none;
    position: relative;
    overflow: hidden;
  }
  .shop-page .qv-add-btn::after {
    content: "VIEW DETAILS   ✦";
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c9a96e;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    font-family: var(--sans, sans-serif);
    transition: all 0.3s;
  }
  .shop-page .qv-add-btn:hover {
    background: #fcf4e6 !important;
  }
  .shop-page .qv-add-btn:hover::after {
    color: #1a1a1a;
  }
  
  /* Filter Pills styling override */
  .shop-page .shop-active-filters span {
      background: transparent !important;
      border: 1px solid rgba(255,255,255,0.2) !important;
      color: #e5dac4 !important;
  }
  .shop-page .shop-active-filters strong {
      color: #fff !important;
  }
  .shop-page .shop-active-filters a {
      color: #c9a96e !important;
  }

  @media (max-width: 1024px) {
    .shop-product-grid { grid-template-columns: repeat(3, 1fr); }
    .style-selector-row { gap: 22px; }
    .shop-shell { padding: 30px 40px 60px; }
    .premium-hero-badge { display: none; }
  }
  @media (max-width: 768px) {
    .shop-product-grid { grid-template-columns: repeat(2, 1fr); }
    .collection-hero.ring-journey-hero { padding: 60px 20px 60px; }
    .collection-hero h1 { font-size: 3rem; }
    .premium-step-banner { min-height: 58px; overflow-x: auto; justify-content: flex-start; padding: 0 18px; width: 100%; border:none; border-bottom:1px solid #eae1d0;}
    .step-banner-item.start-text { display: none; }
    .step-banner-item { padding: 0 18px; font-size: 0.78rem; }
    .shop-shell { padding: 25px 20px 40px; margin: 0 10px 40px; border-radius: 20px; }
    .shop-results-header { font-size: 1.1rem; }
    .collection-filter-bar { flex-direction: column; align-items: flex-start; gap: 15px; }
  }
  @media (max-width: 480px) {
    .shop-product-grid { grid-template-columns: 1fr; }
  }
</style>"""

parts = content.split('<style>', 1)
if len(parts) == 2:
    start_content = parts[0]
    end_content = parts[1].split('</style>', 1)[1]
    content = start_content + new_css + end_content

with open('shop/index.php', 'w') as f:
    f.write(content)
