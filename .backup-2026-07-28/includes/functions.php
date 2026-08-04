<?php
/**
 * functions.php
 * Shared utility helpers for safe output, CSRF tokens, content rendering, etc.
 */
declare(strict_types=1);

function e(string $value): void
{
    echo htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_root_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $directory = ($directory === '.' || $directory === '/') ? '' : $directory;

    $scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $basePath = defined('BASE_PATH') ? str_replace('\\', '/', BASE_PATH) : '';

    if ($scriptFile !== '' && $basePath !== '' && str_starts_with($scriptFile, $basePath)) {
        $relativeDir = trim(substr(str_replace('\\', '/', dirname($scriptFile)), strlen($basePath)), '/');
        if ($relativeDir !== '') {
            $suffix = '/' . $relativeDir;
            if ($directory !== '' && str_ends_with($directory, $suffix)) {
                $directory = substr($directory, 0, -strlen($suffix));
            } else {
                $segments = explode('/', trim($directory, '/'));
                $relativeSegments = explode('/', $relativeDir);
                while ($relativeSegments !== [] && $segments !== [] && end($segments) === end($relativeSegments)) {
                    array_pop($segments);
                    array_pop($relativeSegments);
                }
                $directory = $segments === [] ? '' : '/' . implode('/', $segments);
            }
        }
    }

    return $directory === '/' ? '' : $directory;
}

function app_path_url(string $path): string
{
    $root = app_root_path();
    $cleanPath = ltrim($path, '/');

    if ($cleanPath === '') {
        return $root === '' ? '/' : $root . '/';
    }

    return ($root === '' ? '' : $root) . '/' . $cleanPath;
}

function url(string $path): string
{
    return app_path_url($path);
}

function asset_url(string $path): string
{
    $url = app_path_url($path);
    $cleanPath = ltrim($path, '/');
    $absolutePath = defined('BASE_PATH') ? BASE_PATH . '/' . $cleanPath : '';

    if ($absolutePath !== '' && is_file($absolutePath)) {
        $version = (string) filemtime($absolutePath);
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'v=' . rawurlencode($version);
    }

    return $url;
}

function media_asset_type(string $path): string
{
    $cleanPath = strtolower(parse_url(trim($path), PHP_URL_PATH) ?? trim($path));
    $extension = pathinfo($cleanPath, PATHINFO_EXTENSION);

    return in_array($extension, ['mp4', 'webm', 'ogv', 'mov', 'm4v'], true) ? 'video' : 'image';
}

function media_asset_mime(string $path): string
{
    $cleanPath = strtolower(parse_url(trim($path), PHP_URL_PATH) ?? trim($path));
    $extension = pathinfo($cleanPath, PATHINFO_EXTENSION);

    return match ($extension) {
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        'mov' => 'video/quicktime',
        'm4v' => 'video/x-m4v',
        default => 'video/mp4',
    };
}

function store_media_markup(string $path, string $alt, string $className = '', bool $controls = false): string
{
    $resolvedPath = clean_image($path);
    if ($resolvedPath === '') {
        return '';
    }

    $classAttr = $className !== '' ? ' class="' . h($className) . '"' : '';
    if (media_asset_type($resolvedPath) === 'video') {
        $videoAttrs = $controls ? ' controls' : ' muted autoplay loop aria-hidden="true"';
        return '<video' . $classAttr . $videoAttrs . ' playsinline preload="metadata"><source src="' . h($resolvedPath) . '" type="' . h(media_asset_mime($resolvedPath)) . '"></video>';
    }

    return '<img' . $classAttr . ' src="' . h($resolvedPath) . '" alt="' . h($alt) . '">';
}

function resolve_link(string $path): string
{
    if ($path === '' || $path === '#') {
        return '#';
    }

    if (preg_match('~^(?:https?:)?//~i', $path) || preg_match('~^(mailto:|tel:)~i', $path)) {
        return $path;
    }

    return app_path_url($path);
}

function news_items(): array
{
    return array_values(array_filter(site_content()['news']['items'] ?? [], 'is_array'));
}

function news_article_url(array $story): string
{
    $id = clean_string((string) ($story['id'] ?? ''), 80);
    if ($id === '') {
        $id = content_id('news', $story, 0, 'title');
    }

    return resolve_link('/news/?article=' . rawurlencode($id));
}

function find_news_article(string $articleId): ?array
{
    $articleId = clean_string($articleId, 80);
    if ($articleId === '') {
        return null;
    }

    foreach (news_items() as $story) {
        if ((string) ($story['id'] ?? '') === $articleId) {
            return $story;
        }
    }

    return null;
}

function news_article_body(array $story): string
{
    $body = trim((string) ($story['body'] ?? ''));
    if ($body !== '') {
        return $body;
    }

    $excerpt = clean_string((string) ($story['excerpt'] ?? ''), 12000);
    return $excerpt !== '' ? clean_rich_text($excerpt, 12000) : '';
}

function news_article_text(array $story): string
{
    return rich_text_plain_text(news_article_body($story));
}

function news_article_read_time(array $story): int
{
    $body = trim((string) ($story['title'] ?? '') . ' ' . news_article_text($story));
    if ($body === '') {
        return 2;
    }

    $wordCount = str_word_count(strip_tags($body));
    return max(2, (int) ceil($wordCount / 180));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return isset($_SESSION['csrf_token'])
        && is_string($submitted)
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

function sanitize_text(string $input): string
{
    return trim(strip_tags($input));
}

function sanitize_email(string $email): string
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function catalog_product_map(): array
{
    $items = site_content()['products']['items'] ?? [];
    $map = [];
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['id'])) {
            continue;
        }
        $map[(string) $item['id']] = $item;
    }
    return $map;
}

function catalog_expand_array(array $products): array
{
    $expanded = [];
    foreach ($products as $p) {
        $hasVariations = false;
        if (!empty($p['metal_variations'])) {
            foreach ($p['metal_variations'] as $mv) {
                if ($mv['active'] ?? false) {
                    $hasVariations = true;
                    break;
                }
            }
        }

        if ($hasVariations) {
            $hasActiveMetal = false;
            foreach ($p['metal_variations'] as $idx => $mv) {
                if ($mv['active'] ?? false) {
                    $hasActiveMetal = true;
                    $clone = $p;
                    $clone['original_id'] = $p['id'];
                    $clone['id'] = $p['id'] . '__metal_' . content_slug($mv['metal'], 'm'.$idx);
                    $clone['name'] = $p['name'] . ' - ' . $mv['metal'];
                    $clone['new_price'] = money_format((float)($mv['price'] ?? 0));
                    $clone['old_price'] = $mv['old_price'] ?? '';
                    if (!empty($mv['description'])) {
                        $clone['description'] = $mv['description'];
                    }
                    if (!empty($mv['features']) && is_array($mv['features'])) {
                        $clone['features'] = $mv['features'];
                    }
                    if (!empty($mv['gallery']) && is_array($mv['gallery'])) {
                        $clone['default_image'] = $mv['gallery'][0];
                        $clone['popup_image'] = $mv['gallery'][0];
                        $clone['gallery'] = $mv['gallery'];
                        if (isset($mv['gallery'][1]) && $mv['gallery'][1] !== '') {
                            $clone['hover_image'] = $mv['gallery'][1];
                        }
                    } elseif (!empty($mv['image'])) {
                        $clone['default_image'] = $mv['image'];
                        $clone['popup_image'] = $mv['image'];
                        $clone['gallery'] = [$mv['image']];
                    } else {
                        // Fallback to base product images if metal has no specific images
                        $clone['default_image'] = $p['default_image'] ?? '';
                        $clone['popup_image'] = $p['popup_image'] ?? ($p['default_image'] ?? '');
                        $clone['hover_image'] = $p['hover_image'] ?? ($p['default_image'] ?? '');
                        $clone['gallery'] = $p['gallery'] ?? [];
                    }

                    $clone['url_metal_param'] = content_slug($mv['metal'], 'metal');
                    $clone['color'] = $mv['metal'];
                    $expanded[] = $clone;
                }
            }
            if (!$hasActiveMetal) {
                $expanded[] = $p;
            }
        } else {
            $expanded[] = $p;
        }
    }
    return $expanded;
}

function products_by_ids(array $ids): array
{
    $map = catalog_product_map();
    $products = [];
    foreach ($ids as $id) {
        $key = (string) $id;
        if (isset($map[$key])) {
            $products[] = $map[$key];
        }
    }
    return catalog_sort_by_inventory(catalog_expand_array($products));
}

function catalog_sort_by_inventory(array $products): array
{
    $decorated = [];
    foreach (array_values($products) as $index => $product) {
        if (!is_array($product)) {
            continue;
        }

        $status = function_exists('product_inventory_status')
            ? product_inventory_status($product, ['metal' => (string) ($product['color'] ?? '')])
            : ['out_of_stock' => false];

        $decorated[] = [
            'index' => $index,
            'priority' => !empty($status['out_of_stock']) ? 1 : 0,
            'product' => $product,
        ];
    }

    usort($decorated, static function (array $left, array $right): int {
        if ($left['priority'] !== $right['priority']) {
            return $left['priority'] <=> $right['priority'];
        }

        return $left['index'] <=> $right['index'];
    });

    return array_values(array_map(static fn (array $item): array => $item['product'], $decorated));
}

function catalog_products(bool $activeOnly = true): array
{
    $items = site_content()['products']['items'] ?? [];
    if (!$activeOnly) {
        return catalog_sort_by_inventory(array_values(array_filter($items, 'is_array')));
    }

    return catalog_sort_by_inventory(array_values(array_filter($items, static function (mixed $item): bool {
        return is_array($item) && strtolower((string) ($item['status'] ?? 'active')) === 'active';
    })));
}

function catalog_expanded_products(bool $activeOnly = true): array
{
    return catalog_expand_array(catalog_products($activeOnly));
}

function default_ring_style_cards(): array
{
    return [
        'solitaire' => ['value' => 'solitaire', 'label' => 'Solitaire', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product3.jpg'],
        'halo' => ['value' => 'halo', 'label' => 'Halo', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product10.jpg'],
        'hidden-halo' => ['value' => 'hidden-halo', 'label' => 'Hidden Halo', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product3.jpg'],
        'three-stone' => ['value' => 'three-stone', 'label' => 'Three Stone', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product8.jpg'],
        'vintage' => ['value' => 'vintage', 'label' => 'Vintage', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product4.jpg'],
        'toi-et-moi' => ['value' => 'toi-et-moi', 'label' => 'Toi et Moi', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product2.jpg'],
        'sidestones' => ['value' => 'sidestones', 'label' => 'Sidestones', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product1.jpg'],
    ];
}

function available_ring_style_cards(): array
{
    $defaults = default_ring_style_cards();
    $profile = catalog_attribute_profile('Ring');
    $profileCards = array_values((array) ($profile['style_cards'] ?? []));
    if ($profileCards === []) {
        $ringsProfile = catalog_attribute_profile('Rings');
        $profileCards = array_values((array) ($ringsProfile['style_cards'] ?? []));
    }

    $cards = [];
    foreach ($profileCards as $card) {
        if (!is_array($card)) {
            continue;
        }

        $value = clean_string((string) ($card['value'] ?? ''), 80);
        if ($value === '') {
            continue;
        }

        $cards[$value] = [
            'value' => $value,
            'label' => clean_string((string) ($card['label'] ?? ($defaults[$value]['label'] ?? $value)), 120),
            'image' => clean_image((string) ($card['image'] ?? ($defaults[$value]['image'] ?? ''))),
        ];
    }

    foreach ($defaults as $styleKey => $defaultCard) {
        if (isset($cards[$styleKey])) {
            continue;
        }
        $cards[$styleKey] = [
            'value' => $styleKey,
            'label' => clean_string((string) ($defaultCard['label'] ?? $styleKey), 120),
            'image' => clean_image((string) ($defaultCard['image'] ?? '')),
        ];
    }

    return $cards;
}

function available_ring_styles(): array
{
    $styles = [];
    foreach (available_ring_style_cards() as $styleKey => $card) {
        $label = clean_string((string) ($card['label'] ?? ''), 120);
        if ($label === '') {
            $label = ucwords(str_replace('-', ' ', $styleKey));
        }
        $styles[$styleKey] = $label;
    }
    return $styles;
}

function default_collection_selector_cards(string $type): array
{
    $type = strtolower(clean_string($type, 80));

    return match ($type) {
        'earring', 'earrings' => [
            'studs' => ['value' => 'studs', 'label' => 'Studs', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product1.jpg', 'product_ids' => ['prd-crystal-drops', 'prd-earl-teardrop']],
            'hoops' => ['value' => 'hoops', 'label' => 'Hoops', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product10.jpg', 'product_ids' => ['prd-crystal-drops']],
            'drops' => ['value' => 'drops', 'label' => 'Drops', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product7.jpg', 'product_ids' => ['prd-crystal-drops']],
            'white-gold' => ['value' => 'white-gold', 'label' => 'White Gold', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product2.jpg', 'product_ids' => ['prd-earl-teardrop']],
            'yellow-gold' => ['value' => 'yellow-gold', 'label' => 'Yellow Gold', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product3.jpg', 'product_ids' => ['prd-crystal-drops']],
            'huggies' => ['value' => 'huggies', 'label' => 'Huggies', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product8.jpg', 'product_ids' => ['prd-crystal-drops']],
            'essentials' => ['value' => 'essentials', 'label' => 'Essentials', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product4.jpg', 'product_ids' => ['prd-crystal-drops', 'prd-earl-teardrop']],
            'rose-gold' => ['value' => 'rose-gold', 'label' => 'Rose Gold', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product5.jpg', 'product_ids' => ['prd-earl-teardrop']],
            'fancy-studs' => ['value' => 'fancy-studs', 'label' => 'Fancy Studs', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product6.jpg', 'product_ids' => ['prd-crystal-drops']],
            'halo' => ['value' => 'halo', 'label' => 'Halo', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product9.jpg', 'product_ids' => ['prd-earl-teardrop']],
            'single-stud' => ['value' => 'single-stud', 'label' => 'Single Stud', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product1.jpg', 'product_ids' => ['prd-earl-teardrop']],
            'statement' => ['value' => 'statement', 'label' => 'Statement', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product10.jpg', 'product_ids' => ['prd-crystal-drops']],
            'bezel' => ['value' => 'bezel', 'label' => 'Bezel', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product2.jpg', 'product_ids' => ['prd-earl-teardrop']],
        ],
        'bracelet', 'bangles-bracelets', 'bangles-&-bracelets' => [
            'tennis-bracelet' => ['value' => 'tennis-bracelet', 'label' => 'Tennis Bracelet', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product7.jpg', 'product_ids' => ['prd-silver-cuff-band']],
            'light-weight' => ['value' => 'light-weight', 'label' => 'Light Weight', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product8.jpg', 'product_ids' => ['prd-silver-cuff-band']],
            'white-gold' => ['value' => 'white-gold', 'label' => 'White Gold', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product4.jpg', 'product_ids' => ['prd-silver-cuff-band']],
            'yellow-gold' => ['value' => 'yellow-gold', 'label' => 'Yellow Gold', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product10.jpg', 'product_ids' => ['prd-silver-cuff-band']],
            '17-5-length' => ['value' => '17-5-length', 'label' => '17.5cm Length', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product1.jpg', 'product_ids' => ['prd-silver-cuff-band']],
            '16-5-length' => ['value' => '16-5-length', 'label' => '16.5cm Length', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product2.jpg', 'product_ids' => ['prd-silver-cuff-band']],
            'diamond-bangle' => ['value' => 'diamond-bangle', 'label' => 'Diamond Bangle', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product3.jpg', 'product_ids' => ['prd-silver-cuff-band']],
            'trinity' => ['value' => 'trinity', 'label' => 'Trinity', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product9.jpg', 'product_ids' => ['prd-silver-cuff-band']],
            'emerald' => ['value' => 'emerald', 'label' => 'Emerald', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product10.jpg', 'product_ids' => ['prd-silver-cuff-band']],
            'oval' => ['value' => 'oval', 'label' => 'Oval', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product7.jpg', 'product_ids' => ['prd-silver-cuff-band']],
            'bezel-bracelets' => ['value' => 'bezel-bracelets', 'label' => 'Bezel Bracelets', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product5.jpg', 'product_ids' => ['prd-silver-cuff-band']],
        ],
        'pendant', 'pendants' => [
            'solitaire' => ['value' => 'solitaire', 'label' => 'Solitaire', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product1.jpg', 'product_ids' => ['prd-iamond-wedding-set']],
            'slider' => ['value' => 'slider', 'label' => 'Slider', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product2.jpg', 'product_ids' => ['prd-floral-brooch']],
            'dress' => ['value' => 'dress', 'label' => 'Dress', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product3.jpg', 'product_ids' => ['prd-iamond-wedding-set']],
            'white-gold' => ['value' => 'white-gold', 'label' => 'White Gold', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product4.jpg', 'product_ids' => ['prd-iamond-wedding-set']],
            'tennis' => ['value' => 'tennis', 'label' => 'Tennis', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product8.jpg', 'product_ids' => ['prd-letraset-animal']],
            'bezel' => ['value' => 'bezel', 'label' => 'Bezel', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product5.jpg', 'product_ids' => ['prd-floral-brooch']],
            'initial' => ['value' => 'initial', 'label' => 'Initial', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product6.jpg', 'product_ids' => ['prd-letraset-animal']],
            'yellow-gold' => ['value' => 'yellow-gold', 'label' => 'Yellow Gold', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product9.jpg', 'product_ids' => ['prd-letraset-animal']],
        ],
        'necklace', 'necklaces' => [
            'solitaire' => ['value' => 'solitaire', 'label' => 'Solitaire', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product1.jpg', 'product_ids' => ['prd-iamond-wedding-set']],
            'slider' => ['value' => 'slider', 'label' => 'Slider', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product2.jpg', 'product_ids' => ['prd-floral-brooch']],
            'dress' => ['value' => 'dress', 'label' => 'Dress', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product3.jpg', 'product_ids' => ['prd-iamond-wedding-set']],
            'white-gold' => ['value' => 'white-gold', 'label' => 'White Gold', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product4.jpg', 'product_ids' => ['prd-iamond-wedding-set']],
            'tennis' => ['value' => 'tennis', 'label' => 'Tennis', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product8.jpg', 'product_ids' => ['prd-letraset-animal']],
            'bezel' => ['value' => 'bezel', 'label' => 'Bezel', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product5.jpg', 'product_ids' => ['prd-floral-brooch']],
            'initial' => ['value' => 'initial', 'label' => 'Initial', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product6.jpg', 'product_ids' => ['prd-letraset-animal']],
            'yellow-gold' => ['value' => 'yellow-gold', 'label' => 'Yellow Gold', 'image' => 'https://htmldemo.net/monsta/monsta/assets/img/product/product9.jpg', 'product_ids' => ['prd-letraset-animal']],
        ],
        default => [],
    };
}

function available_collection_selector_cards(string $type): array
{
    $defaults = default_collection_selector_cards($type);
    $profile = catalog_attribute_profile($type);
    $profileCards = array_values((array) ($profile['selector_cards'] ?? []));

    $cards = [];
    foreach ($profileCards as $card) {
        if (!is_array($card)) {
            continue;
        }
        $value = clean_string((string) ($card['value'] ?? ''), 80);
        if ($value === '') {
            continue;
        }
        $cards[$value] = [
            'value' => $value,
            'label' => clean_string((string) ($card['label'] ?? ($defaults[$value]['label'] ?? $value)), 120),
            'image' => clean_image((string) ($card['image'] ?? ($defaults[$value]['image'] ?? ''))),
            'product_ids' => array_values((array) ($defaults[$value]['product_ids'] ?? [])),
        ];
    }

    foreach ($defaults as $value => $defaultCard) {
        if (isset($cards[$value])) {
            continue;
        }
        $cards[$value] = $defaultCard;
    }

    return $cards;
}

function homepage_style_type_label(string $type): string
{
    return match (strtolower(trim($type))) {
        'ring', 'rings' => 'Rings',
        'earring', 'earrings' => 'Earrings',
        'bracelet', 'bracelets', 'bangles', 'bangles & bracelets' => 'Bangles & Bracelets',
        'necklace', 'necklaces' => 'Necklaces',
        'pendant', 'pendants' => 'Pendants',
        'brooch', 'brooches' => 'Brooches',
        'jewellery set', 'jewellery sets' => 'Jewellery Sets',
        default => $type,
    };
}

function homepage_style_showcase_options(): array
{
    $content = site_content();
    $types = array_values(array_filter(array_unique(array_merge(
        array_keys(default_attribute_profiles()),
        array_map(static fn (mixed $value): string => clean_string((string) $value, 80), (array) ($content['catalog_meta']['product_types'] ?? []))
    ))));

    $options = [];
    foreach ($types as $type) {
        if ($type === '') {
            continue;
        }

        $normalizedType = strtolower(trim($type));
        $isRingType = in_array($normalizedType, ['ring', 'rings'], true);

        if ($isRingType) {
            foreach (available_ring_style_cards() as $styleValue => $card) {
                $label = clean_string((string) ($card['label'] ?? $styleValue), 120);
                if ($label === '') {
                    continue;
                }
                $key = strtolower($type) . '::' . clean_string((string) $styleValue, 80);
                $options[$key] = [
                    'id' => $key,
                    'type' => $type,
                    'type_label' => homepage_style_type_label($type),
                    'value' => clean_string((string) $styleValue, 80),
                    'label' => $label,
                    'image' => clean_image((string) ($card['image'] ?? '')),
                    'url' => resolve_link('/shop/?' . http_build_query(['type' => 'Ring', 'style' => $styleValue])),
                ];
            }
            continue;
        }

        foreach (available_collection_selector_cards($type) as $styleValue => $card) {
            $label = clean_string((string) ($card['label'] ?? $styleValue), 120);
            if ($label === '') {
                continue;
            }
            $key = strtolower($type) . '::' . clean_string((string) $styleValue, 80);
            $options[$key] = [
                'id' => $key,
                'type' => $type,
                'type_label' => homepage_style_type_label($type),
                'value' => clean_string((string) $styleValue, 80),
                'label' => $label,
                'image' => clean_image((string) ($card['image'] ?? '')),
                'url' => resolve_link('/shop/?' . http_build_query(['type' => $type, 'facet' => $styleValue])),
            ];
        }
    }

    return $options;
}

function homepage_style_showcase_cards(): array
{
    $options = homepage_style_showcase_options();
    $selectedIds = array_values(array_filter(array_map(
        static fn (mixed $value): string => clean_string((string) $value, 120),
        (array) (site_content()['shop_by_style']['style_ids'] ?? [])
    ), static fn (string $value): bool => $value !== ''));

    $cards = [];
    foreach ($selectedIds as $id) {
        if (isset($options[$id])) {
            $cards[] = $options[$id];
        }
    }

    return $cards;
}

function available_diamond_shapes(): array
{
    return [
        'round' => 'Round',
        'oval' => 'Oval',
        'cushion' => 'Cushion',
        'princess' => 'Princess',
        'emerald' => 'Emerald',
        'pear' => 'Pear',
        'marquise' => 'Marquise',
        'radiant' => 'Radiant',
        'asscher' => 'Asscher',
        'heart' => 'Heart'
    ];
}

function header_search_normalize_term(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? '';
    return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
}

function header_search_normalize_catalog_type(string $type): string
{
    return match (header_search_normalize_term($type)) {
        'ring', 'rings' => 'Ring',
        'earring', 'earrings' => 'Earring',
        'bracelet', 'bracelets', 'bangles', 'bangles bracelets', 'bangles bracelet' => 'Bracelet',
        'necklace', 'necklaces', 'neckless', 'necklesses' => 'Necklace',
        'pendant', 'pendants' => 'Pendant',
        'mangalsutra', 'mangalsutras' => 'Mangalsutra',
        default => clean_string($type, 80),
    };
}

function header_search_collection_meta(): array
{
    return [
        'Ring' => [
            'label' => 'Rings',
            'subtitle' => 'Shop Collection',
            'aliases' => ['ring', 'rings', 'engagement ring', 'engagement rings'],
        ],
        'Earring' => [
            'label' => 'Earrings',
            'subtitle' => 'Shop Collection',
            'aliases' => ['earring', 'earrings', 'stud', 'studs', 'hoop', 'hoops', 'drop earring', 'drop earrings'],
        ],
        'Pendant' => [
            'label' => 'Pendants',
            'subtitle' => 'Shop Collection',
            'aliases' => ['pendant', 'pendants'],
        ],
        'Bracelet' => [
            'label' => 'Bangles & Bracelets',
            'subtitle' => 'Shop Collection',
            'aliases' => ['bracelet', 'bracelets', 'bangle', 'bangles'],
        ],
        'Necklace' => [
            'label' => 'Necklaces',
            'subtitle' => 'Shop Collection',
            'aliases' => ['necklace', 'necklaces'],
        ],
        'Mangalsutra' => [
            'label' => 'Mangalsutra',
            'subtitle' => 'Shop Collection',
            'aliases' => ['mangalsutra', 'mangalsutras'],
        ],
    ];
}

function header_search_index(): array
{
    static $index = null;
    if (is_array($index)) {
        return $index;
    }

    $content = site_content();
    $collectionMeta = header_search_collection_meta();
    $products = catalog_products();
    $categoryImages = [];
    foreach ((array) ($content['category_cards'] ?? []) as $card) {
        if (!is_array($card)) {
            continue;
        }

        $title = clean_string((string) ($card['title'] ?? ''), 80);
        if ($title === '') {
            continue;
        }

        $categoryImages[header_search_normalize_term($title)] = clean_image((string) ($card['image'] ?? ''));
    }

    $diamondShapeImages = [];
    foreach ((array) ($content['diamond_shapes']['items'] ?? []) as $shapeItem) {
        if (!is_array($shapeItem)) {
            continue;
        }

        $shapeName = clean_string((string) ($shapeItem['name'] ?? ''), 80);
        if ($shapeName === '') {
            continue;
        }

        $diamondShapeImages[header_search_normalize_term($shapeName)] = clean_image((string) ($shapeItem['icon_image'] ?? $shapeItem['image'] ?? ''));
    }

    $index = [];
    $seen = [];
    $addSuggestion = static function (array $suggestion) use (&$index, &$seen): void {
        $label = clean_string((string) ($suggestion['label'] ?? ''), 140);
        $url = clean_string((string) ($suggestion['url'] ?? ''), 500);
        if ($label === '' || $url === '') {
            return;
        }

        $key = header_search_normalize_term($label) . '|' . $url;
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;

        $searchTextParts = [];
        foreach ((array) ($suggestion['search_text'] ?? []) as $part) {
            $cleanPart = clean_string((string) $part, 240);
            if ($cleanPart !== '') {
                $searchTextParts[] = $cleanPart;
            }
        }

        $index[] = [
            'label' => $label,
            'subtitle' => clean_string((string) ($suggestion['subtitle'] ?? ''), 120),
            'url' => $url,
            'kind' => clean_string((string) ($suggestion['kind'] ?? 'search'), 40),
            'image' => clean_image((string) ($suggestion['image'] ?? '')),
            'search_text' => header_search_normalize_term(implode(' ', array_merge([$label], $searchTextParts))),
        ];
    };

    $firstProductImageByType = [];
    $productColorsByType = [];
    foreach ($products as $product) {
        $normalizedType = header_search_normalize_catalog_type((string) ($product['product_type'] ?? ''));
        if (!isset($collectionMeta[$normalizedType])) {
            continue;
        }

        $image = clean_image((string) ($product['default_image'] ?? $product['hover_image'] ?? $product['popup_image'] ?? ''));
        if ($image !== '' && !isset($firstProductImageByType[$normalizedType])) {
            $firstProductImageByType[$normalizedType] = $image;
        }

        $color = clean_string((string) ($product['color'] ?? ''), 80);
        if ($color !== '') {
            $productColorsByType[$normalizedType][] = $color;
        }
    }

    foreach ($collectionMeta as $typeKey => $meta) {
        $label = $meta['label'];
        $image = $categoryImages[header_search_normalize_term($label)] ?? ($firstProductImageByType[$typeKey] ?? '');
        $addSuggestion([
            'label' => $label,
            'subtitle' => $meta['subtitle'],
            'url' => resolve_link('/shop/?type=' . rawurlencode($typeKey)),
            'kind' => 'collection',
            'image' => $image,
            'search_text' => array_merge([$label], $meta['aliases']),
        ]);
    }

    foreach (available_ring_styles() as $styleKey => $styleLabel) {
        $card = available_ring_style_cards()[$styleKey] ?? [];
        $addSuggestion([
            'label' => $styleLabel . ' Rings',
            'subtitle' => 'Ring Style',
            'url' => resolve_link('/shop/?' . http_build_query(['type' => 'Ring', 'style' => $styleKey])),
            'kind' => 'style',
            'image' => clean_image((string) ($card['image'] ?? '')),
            'search_text' => [$styleLabel, $styleKey, 'ring', 'rings', 'diamond ring'],
        ]);
    }

    foreach (['Earring', 'Pendant', 'Bracelet', 'Necklace'] as $typeKey) {
        $meta = $collectionMeta[$typeKey] ?? null;
        if ($meta === null) {
            continue;
        }

        foreach (available_collection_selector_cards($typeKey) as $facetKey => $card) {
            $facetLabel = clean_string((string) ($card['label'] ?? $facetKey), 120);
            if ($facetLabel === '') {
                continue;
            }

            $addSuggestion([
                'label' => $facetLabel,
                'subtitle' => $meta['label'] . ' Category',
                'url' => resolve_link('/shop/?' . http_build_query(['type' => $typeKey, 'facet' => $facetKey])),
                'kind' => 'facet',
                'image' => clean_image((string) ($card['image'] ?? '')),
                'search_text' => array_merge([$facetLabel, $facetKey, $meta['label']], $meta['aliases']),
            ]);
        }
    }

    foreach (available_diamond_shapes() as $shapeKey => $shapeLabel) {
        $shapeImage = $diamondShapeImages[header_search_normalize_term($shapeLabel)] ?? ($firstProductImageByType['Ring'] ?? '');
        $addSuggestion([
            'label' => $shapeLabel . ' Diamond Rings',
            'subtitle' => 'Diamond Shape',
            'url' => resolve_link('/shop/?' . http_build_query(['type' => 'Ring', 'shape' => $shapeKey])),
            'kind' => 'shape',
            'image' => $shapeImage,
            'search_text' => [$shapeLabel, $shapeKey, $shapeLabel . ' diamond', $shapeLabel . ' diamonds', 'diamond ring', 'diamond rings', 'ring', 'rings'],
        ]);
    }

    $metalFamilies = [
        'gold' => 'Gold',
        'yellow gold' => 'Yellow Gold',
        'white gold' => 'White Gold',
        'rose gold' => 'Rose Gold',
        'silver' => 'Silver',
        'platinum' => 'Platinum',
    ];
    foreach ($collectionMeta as $typeKey => $meta) {
        $typeColors = array_map('header_search_normalize_term', array_values(array_unique($productColorsByType[$typeKey] ?? [])));
        $typeFacetCards = available_collection_selector_cards($typeKey);

        foreach ($metalFamilies as $queryValue => $displayLabel) {
            $matchFound = false;
            foreach ($typeColors as $colorValue) {
                if (($queryValue === 'gold' && str_contains($colorValue, 'gold')) || str_contains($colorValue, $queryValue)) {
                    $matchFound = true;
                    break;
                }
            }

            $matchedFacetKey = null;
            foreach ($typeFacetCards as $facetKey => $facetCard) {
                $facetLabel = header_search_normalize_term((string) ($facetCard['label'] ?? $facetKey));
                if ($facetLabel === '') {
                    continue;
                }

                if (($queryValue === 'gold' && str_contains($facetLabel, 'gold')) || str_contains($facetLabel, $queryValue)) {
                    if ($queryValue === 'gold' && $facetKey === 'yellow-gold') {
                        $matchedFacetKey = $facetKey;
                        break;
                    }
                    $matchedFacetKey ??= $facetKey;
                }
            }

            if (!$matchFound) {
                if ($matchedFacetKey === null) {
                    continue;
                }
            }

            $metalUrl = $matchFound
                ? resolve_link('/shop/?' . http_build_query(['type' => $typeKey, 'q' => $queryValue]))
                : resolve_link('/shop/?' . http_build_query(['type' => $typeKey, 'facet' => $matchedFacetKey]));

            $metalImage = $firstProductImageByType[$typeKey] ?? '';
            if ($metalImage === '' && $matchedFacetKey !== null) {
                $metalImage = clean_image((string) ($typeFacetCards[$matchedFacetKey]['image'] ?? ''));
            }

            $addSuggestion([
                'label' => $displayLabel . ' ' . $meta['label'],
                'subtitle' => 'Metal Match',
                'url' => $metalUrl,
                'kind' => 'metal',
                'image' => $metalImage,
                'search_text' => [$displayLabel, $queryValue, $meta['label'], $displayLabel . ' ' . $meta['label'], 'metal'],
            ]);
        }
    }

    foreach ($products as $product) {
        $productName = clean_string((string) ($product['name'] ?? ''), 140);
        if ($productName === '') {
            continue;
        }

        $normalizedType = header_search_normalize_catalog_type((string) ($product['product_type'] ?? ''));
        $productTypeLabel = $collectionMeta[$normalizedType]['label'] ?? clean_string((string) ($product['product_type'] ?? 'Jewellery'), 80);
        $keywords = [
            $productName,
            $productTypeLabel,
            (string) ($product['product_type'] ?? ''),
            (string) ($product['color'] ?? ''),
            (string) ($product['category'] ?? ''),
            (string) ($product['description'] ?? ''),
        ];

        foreach ((array) ($product['diamondShapes'] ?? []) as $shapeKey) {
            $shapeKey = clean_string((string) $shapeKey, 80);
            if ($shapeKey === '') {
                continue;
            }
            $keywords[] = $shapeKey;
            $keywords[] = available_diamond_shapes()[$shapeKey] ?? $shapeKey;
        }

        foreach ((array) ($product['styles'] ?? []) as $styleKey) {
            $styleKey = clean_string((string) $styleKey, 80);
            if ($styleKey === '') {
                continue;
            }
            $keywords[] = $styleKey;
            $keywords[] = available_ring_styles()[$styleKey] ?? $styleKey;
        }

        $addSuggestion([
            'label' => $productName,
            'subtitle' => $productTypeLabel . ' Product',
            'url' => product_url($product),
            'kind' => 'product',
            'image' => clean_image((string) ($product['default_image'] ?? $product['hover_image'] ?? $product['popup_image'] ?? '')),
            'search_text' => $keywords,
        ]);
    }

    return $index;
}

function product_price_value(array $product): float
{
    $raw = (string) ($product['new_price'] ?? $product['old_price'] ?? '0');
    $normalized = preg_replace('/[^0-9.]/', '', $raw) ?? '0';
    return (float) $normalized;
}

function filter_catalog_products(array $products, array $filters): array
{
    $type = sanitize_text((string) ($filters['type'] ?? ''));
    $color = sanitize_text((string) ($filters['color'] ?? ''));
    $category = sanitize_text((string) ($filters['category'] ?? ''));
    $query = sanitize_text((string) ($filters['q'] ?? ''));
    $shape = sanitize_text((string) ($filters['shape'] ?? ''));
    $sort = sanitize_text((string) ($filters['sort'] ?? 'featured'));
    $availableShapes = available_diamond_shapes();

    $filtered = array_values(array_filter($products, static function (array $product) use ($type, $color, $category, $query, $shape, $availableShapes): bool {
        if ($type !== '' && strcasecmp((string) ($product['product_type'] ?? ''), $type) !== 0) {
            return false;
        }
        if ($color !== '' && strcasecmp((string) ($product['color'] ?? ''), $color) !== 0) {
            return false;
        }
        if ($category !== '' && strcasecmp((string) ($product['category'] ?? ''), $category) !== 0) {
            return false;
        }

        $styleNames = [];
        foreach ((array) ($product['styles'] ?? []) as $styleKey) {
            $styleKey = (string) $styleKey;
            $styleNames[] = available_ring_styles()[$styleKey] ?? $styleKey;
        }

        $shapeNames = [];
        foreach ((array) ($product['diamondShapes'] ?? []) as $shapeKey) {
            $shapeKey = (string) $shapeKey;
            $shapeNames[] = available_diamond_shapes()[$shapeKey] ?? $shapeKey;
        }

        $haystack = strtolower(trim(implode(' ', [
            (string) ($product['name'] ?? ''),
            (string) ($product['category'] ?? ''),
            (string) ($product['description'] ?? ''),
            (string) ($product['product_type'] ?? ''),
            (string) ($product['color'] ?? ''),
            implode(' ', (array) ($product['subcategories'] ?? [])),
            implode(' ', (array) ($product['features'] ?? [])),
            implode(' ', $styleNames),
            implode(' ', $shapeNames),
        ])));

        if ($query !== '' && !str_contains($haystack, strtolower($query))) {
            return false;
        }

        if ($shape !== '') {
            $requestedShape = strtolower(trim($shape));
            $shapeValues = [];

            foreach ((array) ($product['diamondShapes'] ?? []) as $shapeKey) {
                $normalizedShape = strtolower(trim((string) $shapeKey));
                if ($normalizedShape === '') {
                    continue;
                }

                $shapeValues[] = $normalizedShape;
                if (isset($availableShapes[$normalizedShape])) {
                    $shapeValues[] = strtolower($availableShapes[$normalizedShape]);
                }
            }

            if (function_exists('product_option_data')) {
                foreach ((array) (product_option_data($product)['diamond_shapes'] ?? []) as $shapeOption) {
                    if (!is_array($shapeOption)) {
                        continue;
                    }

                    $optionValue = strtolower(trim((string) ($shapeOption['value'] ?? '')));
                    $optionLabel = strtolower(trim((string) ($shapeOption['label'] ?? '')));
                    if ($optionValue !== '') {
                        $shapeValues[] = $optionValue;
                    }
                    if ($optionLabel !== '') {
                        $shapeValues[] = $optionLabel;
                    }
                }
            }

            $shapeValues = array_values(array_unique(array_filter($shapeValues, static fn (string $value): bool => $value !== '')));
            if (!in_array($requestedShape, $shapeValues, true)) {
                return false;
            }
        }

        return true;
    }));

    usort($filtered, static function (array $left, array $right) use ($sort): int {
        return match ($sort) {
            'price-low' => product_price_value($left) <=> product_price_value($right),
            'price-high' => product_price_value($right) <=> product_price_value($left),
            'name-asc' => strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')),
            'name-desc' => strcasecmp((string) ($right['name'] ?? ''), (string) ($left['name'] ?? '')),
            default => 0,
        };
    });

    return catalog_sort_by_inventory($filtered);
}

function render_product_card(array $product, array $extraParams = []): void
{
    $defaultImage = $product['default_image'] ?? '';
    $hoverImage = $product['hover_image'] ?? $defaultImage;
    $popupImage = $product['popup_image'] ?? $defaultImage;
    $productUrl = product_url($product, $extraParams);
    $customer = current_customer();
    $isWishlisted = customer_has_wishlist_product($customer, (string) ($product['id'] ?? ''));
    $returnTo = current_internal_url($productUrl);
    $cardVideoMime = static function (string $path): string {
        $extension = strtolower(pathinfo((string) (parse_url($path, PHP_URL_PATH) ?? $path), PATHINFO_EXTENSION));

        return match ($extension) {
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
            'm4v' => 'video/x-m4v',
            default => 'video/mp4',
        };
    };
    $renderCardMedia = static function (string $path, string $className, string $alt) use ($cardVideoMime): string {
        $resolvedPath = clean_image($path);
        if ($resolvedPath === '') {
            return '';
        }

        if (media_asset_type($resolvedPath) === 'video') {
            return '<video class="' . h($className) . '" muted autoplay loop playsinline preload="metadata"><source src="' . h($resolvedPath) . '" type="' . h($cardVideoMime($resolvedPath)) . '"></video>';
        }

        return '<img class="' . h($className) . '" src="' . h($resolvedPath) . '" alt="' . h($alt) . '">';
    };
    $inventoryStatus = function_exists('product_inventory_status')
        ? product_inventory_status($product, ['metal' => (string) ($product['color'] ?? '')])
        : ['out_of_stock' => false, 'low_stock' => false];
    $cardIsOutOfStock = !empty($inventoryStatus['out_of_stock']);
    ?>
    <div class="prod-card">
      <div class="prod-img-box">
        <?php if ($cardIsOutOfStock): ?>
          <span class="prod-stock-badge">OUT OF STOCK</span>
        <?php endif; ?>
        
        <a href="<?= h($productUrl) ?>" class="prod-img-link" aria-label="<?= h('View ' . ($product['name'] ?? 'product')) ?>">
          <?= $renderCardMedia($defaultImage, 'img-default blend-darken', (string) ($product['name'] ?? 'Product image')) ?>
          <?= $renderCardMedia($hoverImage, 'img-hover blend-darken', (string) (($product['name'] ?? 'Product image') . ' alternate view')) ?>
        </a>
      </div>
      
      <div class="prod-card-body">
        <div class="prod-name"><a href="<?= h($productUrl) ?>"><?= h($product['name'] ?? '') ?></a></div>
        <div class="prod-desc"><?= h($product['description'] ?? '') ?></div>
        
        <div class="prod-prices-premium">
          <span class="price-prefix">FROM</span>
          <span class="price-value"><?= h($product['new_price'] ?? $product['old_price'] ?? '') ?></span>
        </div>
        <a href="<?= h($productUrl) ?>" class="prod-hover-btn">Shop Now</a>
      </div>
    </div>
    <?php
}

require_once __DIR__ . '/storefront.php';
