with open('shop/index.php', 'r') as f:
    content = f.read()

# Replace the max-width and grid columns
content = content.replace('.shop-container {\n    max-width: 1400px;', '.shop-container {\n    max-width: 1600px;')
content = content.replace('grid-template-columns: repeat(4, 1fr);', 'grid-template-columns: repeat(5, 1fr);')

# Replace the broken ::before price hack
old_price_css = """  .shop-page .prod-prices::before {
    content: "Starting From\\A ";
    white-space: pre;
    font-size: 0.65rem;
    color: #8c8577;
    display: block;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
  }"""
new_price_css = """  .shop-page .prod-prices {
    opacity: 1 !important;
    transform: none !important;
  }
  .shop-page .prod-card:hover .prod-prices {
    opacity: 1 !important;
    transform: none !important;
  }
  .shop-page .prod-card:hover .img-default {
    opacity: 0 !important;
  }
  .shop-page .prod-card:hover .img-hover {
    opacity: 1 !important;
  }
  .shop-page .price-prefix {
    font-size: 0.65rem;
    color: #8c8577;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 600;
    margin-right: 5px;
    font-family: var(--sans, sans-serif);
  }"""

content = content.replace(old_price_css, new_price_css)

with open('shop/index.php', 'w') as f:
    f.write(content)

