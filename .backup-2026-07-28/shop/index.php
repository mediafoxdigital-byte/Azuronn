<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$content = site_content();
$products = catalog_expanded_products();
$productTypes = $content['catalog_meta']['product_types'] ?? [];

// Build metal/color filter options dynamically from actual product data
// This ensures expanded metal clones (Gold 9k, Gold 18k, Platinum, etc.) appear as filter options
$productColors = array_values(array_filter(array_unique(array_map(
    static fn (array $p): string => (string) ($p['color'] ?? ''),
    $products
))));
sort($productColors);


$filters = [
    'q' => sanitize_text((string) ($_GET['q'] ?? '')),
    'type' => sanitize_text((string) ($_GET['type'] ?? '')),
    'color' => sanitize_text((string) ($_GET['color'] ?? '')),
    'category' => sanitize_text((string) ($_GET['category'] ?? '')),
    'shape' => sanitize_text((string) ($_GET['shape'] ?? '')),
    'style' => sanitize_text((string) ($_GET['style'] ?? '')),
    'facet' => sanitize_text((string) ($_GET['facet'] ?? '')),
    'sort' => sanitize_text((string) ($_GET['sort'] ?? 'featured')),
];

$categoryTypeAliases = [
    'ring' => 'Ring',
    'rings' => 'Ring',
    'earring' => 'Earring',
    'earrings' => 'Earring',
    'pendant' => 'Pendant',
    'pendants' => 'Pendant',
    'bracelet' => 'Bracelet',
    'bracelets' => 'Bracelet',
    'bangles' => 'Bracelet',
    'bangles & bracelets' => 'Bracelet',
    'necklace' => 'Necklace',
    'necklaces' => 'Necklace',
    'neckless' => 'Necklace',
    'necklesses' => 'Necklace',
];

$collectionMeta = [
    'Ring' => [
        'title' => 'Rings',
        'description' => 'Explore every ring design in one place, from solitaire and halo to vintage, toi et moi, sidestones, and modern signature styles.',
    ],
    'Earring' => [
        'title' => 'Earrings',
        'description' => 'Discover refined everyday studs, drops, and statement earring silhouettes curated for gifting and every occasion wear.',
    ],
    'Pendant' => [
        'title' => 'Pendants',
        'description' => 'Browse pendant styles designed for layering, gifting, and effortless daily elegance.',
    ],
    'Bracelet' => [
        'title' => 'Bangles & Bracelets',
        'description' => 'Explore bracelet and bangle designs with clean lines, polished finishes, and timeless styling.',
    ],
    'Necklace' => [
        'title' => 'Necklaces',
        'description' => 'View necklace styles crafted to add statement, sparkle, and soft layering across every occasion.',
    ],
    'Mangalsutra' => [
        'title' => 'Mangalsutra',
        'description' => 'Explore mangalsutra-inspired designs with a refined jewellery presentation and a dedicated collection landing experience.',
    ],
];

$namedQueryCollections = [];

$collectionTypeGroups = [
    'Ring'     => ['Ring', 'Rings'],
    'Rings'    => ['Ring', 'Rings'],
    'Earring'  => ['Earring', 'Earrings'],
    'Earrings' => ['Earring', 'Earrings'],
    'Bracelet'         => ['Bracelet', 'Bangles & Bracelets'],
    'Bangles & Bracelets' => ['Bracelet', 'Bangles & Bracelets'],
    'Pendant'  => ['Pendant', 'Pendants', 'Jewellery Set', 'Brooch'],
    'Pendants' => ['Pendant', 'Pendants', 'Jewellery Set', 'Brooch'],
    'Necklace' => ['Necklace', 'Necklaces'],
    'Necklaces'=> ['Necklace', 'Necklaces'],
];

$normalizedQuery = strtolower($filters['q']);
if ($filters['type'] === '' && isset($categoryTypeAliases[$normalizedQuery])) {
    $filters['type'] = $categoryTypeAliases[$normalizedQuery];
    $filters['q'] = '';
}

$normalizedType = strtolower($filters['type']);
if ($normalizedType !== '' && isset($categoryTypeAliases[$normalizedType])) {
    $filters['type'] = $categoryTypeAliases[$normalizedType];
}

$ringStyles = available_ring_styles();
$ringStyleCards = available_ring_style_cards();
$allowedTypes = $filters['type'] !== '' ? ($collectionTypeGroups[$filters['type']] ?? [$filters['type']]) : [];
if ($filters['type'] === 'Ring') {
    $normalizedStyle = strtolower($filters['style']);
    foreach ($ringStyles as $styleKey => $styleLabel) {
        if ($normalizedStyle === $styleKey || $normalizedStyle === strtolower($styleLabel)) {
            $filters['style'] = $styleKey;
            break;
        }
    }

    if ($filters['style'] === '' && $filters['q'] !== '') {
        foreach ($ringStyles as $styleKey => $styleLabel) {
            if ($normalizedQuery === $styleKey || $normalizedQuery === strtolower($styleLabel)) {
                $filters['style'] = $styleKey;
                $filters['q'] = '';
                break;
            }
        }
    }
}

$catalogFilters = $filters;
$catalogFilters['type'] = '';
$catalogFilters['facet'] = '';
$catalogFilters['style'] = '';
$filteredProducts = filter_catalog_products($products, $catalogFilters);
if ($allowedTypes !== []) {
    $allowedTypesLower = array_map('strtolower', $allowedTypes);
    $filteredProducts = array_values(array_filter($filteredProducts, static function (array $product) use ($allowedTypesLower): bool {
        return in_array(strtolower((string) ($product['product_type'] ?? '')), $allowedTypesLower, true);
    }));
}
$showRingJourney = $filters['type'] === 'Ring';
$isSearch = $filters['q'] !== '';
$showPremiumHero = true;
$premiumHeroCategory = $filters['type'] !== '' ? $filters['type'] : ($isSearch ? 'Search' : 'Shop');

$premiumHeroBgs = [
    'Ring' => '/assets/uploads/ring_collection_bg.png',
    'Earring' => '/assets/uploads/earring_collection_bg.png',
    'Pendant' => '/assets/uploads/pendant_collection_bg.png',
    'Bracelet' => '/assets/uploads/bracelet_collection_bg.png',
    'Necklace' => '/assets/uploads/necklace_collection_bg.png',
    'Mangalsutra' => '/assets/uploads/mangalsutra_collection_bg.png',
    'Search' => '/assets/uploads/shop_collection_bg.png',
    'Shop' => '/assets/uploads/shop_collection_bg.png',
];
$premiumBgUrl = $premiumHeroBgs[$premiumHeroCategory] ?? $premiumHeroBgs['Shop'];

if ($showRingJourney && $filters['style'] !== '') {
    $filteredProducts = array_values(array_filter($filteredProducts, static function (array $product) use ($filters): bool {
        return in_array($filters['style'], (array) ($product['styles'] ?? []), true);
    }));
}

$selectorItems = !$showRingJourney && $filters['type'] !== '' ? available_collection_selector_cards($filters['type']) : [];
if (!$showRingJourney && $filters['facet'] !== '' && isset($selectorItems[$filters['facet']])) {
    $facetProductIds = $selectorItems[$filters['facet']]['product_ids'] ?? [];
    $facetNeedle = strtolower($filters['facet']);
    $facetLabel = strtolower((string) ($selectorItems[$filters['facet']]['label'] ?? ''));
    $filteredProducts = array_values(array_filter($filteredProducts, static function (array $product) use ($facetProductIds, $facetNeedle, $facetLabel): bool {
        if (in_array((string) ($product['id'] ?? ''), $facetProductIds, true)) {
            return true;
        }

        $productTags = array_map(static function (mixed $tag): string {
            return strtolower(trim((string) $tag));
        }, (array) ($product['subcategories'] ?? []));

        return in_array($facetNeedle, $productTags, true) || in_array($facetLabel, $productTags, true);
    }));
}

$premiumSelectorPreviewItems = $showPremiumHero ? array_slice(array_values($selectorItems), 0, 13) : [];

$heroTitle = 'Shop Jewellery';
$heroDescription = 'Browse the current collection and refine by type, metal colour, style, and shape.';
$breadcrumbLabel = 'Shop';

if ($showRingJourney) {
    $heroTitle = $filters['style'] !== ''
        ? (($ringStyles[$filters['style']] ?? 'Ring Style') . ' Rings')
        : ($collectionMeta['Ring']['title'] ?? 'Rings');
    $heroDescription = $filters['style'] !== ''
        ? 'Explore ' . strtolower($ringStyles[$filters['style']] ?? 'ring') . ' ring designs and compare silhouettes across the full ring collection.'
        : ($collectionMeta['Ring']['description'] ?? $heroDescription);
    $breadcrumbLabel = $heroTitle;
} elseif (isset($collectionMeta[$filters['type']])) {
    $heroTitle = $collectionMeta[$filters['type']]['title'];
    $heroDescription = $collectionMeta[$filters['type']]['description'];
    $breadcrumbLabel = $heroTitle;
} elseif ($filters['q'] !== '' && isset($namedQueryCollections[$normalizedQuery])) {
    $heroTitle = $namedQueryCollections[$normalizedQuery]['title'];
    $heroDescription = $namedQueryCollections[$normalizedQuery]['description'];
    $breadcrumbLabel = $heroTitle;
} elseif ($filters['q'] !== '') {
    $heroTitle = 'Search Results for "' . $filters['q'] . '"';
    $heroDescription = 'Explore the pieces matching your search and refine the results with the available filters.';
    $breadcrumbLabel = 'Search';
}

$pageTitle = $heroTitle . ' - ' . SITE_NAME;
$bodyClass = 'shop-page';

$buildShopUrl = static function (array $changes = []) use ($filters): string {
    $query = array_merge($filters, $changes);
    $query = array_filter($query, static fn (mixed $value): bool => is_string($value) && $value !== '');
    return empty($query) ? resolve_link('/shop/') : resolve_link('/shop/?' . http_build_query($query));
};

$activeFilterPills = array_filter([
    $filters['type'] !== '' ? ['label' => 'Type', 'value' => $filters['type'], 'clear' => $buildShopUrl(['type' => '', 'style' => ''])] : null,
    $filters['color'] !== '' ? ['label' => 'Metal', 'value' => $filters['color'], 'clear' => $buildShopUrl(['color' => ''])] : null,
    $filters['category'] !== '' ? ['label' => 'Collection', 'value' => $filters['category'], 'clear' => $buildShopUrl(['category' => ''])] : null,
    $filters['shape'] !== '' ? ['label' => 'Shape', 'value' => $filters['shape'], 'clear' => $buildShopUrl(['shape' => ''])] : null,
    $filters['style'] !== '' ? ['label' => 'Style', 'value' => $ringStyles[$filters['style']] ?? $filters['style'], 'clear' => $buildShopUrl(['style' => ''])] : null,
    $filters['facet'] !== '' && isset($selectorItems[$filters['facet']]) ? ['label' => 'Edit', 'value' => $selectorItems[$filters['facet']]['label'], 'clear' => $buildShopUrl(['facet' => ''])] : null,
    $filters['q'] !== '' ? ['label' => 'Search', 'value' => $filters['q'], 'clear' => $buildShopUrl(['q' => ''])] : null,
]);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
  body.shop-page {
    background: #fdfaf5;
  }

  .hero-breadcrumbs {
    display: inline-flex;
    align-items: center;
    margin-bottom: 20px;
    color: #c18b35;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
  }
  .hero-breadcrumbs a {
    color: inherit;
    text-decoration: none;
    transition: color 0.2s;
  }
  .hero-breadcrumbs a:hover {
    color: #a4762c;
  }
  .hero-breadcrumbs .sep {
    margin: 0 12px;
    opacity: 0.6;
  }
  .hero-breadcrumbs strong {
    font-weight: 600;
  }

  .collection-hero {
    background: transparent;
    padding: 54px 20px 40px;
    text-align: center;
  }
  .collection-hero.ring-journey-hero {
    padding: 90px 20px 220px;
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
  .collection-hero.earring-collection-hero {
    padding: 60px 20px 40px;
    background-position: center right;
    background-repeat: no-repeat;
    background-size: cover;
    text-align: left;
    position: relative;
    overflow: hidden;
  }
  .collection-hero.earring-collection-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(253, 250, 245, 0.95) 0%, rgba(253, 250, 245, 0.8) 35%, rgba(253, 250, 245, 0) 100%);
    pointer-events: none;
  }
  .collection-hero.earring-collection-hero .container {
    position: relative;
    z-index: 1;
    max-width: 1460px;
  }
  .earring-hero-shell {
    position: relative;
    padding: 20px 10px 40px;
  }
  .earring-hero-grid {
    display: flex;
    align-items: center;
    min-height: 400px;
  }
  .earring-hero-copy {
    max-width: 500px;
    padding: 20px 0 20px 5%;
  }
  .earring-hero-kicker {
    display: inline-block;
    margin-bottom: 20px;
    color: #c18b35;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 0.25em;
    text-transform: uppercase;
  }
  .earring-hero-copy h1 {
    margin: 0;
    color: #143b32;
    font-family: "Playfair Display", serif;
    font-size: clamp(4.5rem, 7vw, 6.5rem);
    font-weight: 400;
    line-height: 1;
    letter-spacing: -0.02em;
  }
  .earring-hero-ornament {
    display: flex;
    align-items: center;
    gap: 15px;
    margin: 25px 0 25px;
    color: #c18b35;
  }
  .earring-hero-ornament::before,
  .earring-hero-ornament::after {
    content: "";
    width: 90px;
    height: 1px;
    background: #c18b35;
  }
  .earring-hero-ornament i {
    font-size: 0.85rem;
  }
  .earring-hero-copy p {
    max-width: 440px;
    margin: 0;
    color: #5a5a5a;
    font-size: 1.05rem;
    line-height: 1.6;
    font-weight: 400;
  }
  .style-selector-row.earring-selector-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    width: 100%;
    max-width: none;
    margin: 30px auto 0;
    gap: 24px 20px;
    padding: 0;
  }
  .style-selector-item.earring-selector-item {
    width: 96px;
    color: #262626;
    flex-shrink: 0;
  }
  .style-selector-item.earring-selector-item img {
    width: 82px;
    height: 82px;
    padding: 12px;
    border-radius: 50%;
    background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(252,248,241,0.94) 100%);
    box-shadow:
      0 18px 34px rgba(173, 151, 123, 0.11),
      inset 0 0 0 1px rgba(227, 217, 201, 0.9);
  }
  .style-selector-item.earring-selector-item span {
    margin-top: 10px;
    color: #2d2d2d;
    font-size: 0.70rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    line-height: 1.35;
  }
  .earring-selector-item:hover img,
  .earring-selector-item.is-active img {
    border-color: transparent;
    box-shadow:
      0 20px 38px rgba(193, 139, 53, 0.16),
      inset 0 0 0 2px rgba(193, 139, 53, 0.48);
  }
  .earring-selector-item:hover,
  .earring-selector-item.is-active {
    color: #143b32;
    transform: translateY(-4px);
  }
  .ring-hero-heading {
    display: inline-flex;
    align-items: flex-start;
    gap: 24px;
    margin-bottom: 20px;
    max-width: 100%;
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
  .collection-hero.ring-journey-hero h1 {
    margin-bottom: 0;
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
    position: relative;
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
    margin-top: -30px;
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
    content: "\2726";
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
    max-width: 1600px;
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
    grid-template-columns: repeat(5, 1fr);
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
    width: 100%;
    margin: 8px 0 0;
    padding: 13px 18px 14px;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(249,246,239,0.98) 0%, rgba(255,253,248,0.98) 60%, rgba(245,240,228,0.98) 100%);
    border: 1px solid rgba(201, 169, 110, 0.2);
    box-shadow: 0 8px 18px rgba(18, 43, 35, 0.06), inset 0 1px 0 rgba(255,255,255,0.9);
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
  }
  .shop-page .prod-prices .new {
    font-size: 1.05rem;
  }
  .shop-page .prod-footer-decor {
    order: 5;
    display: flex !important;
    margin-top: 16px;
  }
  .shop-page .prod-stars,
  .shop-page .prod-craft-row,
  .shop-page .prod-ornament {
    display: flex !important;
  }
  .shop-page .prod-ornament-close {
    display: none !important;
  }
  
  .shop-page .qv-popup {
    left: 14px;
    right: 14px;
    bottom: 14px;
    top: calc(var(--prod-card-pad) + var(--prod-media-height) + 10px);
    transform: translateY(16px);
  }
  .shop-page .prod-card:hover .qv-popup {
    transform: translateY(0);
  }
  .shop-page .qv-popup-body {
    min-height: 255px;
    padding: 16px 16px 14px;
    border-radius: 28px;
    background: linear-gradient(180deg, rgba(255,255,252,0.98) 0%, rgba(251,247,239,0.99) 100%);
    box-shadow: 0 18px 40px rgba(18, 43, 35, 0.14), 0 8px 22px rgba(201, 169, 110, 0.12);
  }
  .shop-page .qv-popup-name {
    font-size: 1.05rem;
    line-height: 1.18;
    color: #243a32;
  }
  .shop-page .qv-desc {
    max-width: 250px;
    color: #6d756d;
  }
  .shop-page .qv-actions {
    gap: 10px;
    padding-top: 12px;
    margin-top: 12px;
  }
  .shop-page .qv-wishlist-form {
    margin: 0;
  }
  .shop-page .qv-icon-btn {
    width: 40px;
    height: 40px;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #f7f3ea 100%);
    border: 1px solid rgba(18, 43, 35, 0.1);
    color: #5d6a64;
    box-shadow: 0 4px 12px rgba(18, 43, 35, 0.07);
  }
  .shop-page .qv-icon-btn:hover,
  .shop-page .qv-icon-btn.is-active {
    border-color: rgba(200, 157, 88, 0.44);
    background: linear-gradient(135deg, #fffaf1 0%, #ead4ab 46%, #c89d58 100%);
    color: #17372c;
    box-shadow: 0 8px 18px rgba(132, 96, 44, 0.22);
  }
  .shop-page .qv-add-btn {
    min-height: 40px;
    border-radius: 14px;
    background: linear-gradient(135deg, #17372c 0%, #284d3f 60%, #9a7445 140%);
    color: #fff !important;
    font-size: 0.7rem !important;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    box-shadow: 0 8px 20px rgba(18, 43, 35, 0.2);
  }
  .shop-page .qv-add-btn:hover {
    filter: brightness(1.08);
    box-shadow: 0 14px 28px rgba(18, 43, 35, 0.28);
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
    .premium-hero-badge { width: 124px; height: 124px; }
  @media (max-width: 1024px) {
    .shop-product-grid { grid-template-columns: repeat(3, 1fr); }
    .style-selector-row { gap: 22px; }
    .shop-shell { padding: 30px 40px 60px; }
    .premium-hero-badge { width: 124px; height: 124px; }
    .earring-hero-grid { min-height: 350px; }
    .earring-hero-copy { max-width: none; padding: 20px 10px 0; }
  }
  @media (max-width: 768px) {
    .shop-product-grid { grid-template-columns: repeat(2, 1fr); }
    .collection-hero.ring-journey-hero { padding: 60px 20px 100px; }
    .collection-hero h1 { font-size: 3rem; }
    .ring-hero-heading { display: block; }
    .premium-hero-badge { display: none; }
    .premium-step-banner { min-height: 58px; overflow-x: auto; justify-content: flex-start; padding: 0 18px; width: 100%; border:none; border-bottom:1px solid #eae1d0;}
    .step-banner-item.start-text { display: none; }
    .step-banner-item { padding: 0 18px; font-size: 0.78rem; }
    .shop-shell { padding: 25px 20px 40px; margin: 0 10px 40px; border-radius: 20px; }
    .shop-results-header { font-size: 1.1rem; }
    .collection-filter-bar { flex-direction: column; align-items: flex-start; gap: 15px; }
    .collection-hero.earring-collection-hero { padding: 40px 12px 20px; background-position: 70% center; }
    .earring-hero-shell { padding: 10px 0 20px; }
    .earring-hero-copy h1 { font-size: clamp(3.5rem, 15vw, 5.2rem); }
    .earring-hero-kicker { font-size: 0.74rem; letter-spacing: 0.22em; }
    .earring-hero-copy p { max-width: 100%; font-size: 0.98rem; }
    .earring-hero-ornament::before { width: 72px; }
    .style-selector-row.earring-selector-row { gap: 12px; margin-top: 12px; }
    .style-selector-item.earring-selector-item { width: 82px; }
    .style-selector-item.earring-selector-item img { width: 68px; height: 68px; padding: 10px; }
    .style-selector-item.earring-selector-item span { font-size: 0.62rem; letter-spacing: 0.1em; }
  }
  @media (max-width: 480px) {
    .shop-product-grid { grid-template-columns: 1fr; }
    .earring-hero-copy { padding: 10px 6px 0; }
    .style-selector-item.earring-selector-item { width: 74px; }
  }
</style>

<?php ob_start(); ?>
<div class="hero-breadcrumbs">
  <a href="<?= h(resolve_link('/')) ?>"><i class="fas fa-home" style="margin-right:6px;"></i> HOME</a>
  <?php if ($showRingJourney): ?>
    <span class="sep">/</span>
    <?php if ($filters['style'] !== ''): ?>
      <a href="<?= h($buildShopUrl(['type' => 'Ring', 'style' => '', 'q' => '', 'category' => '', 'shape' => ''])) ?>">RINGS</a>
      <span class="sep">/</span>
      <strong><?= h($breadcrumbLabel) ?></strong>
    <?php else: ?>
      <strong>RINGS</strong>
    <?php endif; ?>
  <?php else: ?>
    <span class="sep">/</span>
    <strong><?= h($breadcrumbLabel) ?></strong>
  <?php endif; ?>
</div>
<?php $heroBreadcrumbsHtml = ob_get_clean(); ?>

<section class="collection-hero reveal-in <?= $showPremiumHero ? 'earring-collection-hero' : '' ?>"<?= $showPremiumHero ? ' style="background-image: url(\'' . h($premiumBgUrl) . '\');"' : ' style="padding-bottom: 0;"' ?>>
  <div class="container">
    <?php if ($showPremiumHero): ?>
    <div class="earring-hero-shell">
      <div class="earring-hero-grid">
        <div class="earring-hero-copy">
          <?= $heroBreadcrumbsHtml ?>
          <h1><?= h($heroTitle) ?></h1>
          <div class="earring-hero-ornament"><i class="far fa-gem" aria-hidden="true"></i></div>
          <p><?= h($heroDescription) ?></p>
        </div>
      </div>

      <?php if ($premiumSelectorPreviewItems !== []): ?>
        <div class="style-selector-row earring-selector-row">
          <?php foreach ($premiumSelectorPreviewItems as $item): ?>
            <?php
              $itemValue = (string) ($item['value'] ?? '');
              $isActive = $filters['facet'] === $itemValue;
              $link = $buildShopUrl([
                  'type' => $premiumHeroCategory,
                  'facet' => $itemValue,
                  'q' => '',
                  'category' => '',
                  'shape' => '',
                  'style' => '',
              ]);
            ?>
            <a href="<?= h($link) ?>" class="style-selector-item earring-selector-item <?= $isActive ? 'is-active' : '' ?>" data-style-selector-link>
              <img src="<?= h((string) ($item['image'] ?? '')) ?>" alt="<?= h((string) ($item['label'] ?? 'Style')) ?>">
              <span><?= h((string) ($item['label'] ?? '')) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <?= $heroBreadcrumbsHtml ?>
    <h1><?= h($heroTitle) ?></h1>
    <?php endif; ?>
    <?php if (!$showPremiumHero): ?>
      <p><?= h($heroDescription) ?></p>
    <?php endif; ?>
  </div>
</section>

<?php if ($showRingJourney): ?>
  <section class="ring-journey-overview">
  <div class="premium-step-banner">
    <div class="step-banner-item start-text">Start With A Ring Design</div>
    <div class="step-separator"></div>
    <div class="step-banner-item is-active">
      <span>1</span> Select Ring Design
    </div>
    <div class="step-separator"></div>
    <div class="step-banner-item">
      <span>2</span> Select Diamond
    </div>
    <div class="step-separator"></div>
    <div class="step-banner-item">
      <span>3</span> Complete Ring
    </div>
  </div>

  <div class="container ring-style-showcase">
    <div class="style-selector-row ring-style-selector-row">
      <?php
      foreach ($ringStyles as $itemKey => $itemName):
          $isActive = $filters['style'] === $itemKey;
          $img = $ringStyleCards[$itemKey]['image'] ?? (default_ring_style_cards()[$itemKey]['image'] ?? '');
          $displayName = $ringStyleCards[$itemKey]['label'] ?? $itemName;
      ?>
        <form method="get" action="<?= h(resolve_link('/shop/')) ?>" class="style-selector-form">
          <input type="hidden" name="type" value="Ring">
          <?php if (!$isActive): ?><input type="hidden" name="style" value="<?= h($itemKey) ?>"><?php endif; ?>
          <?php if ($filters['color'] !== ''): ?><input type="hidden" name="color" value="<?= h($filters['color']) ?>"><?php endif; ?>
          <?php if ($filters['sort'] !== ''): ?><input type="hidden" name="sort" value="<?= h($filters['sort']) ?>"><?php endif; ?>
          <button type="submit" class="style-selector-item <?= $isActive ? 'is-active' : '' ?>" title="<?= h($isActive ? 'Show all ring styles' : 'Show ' . $displayName . ' rings') ?>">
            <img src="<?= h($img) ?>" alt="<?= h($displayName) ?>">
            <span><?= h($displayName) ?></span>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>
  </section>
<?php elseif ($selectorItems !== [] && !$showPremiumHero): ?>
  <div class="container">
    <div class="style-selector-row">
      <?php foreach ($selectorItems as $itemKey => $item): ?>
        <?php
          $isActive = $filters['facet'] === $itemKey;
          $link = $buildShopUrl([
              'type' => $filters['type'],
              'facet' => $itemKey,
              'q' => '',
              'category' => '',
              'shape' => '',
              'style' => '',
          ]);
        ?>
        <a href="<?= h($link) ?>" class="style-selector-item <?= $isActive ? 'is-active' : '' ?>" data-style-selector-link>
          <img src="<?= h((string) $item['image']) ?>" alt="<?= h((string) $item['label']) ?>">
          <span><?= h((string) $item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<section class="shop-shell">
  <div class="shop-container shop-layout">
    
    <div class="shop-results">
        
      <form method="get" action="<?= h(resolve_link('/shop/')) ?>" class="collection-filter-bar">
        <!-- Preserve hidden filters if any -->
        <?php foreach ($filters as $k => $v): if ($k !== 'sort' && $k !== 'color' && $v !== ''): ?>
          <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
        <?php endif; endforeach; ?>
        
        <div class="shop-results-header" style="margin: 0; padding-top: 5px;">
            <?= count($filteredProducts) ?> RESULTS
        </div>

        <div class="filter-group">
            <span class="filter-label">Filter By:</span>
            
            <div style="position: relative; display: inline-block;">
                <select name="color" class="filter-dropdown" onchange="this.form.submit()" style="appearance: none; -webkit-appearance: none; padding-right: 20px;">
                    <option value="">Metal</option>
                    <?php foreach ($productColors as $color): ?>
                        <option value="<?= h($color) ?>" <?= $filters['color'] === $color ? 'selected' : '' ?>><?= h($color) ?></option>
                    <?php endforeach; ?>
                </select>
                <i class="fas fa-caret-down" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%); pointer-events: none;"></i>
            </div>
            
            <div style="width: 1px; height: 20px; background: #eaeaea; margin: 0 10px;"></div>

            <span class="filter-label" style="margin-left: 10px;">Sort By:</span>
            
            <div style="position: relative; display: inline-block;">
                <select name="sort" class="filter-dropdown" onchange="this.form.submit()" style="appearance: none; -webkit-appearance: none; padding-right: 20px;">
                    <option value="featured" <?= $filters['sort'] === 'featured' ? 'selected' : '' ?>>Featured</option>
                    <option value="price-low" <?= $filters['sort'] === 'price-low' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price-high" <?= $filters['sort'] === 'price-high' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="name-asc" <?= $filters['sort'] === 'name-asc' ? 'selected' : '' ?>>Name: A - Z</option>
                    <option value="name-desc" <?= $filters['sort'] === 'name-desc' ? 'selected' : '' ?>>Name: Z - A</option>
                </select>
                <i class="fas fa-caret-down" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%); pointer-events: none;"></i>
            </div>
        </div>
      </form>

      <?php if ($activeFilterPills !== []): ?>
        <div class="shop-active-filters" style="margin-bottom: 25px; display: flex; gap: 10px; flex-wrap: wrap;">
          <?php foreach ($activeFilterPills as $pill): ?>
              <span style="background: #f0f4f2; border: 1px solid #e0e8e4; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; color: #2b3330; display: inline-flex; align-items: center; gap: 8px;">
                <strong style="text-transform: uppercase; font-size: 0.7rem; color: #114531;"><?= h($pill['label']) ?>:</strong> <?= h($pill['value']) ?>
                <a href="<?= h($pill['clear']) ?>" style="color: #8da197; text-decoration: none;"><i class="fas fa-times"></i></a>
              </span>
          <?php endforeach; ?>
          <a href="<?= h($buildShopUrl(['q' => '', 'type' => '', 'color' => '', 'category' => '', 'shape' => '', 'style' => '', 'facet' => '', 'sort' => 'featured'])) ?>" style="font-size: 0.8rem; color: #c9a96e; text-decoration: underline; margin-left: 10px; align-self: center;">Clear All</a>
        </div>
      <?php endif; ?>

      <?php if ($filteredProducts === []): ?>
        <div class="shop-empty-state" style="background: #fbfcfb; border: 1px solid #edf0ed; border-radius: 12px; padding: 60px 30px; text-align: center; margin: 40px 0;">
          <h3 style="font-family: var(--serif); font-size: 2.2rem; color: #2b3330; margin-bottom: 15px; font-weight: 600;">No products matched these filters</h3>
          <p style="color: #6a7c73; font-size: 1.05rem; max-width: 500px; margin: 0 auto 30px; line-height: 1.6;">Clear the current filters or adjust the search terms to see more products.</p>
          <a class="btn-shop" href="<?= h($buildShopUrl(['q' => '', 'type' => '', 'color' => '', 'category' => '', 'shape' => '', 'style' => '', 'facet' => '', 'sort' => 'featured'])) ?>" style="background: #b18861; color: #fff; border: none; font-size: 0.8rem; letter-spacing: 0.15em; padding: 12px 30px; border-radius: 4px; text-transform: uppercase; font-weight: 600;">View All Products</a>
        </div>
      <?php else: ?>
        <div class="shop-product-grid">
          <?php foreach ($filteredProducts as $product): ?>
            <?php render_product_card($product); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
