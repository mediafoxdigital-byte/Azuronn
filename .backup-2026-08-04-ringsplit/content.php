<?php

declare(strict_types=1);

require_once __DIR__ . '/supabase.php';

function content_data_dir(): string
{
    return dirname(__DIR__) . '/data';
}

function content_file_path(): string
{
    return content_data_dir() . '/site-content.json';
}

function content_lock_file_path(): string
{
    return content_data_dir() . '/site-content.lock';
}

function local_site_content_candidate(array $defaults): array
{
    ensure_content_storage();
    $file = content_file_path();
    if (!is_file($file)) {
        return $defaults;
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $defaults;
}

function local_save_site_content(array $content): void
{
    ensure_content_storage();
    $normalized = normalize_site_content($content);
    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException('Unable to encode site content.');
    }

    $file = content_file_path();
    $temp = $file . '.tmp';

    $handle = fopen($temp, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Unable to open temporary content file.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock content file.');
        }
        fwrite($handle, $json);
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    rename($temp, $file);
}

function site_content_with_lock(callable $callback): mixed
{
    ensure_content_storage();
    $lockPath = content_lock_file_path();
    $handle = fopen($lockPath, 'c+b');
    if ($handle === false) {
        throw new RuntimeException('Unable to open content lock file.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock site content.');
        }

        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function default_site_content(): array
{
    /** @var array $defaults */
    $defaults = require content_data_dir() . '/default-site-content.php';
    return $defaults;
}

function ensure_content_storage(): void
{
    $dir = content_data_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    }
}

function clean_string(mixed $value, int $maxLength = 5000): string
{
    $value = is_scalar($value) ? trim((string) $value) : '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function clean_multiline(mixed $value, int $maxLength = 12000): string
{
    $value = is_scalar($value) ? trim((string) $value) : '';
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function rich_text_plain_text(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
    return $text;
}

function rich_text_excerpt(string $html, int $maxLength = 240): string
{
    $plain = rich_text_plain_text($html);
    if ($plain === '') {
        return '';
    }

    $length = function_exists('mb_strlen') ? mb_strlen($plain) : strlen($plain);
    if ($length <= $maxLength) {
        return $plain;
    }

    $slice = function_exists('mb_substr') ? mb_substr($plain, 0, $maxLength + 1) : substr($plain, 0, $maxLength + 1);
    $lastSpace = function_exists('mb_strrpos') ? mb_strrpos($slice, ' ') : strrpos($slice, ' ');
    if ($lastSpace !== false && $lastSpace > (int) floor($maxLength * 0.6)) {
        $slice = function_exists('mb_substr') ? mb_substr($slice, 0, $lastSpace) : substr($slice, 0, $lastSpace);
    } else {
        $slice = function_exists('mb_substr') ? mb_substr($slice, 0, $maxLength) : substr($slice, 0, $maxLength);
    }

    return rtrim($slice, " \t\n\r\0\x0B,.;:-") . '…';
}

function rich_text_from_plain(string $text): string
{
    $text = clean_multiline($text, 20000);
    if ($text === '') {
        return '';
    }

    $paragraphs = preg_split("/\n\s*\n/", $text) ?: [];
    $chunks = [];

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $paragraph = nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        $chunks[] = '<p>' . $paragraph . '</p>';
    }

    return implode('', $chunks);
}

function clean_rich_text(mixed $value, int $maxLength = 20000): string
{
    $html = is_scalar($value) ? trim((string) $value) : '';
    $html = str_replace(["\r\n", "\r"], "\n", $html);
    $html = function_exists('mb_substr') ? mb_substr($html, 0, $maxLength) : substr($html, 0, $maxLength);

    if ($html === '') {
        return '';
    }

    if (strip_tags($html) === $html) {
        $html = rich_text_from_plain($html);
    }

    if (!class_exists('DOMDocument')) {
        return strip_tags($html, '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><blockquote><a><hr>');
    }

    $allowedTags = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'a' => ['href'],
        'hr' => [],
    ];
    $tagMap = [
        'div' => 'p',
        'section' => 'p',
        'article' => 'p',
    ];

    $previousUseErrors = libxml_use_internal_errors(true);
    $source = new DOMDocument('1.0', 'UTF-8');
    $source->loadHTML('<?xml encoding="utf-8" ?><div id="rich-text-root">' . $html . '</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    $root = $source->getElementById('rich-text-root');

    if (!$root instanceof DOMElement) {
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);
        return rich_text_from_plain(rich_text_plain_text($html));
    }

    $target = new DOMDocument('1.0', 'UTF-8');
    $targetRoot = $target->createElement('div');
    $target->appendChild($targetRoot);

    $appendSanitized = function (DOMNode $node, DOMNode $parent) use (&$appendSanitized, $allowedTags, $tagMap, $target): void {
        if ($node instanceof DOMText) {
            $value = preg_replace('/\s+/u', ' ', $node->nodeValue ?? '') ?? '';
            if ($value !== '') {
                $parent->appendChild($target->createTextNode($value));
            }
            return;
        }

        if (!$node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);
        $tag = $tagMap[$tag] ?? $tag;

        if (!isset($allowedTags[$tag])) {
            foreach ($node->childNodes as $child) {
                $appendSanitized($child, $parent);
            }
            return;
        }

        $element = $target->createElement($tag);
        if ($tag === 'a') {
            $href = clean_link((string) $node->getAttribute('href'));
            $element->setAttribute('href', $href);
            if (preg_match('~^(?:https?:)?//~i', $href)) {
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        foreach ($node->childNodes as $child) {
            $appendSanitized($child, $element);
        }

        $isVoid = in_array($tag, ['br', 'hr'], true);
        $hasContent = trim($element->textContent ?? '') !== '' || $element->hasChildNodes();
        if ($isVoid || $hasContent) {
            $parent->appendChild($element);
        }
    };

    foreach ($root->childNodes as $child) {
        $appendSanitized($child, $targetRoot);
    }

    $clean = '';
    foreach ($targetRoot->childNodes as $child) {
        $clean .= $target->saveHTML($child);
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previousUseErrors);

    return trim($clean);
}

function clean_int(mixed $value, int $min = 0, int $max = 100000): int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    if ($filtered === false) {
        return $min;
    }

    return max($min, min($max, $filtered));
}

function clean_bool(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 'true', 'on'], true);
}

function clean_link(mixed $value): string
{
    $link = clean_string($value, 2048);
    if ($link === '') {
        return '#';
    }

    if ($link === '#') {
        return $link;
    }

    if (preg_match('~^(?:https?:)?//~i', $link)) {
        return filter_var($link, FILTER_VALIDATE_URL) ? $link : '#';
    }

    if (preg_match('~^(mailto:|tel:)~i', $link)) {
        return $link;
    }

    if ($link[0] === '/' || $link[0] === '#') {
        return $link;
    }

    return '/' . ltrim($link, '/');
}

function clean_image(mixed $value): string
{
    $image = clean_link($value);
    return $image === '#' ? '' : $image;
}

function clean_icon(mixed $value): string
{
    $icon = preg_replace('/[^a-z0-9\-\s]/i', '', clean_string($value, 120)) ?? '';
    return trim($icon) !== '' ? trim($icon) : 'fas fa-gem';
}

function clean_color(mixed $value, string $fallback = '#b18861'): string
{
    $color = clean_string($value, 32);
    return preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : $fallback;
}

function clean_tone(mixed $value): string
{
    $tone = preg_replace('/[^a-z\-]/i', '', clean_string($value, 40)) ?? '';
    return $tone !== '' ? strtolower($tone) : 'classic';
}

function clean_items(array $items, callable $sanitizer): array
{
    $clean = [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        $clean[] = $sanitizer($item, $index);
    }
    return $clean;
}

function clean_link_item(array $item): array
{
    return [
        'label' => clean_string($item['label'] ?? '', 120),
        'url' => clean_link($item['url'] ?? '#'),
    ];
}

function content_slug(string $value, string $fallback = 'item'): string
{
    $value = strtolower(clean_string($value, 80));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : $fallback;
}

function content_id(string $prefix, array $item, int $index = 0, string $fallbackField = 'name'): string
{
    $raw = clean_string($item['id'] ?? '', 80);
    if ($raw !== '') {
        $slug = content_slug($raw, $prefix . '-' . ($index + 1));
        return $prefix . '-' . ltrim($slug, $prefix . '-');
    }

    $seed = clean_string($item[$fallbackField] ?? '', 80);
    return $prefix . '-' . content_slug($seed, (string) ($index + 1));
}

function clean_select_ids(array $items, array $validIds): array
{
    $lookup = array_fill_keys($validIds, true);
    $clean = [];
    foreach ($items as $item) {
        $id = clean_string((string) $item, 80);
        if ($id !== '' && isset($lookup[$id]) && !in_array($id, $clean, true)) {
            $clean[] = $id;
        }
    }
    return $clean;
}

function infer_product_type(array $item): string
{
    $value = clean_string($item['product_type'] ?? '', 80);
    if ($value !== '') {
        return $value;
    }

    $haystack = strtolower(trim((string) (($item['name'] ?? '') . ' ' . ($item['category'] ?? ''))));
    return match (true) {
        str_contains($haystack, 'bracelet'), str_contains($haystack, 'cuff'), str_contains($haystack, 'bangle') => 'Bracelet',
        str_contains($haystack, 'necklace') => 'Necklace',
        str_contains($haystack, 'pendant') => 'Pendant',
        str_contains($haystack, 'earring'), str_contains($haystack, 'drops'), str_contains($haystack, 'teardrop') => 'Earring',
        str_contains($haystack, 'brooch') => 'Brooch',
        str_contains($haystack, 'set') => 'Jewellery Set',
        str_contains($haystack, 'ring'), str_contains($haystack, 'band'), str_contains($haystack, 'solitaire') => 'Rings',
        default => 'Rings',
    };
}

function infer_product_color(array $item): string
{
    $value = clean_string($item['color'] ?? '', 80);
    if ($value !== '') {
        return $value;
    }

    $haystack = strtolower(trim((string) (($item['name'] ?? '') . ' ' . ($item['category'] ?? ''))));
    return match (true) {
        str_contains($haystack, 'rose') => 'Rose Gold',
        str_contains($haystack, 'white gold') => 'White Gold',
        str_contains($haystack, 'silver') => 'Silver',
        str_contains($haystack, 'platinum') => 'Platinum',
        str_contains($haystack, 'emerald') => 'Emerald Green',
        str_contains($haystack, 'ruby') => 'Ruby Red',
        str_contains($haystack, 'diamond'), str_contains($haystack, 'pearl'), str_contains($haystack, 'crystal'), str_contains($haystack, 'white') => 'Diamond White',
        default => 'Yellow Gold',
    };
}

function clean_string_list(array $items, int $maxLength = 80): array
{
    $clean = [];
    foreach ($items as $item) {
        $value = clean_string((string) $item, $maxLength);
        if ($value !== '' && !in_array($value, $clean, true)) {
            $clean[] = $value;
        }
    }
    return $clean;
}

function product_choice_generated_value(array $item, string $type, int $index = 0): string
{
    $label = clean_string((string) ($item['label'] ?? ''), 120);
    $kicker = clean_string((string) ($item['kicker'] ?? ''), 30);
    $caption = clean_string((string) ($item['caption'] ?? ''), 60);

    return match ($type) {
        'choice-color' => trim($kicker . ' ' . $label) !== '' ? trim($kicker . ' ' . $label) : 'choice-' . ($index + 1),
        'choice-size' => trim($label . ($caption !== '' ? ' / ' . $caption : '')) !== '' ? trim($label . ($caption !== '' ? ' / ' . $caption : '')) : 'size-' . ($index + 1),
        'option-detail', 'option-delivery' => content_slug($label, 'option-' . ($index + 1)),
        default => $label !== '' ? $label : 'option-' . ($index + 1),
    };
}

function clean_product_choice_list(array $items, string $type): array
{
    $clean = [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $value = clean_string((string) ($item['value'] ?? ''), 80);
        $label = clean_string((string) ($item['label'] ?? ''), 120);
        if ($label === '') {
            continue;
        }
        if ($value === '') {
            $value = product_choice_generated_value($item, $type, $index);
        }

        $entry = [
            'value' => $value,
            'label' => $label,
        ];

        if ($type === 'choice-color') {
            $entry['kicker'] = clean_string((string) ($item['kicker'] ?? ''), 30);
            $entry['tone'] = clean_tone((string) ($item['tone'] ?? 'classic'));
        } elseif ($type === 'choice-size') {
            $entry['caption'] = clean_string((string) ($item['caption'] ?? ''), 60);
            $entry['tone'] = clean_tone((string) ($item['tone'] ?? 'classic'));
        } elseif ($type === 'option-detail') {
            $entry['description'] = clean_multiline((string) ($item['description'] ?? ''), 220);
            // Palette options carry NO price/surcharge — those live per-product in
            // the upload Metal Matrix (the reference flow). Only the display colour
            // (metal orb + nav circle) and the optional band swatch image persist.
            $hex = clean_string((string) ($item['color_hex'] ?? ''), 7);
            $entry['color_hex'] = preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : '';
            $entry['image'] = clean_image((string) ($item['image'] ?? ''));
        } elseif ($type === 'option-delivery') {
            $priceRaw = preg_replace('/[^0-9.]/', '', (string) ($item['price'] ?? '0')) ?? '0';
            $entry['description'] = clean_multiline((string) ($item['description'] ?? ''), 220);
            $entry['price'] = round(max(0, (float) $priceRaw), 2);
            $entry['price_label'] = clean_string((string) ($item['price_label'] ?? ''), 40);
            $entry['badge'] = clean_string((string) ($item['badge'] ?? ''), 40);
        }

        $clean[] = $entry;
    }
    return $clean;
}


function clean_attribute_profile_item(array $item, string $type = ''): array
{
    $type = $type !== '' ? clean_string($type, 80) : clean_string((string) ($item['type'] ?? ''), 80);
    $colorDisplay = clean_string((string) ($item['option_color_display'] ?? ''), 40);
    if (!in_array($colorDisplay, ['compact', 'jewellery-metals'], true)) {
        $colorDisplay = '';
    }
    $sizeDisplay = clean_string((string) ($item['option_size_display'] ?? ''), 40);
    if (!in_array($sizeDisplay, ['compact', 'stone-weights'], true)) {
        $sizeDisplay = '';
    }

    $cleanStyleCardList = static fn (array $cards): array => clean_items($cards, static function (array $card, int $index): array {
        $defaultStyleKeys = ['solitaire', 'halo', 'hidden-halo', 'three-stone', 'vintage', 'toi-et-moi', 'sidestones'];
        $label = clean_string((string) ($card['label'] ?? ''), 120);
        $value = content_slug((string) ($card['value'] ?? ''), '');
        if ($value === '') {
            $value = $label !== '' ? content_slug($label, 'style-' . ($index + 1)) : ($defaultStyleKeys[$index] ?? ('style-' . ($index + 1)));
        }

        return [
            'value' => $value,
            'label' => $label !== '' ? $label : ucwords(str_replace('-', ' ', $value)),
            'image' => clean_image((string) ($card['image'] ?? '')),
        ];
    });

    return [
        'type' => $type,
        'option_color_label' => clean_string((string) ($item['option_color_label'] ?? ''), 60),
        'option_size_label' => clean_string((string) ($item['option_size_label'] ?? ''), 60),
        'option_color_display' => $colorDisplay,
        'option_size_display' => $sizeDisplay,
        'option_colors' => clean_string_list((array) ($item['option_colors'] ?? []), 80),
        'option_sizes' => clean_string_list((array) ($item['option_sizes'] ?? []), 80),
        'option_color_choices' => clean_product_choice_list((array) ($item['option_color_choices'] ?? []), 'choice-color'),
        'option_size_choices' => clean_product_choice_list((array) ($item['option_size_choices'] ?? []), 'choice-size'),
        'option_metal_options' => clean_product_choice_list((array) ($item['option_metal_options'] ?? []), 'option-detail'),
        'option_band_claw_metal_options' => clean_product_choice_list((array) ($item['option_band_claw_metal_options'] ?? []), 'option-detail'),
        'option_delivery_options' => clean_product_choice_list((array) ($item['option_delivery_options'] ?? []), 'option-delivery'),
        'selector_cards' => clean_items((array) ($item['selector_cards'] ?? []), static function (array $card, int $index): array {
            $label = clean_string((string) ($card['label'] ?? ''), 120);
            $value = content_slug((string) ($card['value'] ?? ''), '');
            if ($value === '') {
                $value = $label !== '' ? content_slug($label, 'selector-' . ($index + 1)) : 'selector-' . ($index + 1);
            }

            return [
                'value' => $value,
                'label' => $label !== '' ? $label : ucwords(str_replace('-', ' ', $value)),
                'image' => clean_image((string) ($card['image'] ?? '')),
            ];
        }),
        'style_cards' => $cleanStyleCardList((array) ($item['style_cards'] ?? [])),
        // Per-section style showcases for rings: engagement and wedding each keep
        // their own Shop-by-Style cards. A flat style_cards list stays the shared
        // fallback when a section has no cards of its own.
        'style_cards_sections' => [
            'engagement' => $cleanStyleCardList((array) ($item['style_cards_sections']['engagement'] ?? [])),
            'wedding' => $cleanStyleCardList((array) ($item['style_cards_sections']['wedding'] ?? [])),
        ],
        'diamond_intro_kicker' => clean_string((string) ($item['diamond_intro_kicker'] ?? ''), 80),
        'diamond_intro_text' => clean_multiline((string) ($item['diamond_intro_text'] ?? ''), 320),
        'diamond_inventory' => clean_items((array) ($item['diamond_inventory'] ?? []), 'clean_product_diamond_inventory_item'),
    ];
}

function clean_product_metal_variation_item(array $item, int $index = 0): array
{
    $active = clean_bool($item['active'] ?? false);
    $inventoryTracked = clean_bool($item['inventory_tracked'] ?? false);
    $inventoryQuantity = clean_int($item['inventory_quantity'] ?? 0, 0, 1000000);
    $metal = clean_string((string) ($item['metal'] ?? ''), 120);
    $priceRaw = preg_replace('/[^0-9.]/', '', (string) ($item['price'] ?? '0')) ?? '0';
    $price = round(max(0, (float) $priceRaw), 2);
    
    $oldPriceRaw = preg_replace('/[^0-9.]/', '', (string) ($item['old_price'] ?? ''));
    $oldPriceStr = '';
    if ($oldPriceRaw !== '' && $oldPriceRaw !== null) {
        $oldPriceStr = '£' . number_format(round(max(0, (float) $oldPriceRaw), 2), 2);
    }
    
    $shapes = array_values(array_filter(array_map('trim', (array)($item['shapes'] ?? []))));
    $sizes = array_values(array_filter(array_map('trim', (array)($item['sizes'] ?? []))));
    $image = clean_image((string) ($item['image'] ?? ''));
    
    $gallery = [];
    foreach ((array)($item['gallery'] ?? []) as $gImg) {
        $cln = clean_image((string)$gImg);
        if ($cln !== '') $gallery[] = $cln;
    }
    if ($image !== '' && empty($gallery)) {
        $gallery[] = $image;
    }
    
    $hoverImage = $gallery[1] ?? '';
    
    $description = clean_string((string)($item['description'] ?? ''), 1000);
    
    $features = [];
    if (isset($item['features_text']) && is_string($item['features_text'])) {
        $lines = explode("\n", str_replace("\r", '', $item['features_text']));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $features[] = clean_string($line, 200);
            }
        }
    } elseif (isset($item['features']) && is_array($item['features'])) {
        foreach ($item['features'] as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $features[] = clean_string($line, 200);
            }
        }
    }
    
    $bandOptions = [];
    foreach ((array)($item['band_options'] ?? []) as $band) {
        $bActive = clean_bool($band['active'] ?? false);
        if ($bActive) {
            $bLabel = clean_string((string) ($band['label'] ?? ''), 120);
            $bSurchargeRaw = preg_replace('/[^0-9.]/', '', (string) ($band['surcharge'] ?? '0')) ?? '0';
            $bSurcharge = round(max(0, (float) $bSurchargeRaw), 2);
            $bandOptions[] = [
                'active' => true,
                'label' => $bLabel,
                'surcharge' => $bSurcharge,
            ];
        }
    }
    
    return [
        'active' => $active,
        'inventory_tracked' => $inventoryTracked,
        'inventory_quantity' => $inventoryTracked ? $inventoryQuantity : 0,
        'metal' => $metal,
        'price' => $price,
        'old_price' => $oldPriceStr,
        'image' => $image,
        'hover_image' => $hoverImage,
        'gallery' => $gallery,
        'description' => $description,
        'features' => $features,
        'shapes' => $shapes,
        'sizes' => $sizes,
        'band_options' => $bandOptions,
    ];
}

function clean_product_diamond_inventory_item(array $item, int $index = 0): array
{
    $priceRaw = preg_replace('/[^0-9.]/', '', (string) ($item['price'] ?? '0')) ?? '0';
    $shape = clean_string((string) ($item['shape'] ?? ''), 40);
    $title = clean_string((string) ($item['title'] ?? ''), 140);
    $carat = clean_string((string) ($item['carat'] ?? ''), 20);
    $color = clean_string((string) ($item['color'] ?? ''), 20);
    $clarity = clean_string((string) ($item['clarity'] ?? ''), 20);
    $id = clean_string((string) ($item['id'] ?? ''), 80);
    if ($id === '') {
        $id = content_slug(trim($shape . '-' . $carat . '-' . $color . '-' . $clarity), 'diamond-' . ($index + 1));
    }
    return [
        'id' => $id,
        'shape' => $shape,
        'title' => $title,
        'image' => clean_string((string) ($item['image'] ?? ''), 2048) !== '' ? clean_image($item['image'] ?? '') : '',
        'carat' => $carat,
        'color' => $color,
        'clarity' => $clarity,
        'cut' => clean_string((string) ($item['cut'] ?? ''), 40),
        'ratio' => clean_string((string) ($item['ratio'] ?? ''), 40),
        'measurement' => clean_string((string) ($item['measurement'] ?? ''), 80),
        'ref' => clean_string((string) ($item['ref'] ?? ''), 80),
        'igi_certificate' => clean_string((string) ($item['igi_certificate'] ?? ''), 160),
        'price' => round(max(0, (float) $priceRaw), 2),
        'description' => clean_multiline((string) ($item['description'] ?? ''), 280),
        'badge' => clean_string((string) ($item['badge'] ?? ''), 40),
        'status' => clean_string((string) ($item['status'] ?? 'active'), 20),
    ];
}

function clean_product_library_item(array $item, int $index = 0): array
{
    $defaultImage = clean_image($item['default_image'] ?? ($item['image'] ?? ''));
    $inventoryTracked = clean_bool($item['inventory_tracked'] ?? false);
    $inventoryQuantity = clean_int($item['inventory_quantity'] ?? 0, 0, 1000000);
    
    // Clean and validate arrays
    $styles = array_values(array_filter(array_map('trim', (array)($item['styles'] ?? []))));
    $diamondShapes = array_values(array_filter(array_map('trim', (array)($item['diamondShapes'] ?? []))));
    $subcategories = clean_string_list((array) ($item['subcategories'] ?? []), 80);
    $features = clean_string_list((array) ($item['features'] ?? []), 160);
    $optionColors = clean_string_list((array) ($item['option_colors'] ?? []), 80);
    $optionSizes = clean_string_list((array) ($item['option_sizes'] ?? []), 80);
    $optionColorDisplay = clean_string((string) ($item['option_color_display'] ?? ''), 40);
    if (!in_array($optionColorDisplay, ['compact', 'jewellery-metals'], true)) {
        $optionColorDisplay = '';
    }
    $optionSizeDisplay = clean_string((string) ($item['option_size_display'] ?? ''), 40);
    if (!in_array($optionSizeDisplay, ['compact', 'stone-weights'], true)) {
        $optionSizeDisplay = '';
    }

    // Ring taxonomy: which ring section a product belongs to (Engagement / Wedding)
    // and, for wedding rings, who it is for (Men's / Women's). Whitelisted here so
    // the values survive every admin save, request apply, and Supabase sync.
    $ringCategory = strtolower(clean_string((string) ($item['ring_category'] ?? ''), 40));
    if (!in_array($ringCategory, ['engagement', 'wedding'], true)) {
        $ringCategory = '';
    }
    $ringGender = strtolower(clean_string((string) ($item['ring_gender'] ?? ''), 40));
    if (!in_array($ringGender, ['mens', 'womens'], true)) {
        $ringGender = '';
    }

    return [
        'id' => content_id('prd', $item, $index),
        'product_type' => infer_product_type($item),
        'color' => infer_product_color($item),
        'category' => clean_string($item['category'] ?? '', 120),
        'ring_category' => $ringCategory,
        'ring_gender' => $ringGender,
        'name' => clean_string($item['name'] ?? '', 120),
        'old_price' => clean_string($item['old_price'] ?? '', 50),
        'new_price' => clean_string($item['new_price'] ?? '', 50),
        'default_image' => $defaultImage,
        'hover_image' => clean_image($item['hover_image'] ?? $defaultImage),
        'popup_image' => clean_image($item['popup_image'] ?? $defaultImage),
        'description' => clean_multiline($item['description'] ?? '', 1000),
        'status' => clean_string($item['status'] ?? 'active', 40),
        'inventory_tracked' => $inventoryTracked,
        'inventory_quantity' => $inventoryTracked ? $inventoryQuantity : 0,
        'styles' => $styles,
        'diamondShapes' => $diamondShapes,
        'subcategories' => $subcategories,
        'features' => $features,
        'option_color_label' => clean_string((string) ($item['option_color_label'] ?? ''), 60),
        'option_size_label' => clean_string((string) ($item['option_size_label'] ?? ''), 60),
        'option_color_display' => $optionColorDisplay,
        'option_size_display' => $optionSizeDisplay,
        'option_colors' => $optionColors,
        'option_sizes' => $optionSizes,
        'metal_variations' => clean_items((array) ($item['metal_variations'] ?? []), 'clean_product_metal_variation_item'),
        'option_delivery_options' => clean_product_choice_list((array) ($item['option_delivery_options'] ?? []), 'option-delivery'),
    ];
}

function clean_customer_item(array $item, int $index = 0): array
{
    $wishlistIds = [];
    foreach (($item['wishlist_product_ids'] ?? []) as $wishlistId) {
        $cleanId = clean_string((string) $wishlistId, 80);
        if ($cleanId !== '' && !in_array($cleanId, $wishlistIds, true)) {
            $wishlistIds[] = $cleanId;
        }
    }

    $savedAddresses = clean_items($item['saved_addresses'] ?? [], static function (array $address, int $addressIndex): array {
        return [
            'id' => content_id('addr', $address, $addressIndex, 'label'),
            'label' => clean_string($address['label'] ?? '', 80),
            'recipient_name' => clean_string($address['recipient_name'] ?? '', 120),
            'phone' => clean_string($address['phone'] ?? '', 40),
            'address_line_1' => clean_multiline($address['address_line_1'] ?? '', 160),
            'address_line_2' => clean_multiline($address['address_line_2'] ?? '', 160),
            'city' => clean_string($address['city'] ?? '', 80),
            'state' => clean_string($address['state'] ?? '', 80),
            'postal_code' => clean_string($address['postal_code'] ?? '', 20),
            'country' => clean_string($address['country'] ?? '', 80),
        ];
    });

    return [
        'id' => content_id('cus', $item, $index),
        'name' => clean_string($item['name'] ?? '', 120),
        'email' => clean_string($item['email'] ?? '', 120),
        'password_hash' => clean_string($item['password_hash'] ?? '', 255),
        'phone' => clean_string($item['phone'] ?? '', 40),
        'city' => clean_string($item['city'] ?? '', 80),
        'state' => clean_string($item['state'] ?? '', 80),
        'country' => clean_string($item['country'] ?? '', 80),
        'postal_code' => clean_string($item['postal_code'] ?? '', 20),
        'address_line_1' => clean_multiline($item['address_line_1'] ?? '', 160),
        'address_line_2' => clean_multiline($item['address_line_2'] ?? '', 160),
        'status' => clean_string($item['status'] ?? 'active', 40),
        'joined_at' => clean_string($item['joined_at'] ?? '', 40),
        'last_order_at' => clean_string($item['last_order_at'] ?? '', 40),
        'total_orders' => clean_string($item['total_orders'] ?? '', 20),
        'total_spent' => clean_string($item['total_spent'] ?? '', 40),
        'wishlist_product_ids' => $wishlistIds,
        'saved_addresses' => $savedAddresses,
        'notes' => clean_multiline($item['notes'] ?? '', 500),
    ];
}

function clean_newsletter_subscriber_item(array $item, int $index = 0): array
{
    return [
        'id' => content_id('nls', $item, $index, 'subscribed_email'),
        'account_customer_id' => clean_string($item['account_customer_id'] ?? '', 80),
        'account_holder_name' => clean_string($item['account_holder_name'] ?? '', 120),
        'account_holder_email' => clean_string($item['account_holder_email'] ?? '', 120),
        'subscribed_email' => clean_string($item['subscribed_email'] ?? '', 120),
        'source' => clean_string($item['source'] ?? 'guest', 40),
        'status' => clean_string($item['status'] ?? 'active', 20),
        'subscribed_at' => clean_string($item['subscribed_at'] ?? '', 40),
        'updated_at' => clean_string($item['updated_at'] ?? '', 40),
    ];
}

function clean_order_line_item(array $item, int $index = 0): array
{
    return [
        'id' => content_id('line', $item, $index, 'product_name'),
        'product_id' => clean_string($item['product_id'] ?? '', 80),
        'product_name' => clean_string($item['product_name'] ?? '', 140),
        'image' => clean_image($item['image'] ?? ''),
        'quantity' => clean_int($item['quantity'] ?? 1, 1, 99),
        'size' => clean_string($item['size'] ?? '', 40),
        'color' => clean_string($item['color'] ?? '', 80),
        'diamond_shape' => clean_string($item['diamond_shape'] ?? '', 40),
        'diamond_shape_label' => clean_string($item['diamond_shape_label'] ?? '', 80),
        'diamond_id' => clean_string($item['diamond_id'] ?? '', 80),
        'diamond_title' => clean_string($item['diamond_title'] ?? '', 140),
        'diamond_price' => clean_string($item['diamond_price'] ?? '', 40),
        'metal' => clean_string($item['metal'] ?? '', 80),
        'metal_label' => clean_string($item['metal_label'] ?? '', 80),
        'band_claw_metal' => clean_string($item['band_claw_metal'] ?? '', 80),
        'band_claw_metal_label' => clean_string($item['band_claw_metal_label'] ?? '', 80),
        'delivery_option' => clean_string($item['delivery_option'] ?? '', 40),
        'delivery_label' => clean_string($item['delivery_label'] ?? '', 80),
        'delivery_surcharge' => clean_string($item['delivery_surcharge'] ?? '', 40),
        'price' => clean_string($item['price'] ?? '', 40),
        'base_price' => clean_string($item['base_price'] ?? '', 40),
        'line_total' => clean_string($item['line_total'] ?? '', 40),
    ];
}

function clean_order_item(array $item, int $index = 0): array
{
    return [
        'id' => content_id('ord', $item, $index, 'customer_name'),
        'customer_name' => clean_string($item['customer_name'] ?? '', 120),
        'customer_email' => clean_string($item['customer_email'] ?? '', 120),
        'status' => clean_string($item['status'] ?? 'pending', 40),
        'payment_method' => clean_string($item['payment_method'] ?? 'online', 40),
        'payment_status' => clean_string($item['payment_status'] ?? 'awaiting', 40),
        'payment_reference' => clean_string($item['payment_reference'] ?? '', 120),
        'stripe_checkout_session_id' => clean_string($item['stripe_checkout_session_id'] ?? '', 120),
        'stripe_payment_intent_id' => clean_string($item['stripe_payment_intent_id'] ?? '', 120),
        'total' => clean_string($item['total'] ?? '', 40),
        'subtotal' => clean_string($item['subtotal'] ?? '', 40),
        'discount_amount' => clean_string($item['discount_amount'] ?? '', 40),
        'shipping_amount' => clean_string($item['shipping_amount'] ?? '', 40),
        'coupon_code' => clean_string($item['coupon_code'] ?? '', 40),
        'item_count' => clean_string($item['item_count'] ?? '', 20),
        'placed_at' => clean_string($item['placed_at'] ?? '', 40),
        'customer_phone' => clean_string($item['customer_phone'] ?? '', 40),
        'customer_request_type' => clean_string($item['customer_request_type'] ?? '', 20),
        'customer_request_status' => clean_string($item['customer_request_status'] ?? '', 20),
        'customer_request_reason' => clean_multiline($item['customer_request_reason'] ?? '', 500),
        'customer_request_requested_at' => clean_string($item['customer_request_requested_at'] ?? '', 40),
        'customer_request_resolved_at' => clean_string($item['customer_request_resolved_at'] ?? '', 40),
        'shipping_address' => [
            'address_line_1' => clean_multiline($item['shipping_address']['address_line_1'] ?? '', 160),
            'address_line_2' => clean_multiline($item['shipping_address']['address_line_2'] ?? '', 160),
            'city' => clean_string($item['shipping_address']['city'] ?? '', 80),
            'state' => clean_string($item['shipping_address']['state'] ?? '', 80),
            'postal_code' => clean_string($item['shipping_address']['postal_code'] ?? '', 20),
            'country' => clean_string($item['shipping_address']['country'] ?? '', 80),
        ],
        'items' => clean_items($item['items'] ?? [], 'clean_order_line_item'),
        'notes' => clean_multiline($item['notes'] ?? '', 500),
    ];
}

function clean_news_item(array $item, int $index = 0): array
{
    $body = clean_rich_text($item['body'] ?? ($item['excerpt'] ?? ''), 12000);
    $excerpt = rich_text_excerpt($body, 240);
    if ($excerpt === '') {
        $excerpt = clean_string($item['excerpt'] ?? '', 500);
    }

    return [
        'id' => content_id('news', $item, $index, 'title'),
        'title' => clean_string($item['title'] ?? '', 120),
        'author' => clean_string($item['author'] ?? '', 80),
        'date' => clean_string($item['date'] ?? '', 40),
        'excerpt' => $excerpt,
        'body' => $body,
        'url' => clean_link($item['url'] ?? '#'),
        'image' => clean_image($item['image'] ?? ''),
        'alt' => clean_string($item['alt'] ?? '', 120),
    ];
}

function clean_coupon_item(array $item, int $index = 0): array
{
    $type = clean_string($item['type'] ?? 'percent', 20);
    $value = preg_replace('/[^0-9.]/', '', (string) ($item['value'] ?? '')) ?? '';
    $minOrder = clean_string($item['min_order'] ?? '', 20);
    $applyLabel = clean_string($item['apply_label'] ?? '', 120);
    if ($applyLabel === '' && $value !== '') {
        $applyLabel = ($type === 'fixed' ? '£' . $value . ' off' : $value . '% off') . ($minOrder !== '' ? ' above ' . $minOrder : '');
    }

    return [
        'id' => content_id('coupon', $item, $index, 'code'),
        'code' => strtoupper(clean_string($item['code'] ?? '', 40)),
        'type' => in_array($type, ['percent', 'fixed'], true) ? $type : 'percent',
        'value' => $value,
        'min_order' => $minOrder,
        'usage_limit' => clean_string($item['usage_limit'] ?? '', 20),
        'expires_at' => clean_string($item['expires_at'] ?? '', 30),
        'status' => clean_string($item['status'] ?? 'active', 20),
        'description' => clean_multiline($item['description'] ?? '', 300),
        'apply_label' => $applyLabel,
    ];
}

function clean_nav_item(array $item): array
{
    $columns = clean_items($item['columns'] ?? [], static function (array $column): array {
        return [
            'title' => clean_string($column['title'] ?? '', 120),
            'links' => clean_items($column['links'] ?? [], 'clean_link_item'),
        ];
    });

    return [
        'label' => clean_string($item['label'] ?? '', 120),
        'url' => clean_link($item['url'] ?? '#'),
        'active' => clean_bool($item['active'] ?? false),
        'compact' => clean_bool($item['compact'] ?? false),
        'columns' => $columns,
        'feature' => [
            'image' => clean_image($item['feature']['image'] ?? ''),
            'alt' => clean_string($item['feature']['alt'] ?? '', 120),
            'title' => clean_string($item['feature']['title'] ?? '', 120),
            'subtitle' => clean_multiline($item['feature']['subtitle'] ?? '', 300),
        ],
    ];
}

function normalize_catalog(array $candidate, array $defaults): array
{
    $librarySource = $candidate['products']['items'] ?? [];
    if (!is_array($librarySource) || $librarySource === []) {
        $librarySource = $defaults['products']['items'] ?? [];
    }

    $library = clean_items(is_array($librarySource) ? $librarySource : [], 'clean_product_library_item');
    $libraryById = [];
    foreach ($library as $product) {
        $libraryById[$product['id']] = $product;
    }

    $nextIndex = count($libraryById);
    $appendProduct = static function (array $raw) use (&$libraryById, &$nextIndex): string {
        $product = clean_product_library_item($raw, $nextIndex);
        while (isset($libraryById[$product['id']])) {
            $nextIndex++;
            $product['id'] = content_id('prd', ['name' => ($raw['name'] ?? 'product') . '-' . $nextIndex], $nextIndex);
        }
        $libraryById[$product['id']] = $product;
        $nextIndex++;
        return $product['id'];
    };

    $defaultTabs = $defaults['product_tabs']['tabs'] ?? [];
    $tabsSource = $candidate['product_tabs']['tabs'] ?? $defaultTabs;
    if (!is_array($tabsSource)) {
        $tabsSource = $defaultTabs;
    }

    $tabs = [];
    foreach ($tabsSource as $tabIndex => $tab) {
        if (!is_array($tab)) {
            continue;
        }
        $key = preg_replace('/[^a-z0-9\-]/i', '', clean_string($tab['key'] ?? '', 40)) ?? '';
        $ids = [];
        if (isset($tab['product_ids']) && is_array($tab['product_ids'])) {
            $ids = clean_select_ids($tab['product_ids'], array_keys($libraryById));
        } elseif (isset($tab['products']) && is_array($tab['products'])) {
            foreach ($tab['products'] as $product) {
                if (is_array($product)) {
                    $ids[] = $appendProduct($product);
                }
            }
        }

        $tabs[] = [
            'key' => strtolower($key !== '' ? $key : 'tab-' . ($tabIndex + 1)),
            'label' => clean_string($tab['label'] ?? '', 80),
            'product_ids' => $ids,
        ];
    }

    $bestsellingSource = $candidate['bestselling'] ?? ($defaults['bestselling'] ?? []);
    $bestsellingIds = [];
    if (isset($bestsellingSource['product_ids']) && is_array($bestsellingSource['product_ids'])) {
        $bestsellingIds = clean_select_ids($bestsellingSource['product_ids'], array_keys($libraryById));
    } elseif (isset($bestsellingSource['products']) && is_array($bestsellingSource['products'])) {
        foreach ($bestsellingSource['products'] as $product) {
            if (is_array($product)) {
                $bestsellingIds[] = $appendProduct($product);
            }
        }
    }

    $styleShowcaseSource = is_array($candidate['shop_by_style'] ?? null)
        ? $candidate['shop_by_style']
        : (is_array($defaults['shop_by_style'] ?? null) ? $defaults['shop_by_style'] : []);
    $styleShowcaseIds = [];
    foreach ((array) ($styleShowcaseSource['style_ids'] ?? []) as $styleId) {
        $cleanStyleId = clean_string((string) $styleId, 120);
        if ($cleanStyleId !== '' && !in_array($cleanStyleId, $styleShowcaseIds, true)) {
            $styleShowcaseIds[] = $cleanStyleId;
        }
    }

    return [
        'products' => [
            'title' => clean_string($candidate['products']['title'] ?? ($defaults['products']['title'] ?? 'Product Library'), 120),
            'items' => array_values($libraryById),
        ],
        'product_tabs' => [
            'tabs' => $tabs,
        ],
        'bestselling' => [
            'title' => clean_string($bestsellingSource['title'] ?? ($defaults['bestselling']['title'] ?? 'Bestselling Products'), 120),
            'product_ids' => $bestsellingIds,
        ],
        'shop_by_style' => [
            'title' => clean_string($styleShowcaseSource['title'] ?? 'Shop by Style', 120),
            'style_ids' => $styleShowcaseIds,
        ],
    ];
}

/**
 * Singular/plural spellings that mean the same catalogue type, so "Earrings"
 * on a category card and "Earring" on a product resolve to one profile.
 */
function catalog_type_aliases(): array
{
    return [
        'rings' => 'Ring', 'ring' => 'Ring',
        'engagement rings' => 'Ring', 'wedding rings' => 'Ring',
        'earrings' => 'Earring', 'earring' => 'Earring',
        'bracelets' => 'Bracelet', 'bracelet' => 'Bracelet',
        'bangles & bracelets' => 'Bracelet', 'bangles &amp; bracelets' => 'Bracelet',
        'necklaces' => 'Necklace', 'necklace' => 'Necklace',
        'pendants' => 'Pendant', 'pendant' => 'Pendant',
        'brooches' => 'Brooch', 'brooch' => 'Brooch',
        'jewellery sets' => 'Jewellery Set', 'jewellery set' => 'Jewellery Set',
        'mangalsutra' => 'Mangalsutra',
    ];
}

function catalog_canonical_type(string $type): string
{
    return catalog_type_aliases()[strtolower(trim($type))] ?? trim($type);
}

/**
 * Category card titles that describe a ring *section* rather than a standalone
 * catalogue type — these resolve through the structural ring taxonomy instead
 * of becoming their own category entry.
 */
function catalog_ring_section_titles(): array
{
    return ['engagement rings', 'wedding rings', "women's wedding rings", "men's wedding rings", 'ring', 'rings'];
}

/**
 * The catalogue types the merchant actually has: the categories they created
 * plus any type already in use by a product. Deliberately does NOT read
 * catalog_meta.product_types — that list has no admin editor and was only ever
 * seeded from the demo defaults, which is how fake categories reached the
 * product form. Falls back to a single neutral 'Ring' so a fresh install still
 * has a usable product form.
 */
function catalog_active_product_types(?array $content = null): array
{
    $content = $content ?? site_content();
    $ringSectionTitles = catalog_ring_section_titles();
    $types = [];

    foreach ((array) ($content['category_cards'] ?? []) as $card) {
        if (!is_array($card)) {
            continue;
        }
        $title = trim((string) ($card['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        // A ring-section card ("Rings", "Engagement Rings", …) is not its own
        // catalogue type — every ring section shares the single Ring profile.
        $types[] = in_array(strtolower($title), $ringSectionTitles, true) ? 'Ring' : catalog_canonical_type($title);
    }

    foreach ((array) ($content['products']['items'] ?? []) as $product) {
        if (!is_array($product)) {
            continue;
        }
        $type = clean_string((string) ($product['product_type'] ?? ''), 80);
        if ($type !== '') {
            $types[] = catalog_canonical_type($type);
        }
    }

    $types = array_values(array_unique(array_filter($types, static fn (string $type): bool => $type !== '')));

    return $types !== [] ? $types : ['Ring'];
}

/**
 * Blank every image URL that still points at the upstream HTML demo, so no
 * placeholder imagery survives the clean-slate migration. Runs once, gated on
 * content_schema_version.
 */
function content_strip_demo_images(array $candidate): array
{
    array_walk_recursive($candidate, static function (mixed &$value, string|int $key): void {
        if (is_string($value) && str_contains($value, 'htmldemo.net')) {
            $value = '';
        }
    });

    return $candidate;
}

function normalize_site_content(array $candidate): array
{
    $defaults = default_site_content();

    // One-time clean slate. Before schema 2 the store accumulated the factory
    // demo attribute profiles, because this function used to array_merge them
    // under whatever was saved and then persist the result — so demo colours,
    // sizes, metals and style cards reappeared no matter what the merchant did.
    // Drop the whole stored set once and let the merchant rebuild the profiles
    // for their real categories through the Attributes studio.
    if ((int) ($candidate['content_schema_version'] ?? 1) < 2) {
        unset($candidate['catalog_meta']['attribute_profiles'], $candidate['catalog_meta']['product_types']);
        $candidate = content_strip_demo_images($candidate);
    }

    $catalog = normalize_catalog($candidate, $defaults);
    $profileSource = is_array($candidate['catalog_meta']['attribute_profiles'] ?? null) ? $candidate['catalog_meta']['attribute_profiles'] : [];
    $activeProductTypes = catalog_active_product_types($candidate);
    // Stored profiles are authoritative — the factory defaults are a first-run
    // seed only and must never be merged underneath saved data.
    $profileTypes = array_values(array_unique(array_merge(
        array_keys($profileSource),
        $activeProductTypes
    )));
    $attributeProfiles = [];
    foreach ($profileTypes as $profileType) {
        if ($profileType === '') {
            continue;
        }
        $baseProfile = ['type' => $profileType];
        $overrideProfile = is_array($profileSource[$profileType] ?? null) ? $profileSource[$profileType] : [];
        $attributeProfiles[$profileType] = clean_attribute_profile_item(array_merge($baseProfile, $overrideProfile), $profileType);
    }

    $settingsInput = is_array($candidate['settings'] ?? null) ? $candidate['settings'] : [];
    $settingsDefault = $defaults['settings'];
    $heroDefaultImage = $defaults['hero']['image'] ?? '';

    return [
        'content_schema_version' => 2,
        'settings' => [
            'site_name' => clean_string($settingsInput['site_name'] ?? $settingsDefault['site_name'], 120),
            'site_tagline' => clean_string($settingsInput['site_tagline'] ?? $settingsDefault['site_tagline'], 160),
            'site_url' => clean_string($settingsInput['site_url'] ?? $settingsDefault['site_url'], 255),
            'logo_path' => clean_image($settingsInput['logo_path'] ?? $settingsDefault['logo_path']),
            'store_address' => clean_multiline($settingsInput['store_address'] ?? $settingsDefault['store_address'], 300),
            'store_phone' => clean_string($settingsInput['store_phone'] ?? $settingsDefault['store_phone'], 40),
            'store_email' => clean_string($settingsInput['store_email'] ?? $settingsDefault['store_email'], 120),
            'announcement_text' => clean_string($settingsInput['announcement_text'] ?? $settingsDefault['announcement_text'], 200),
            'announcement_code' => clean_string($settingsInput['announcement_code'] ?? $settingsDefault['announcement_code'], 40),
            'announcement_url' => clean_link($settingsInput['announcement_url'] ?? $settingsDefault['announcement_url']),
            'cart_count' => clean_int($settingsInput['cart_count'] ?? $settingsDefault['cart_count'], 0, 999),
            'cart_total' => clean_string($settingsInput['cart_total'] ?? $settingsDefault['cart_total'], 40),
            'social' => [
                'facebook' => clean_link($settingsInput['social']['facebook'] ?? $settingsDefault['social']['facebook']),
                'twitter' => clean_link($settingsInput['social']['twitter'] ?? $settingsDefault['social']['twitter']),
                'rss' => clean_link($settingsInput['social']['rss'] ?? $settingsDefault['social']['rss']),
                'googleplus' => clean_link($settingsInput['social']['googleplus'] ?? $settingsDefault['social']['googleplus']),
                'youtube' => clean_link($settingsInput['social']['youtube'] ?? $settingsDefault['social']['youtube']),
            ],
        ],
        'hero' => [
            'offer' => clean_string($candidate['hero']['offer'] ?? $defaults['hero']['offer'], 160),
            'title' => clean_string($candidate['hero']['title'] ?? $defaults['hero']['title'], 160),
            'price_prefix' => clean_string($candidate['hero']['price_prefix'] ?? $defaults['hero']['price_prefix'], 80),
            'price_value' => clean_string($candidate['hero']['price_value'] ?? $defaults['hero']['price_value'], 60),
            'cta_label' => clean_string($candidate['hero']['cta_label'] ?? $defaults['hero']['cta_label'], 60),
            'cta_url' => clean_link($candidate['hero']['cta_url'] ?? $defaults['hero']['cta_url']),
            'image' => clean_image($candidate['hero']['image'] ?? $heroDefaultImage),
        ],
        'category_cards' => clean_items($candidate['category_cards'] ?? $defaults['category_cards'], static function (array $item): array {
            return [
                'header_label' => clean_string($item['header_label'] ?? '', 80),
                'header_icon' => clean_icon($item['header_icon'] ?? ''),
                'sub' => clean_string($item['sub'] ?? '', 80),
                'title' => clean_string($item['title'] ?? '', 120),
                'price' => clean_string($item['price'] ?? '', 120),
                'url' => clean_link($item['url'] ?? '#'),
                'image' => clean_image($item['image'] ?? ''),
                'alt' => clean_string($item['alt'] ?? '', 120),
            ];
        }),
        'catalog_meta' => [
            'product_types' => $activeProductTypes,
            'colors' => array_values(array_filter(array_map(static fn ($item): string => clean_string((string) $item, 80), $candidate['catalog_meta']['colors'] ?? $defaults['catalog_meta']['colors'] ?? []), static fn (string $item): bool => $item !== '')),
            'attribute_profiles' => $attributeProfiles,
        ],
        'navigation' => [
            'items' => clean_items($candidate['navigation']['items'] ?? $defaults['navigation']['items'], 'clean_nav_item'),
        ],
        'products' => $catalog['products'],
        'product_tabs' => $catalog['product_tabs'],
        'shop_by_style' => $catalog['shop_by_style'],
        'trending' => [
            'sale' => clean_string($candidate['trending']['sale'] ?? $defaults['trending']['sale'], 120),
            'title' => clean_string($candidate['trending']['title'] ?? $defaults['trending']['title'], 160),
            'subtitle' => clean_string($candidate['trending']['subtitle'] ?? $defaults['trending']['subtitle'], 240),
            'cta_label' => clean_string($candidate['trending']['cta_label'] ?? $defaults['trending']['cta_label'], 60),
            'cta_url' => clean_link($candidate['trending']['cta_url'] ?? $defaults['trending']['cta_url']),
        ],
        'diamond_shapes' => [
            'title' => clean_string($candidate['diamond_shapes']['title'] ?? $defaults['diamond_shapes']['title'], 120),
            'items' => clean_items($candidate['diamond_shapes']['items'] ?? $defaults['diamond_shapes']['items'], static function (array $item, int $index): array {
                $image = clean_image($item['image'] ?? ($item['icon_image'] ?? ''));
                $tones = ['classic', 'graceful', 'romantic', 'modern', 'refined', 'poetic', 'regal', 'bold', 'romantic', 'deco'];
                $accents = ['#c6b590', '#bfae8a', '#c3b086', '#b8a57d', '#afa07e', '#c8b68d', '#b39f77', '#c2ae84', '#d0bf96', '#baa77f'];
                return [
                    'name' => clean_string($item['name'] ?? '', 60),
                    'label' => clean_string($item['label'] ?? '', 120),
                    'description' => clean_multiline($item['description'] ?? '', 360),
                    'image' => $image,
                    'url' => clean_link($item['url'] ?? '#'),
                    'icon_image' => clean_image($item['icon_image'] ?? $image),
                    'accent' => clean_color($item['accent'] ?? ($accents[$index] ?? '#b18861')),
                    'tone' => clean_tone($item['tone'] ?? ($tones[$index] ?? 'classic')),
                ];
            }),
        ],
        'bestselling' => $catalog['bestselling'],
        'celebs' => [
            'title' => clean_string($candidate['celebs']['title'] ?? $defaults['celebs']['title'], 120),
            'items' => clean_items($candidate['celebs']['items'] ?? $defaults['celebs']['items'], static function (array $item): array {
                return [
                    'name' => clean_string($item['name'] ?? '', 120),
                    'image' => clean_image($item['image'] ?? ''),
                ];
            }),
        ],
        'reviews' => [
            'eyebrow' => clean_string($candidate['reviews']['eyebrow'] ?? $defaults['reviews']['eyebrow'], 80),
            'title' => clean_string($candidate['reviews']['title'] ?? $defaults['reviews']['title'], 120),
            'intro' => clean_multiline($candidate['reviews']['intro'] ?? $defaults['reviews']['intro'], 320),
            'rating_value' => clean_string($candidate['reviews']['rating_value'] ?? $defaults['reviews']['rating_value'], 20),
            'rating_label' => clean_string($candidate['reviews']['rating_label'] ?? $defaults['reviews']['rating_label'], 120),
            'reviews_count' => clean_string($candidate['reviews']['reviews_count'] ?? $defaults['reviews']['reviews_count'], 120),
            'items' => clean_items($candidate['reviews']['items'] ?? $defaults['reviews']['items'], static function (array $item): array {
                return [
                    'rating' => clean_int($item['rating'] ?? 5, 1, 5),
                    'title' => clean_string($item['title'] ?? '', 120),
                    'excerpt' => clean_multiline($item['excerpt'] ?? '', 320),
                    'author' => clean_string($item['author'] ?? '', 120),
                    'meta' => clean_string($item['meta'] ?? '', 120),
                    'verified' => clean_bool($item['verified'] ?? true),
                ];
            }),
        ],
        'news' => [
            'title' => clean_string($candidate['news']['title'] ?? $defaults['news']['title'], 120),
            'items' => clean_items($candidate['news']['items'] ?? $defaults['news']['items'], 'clean_news_item'),
        ],
        'newsletter' => [
            'title' => clean_string($candidate['newsletter']['title'] ?? $defaults['newsletter']['title'], 120),
            'subtitle' => clean_multiline($candidate['newsletter']['subtitle'] ?? $defaults['newsletter']['subtitle'], 300),
            'placeholder' => clean_string($candidate['newsletter']['placeholder'] ?? $defaults['newsletter']['placeholder'], 80),
            'button_label' => clean_string($candidate['newsletter']['button_label'] ?? $defaults['newsletter']['button_label'], 60),
            'image' => clean_string($candidate['newsletter']['image'] ?? $defaults['newsletter']['image'] ?? '', 300),
            'subscribers' => clean_items($candidate['newsletter']['subscribers'] ?? $defaults['newsletter']['subscribers'], 'clean_newsletter_subscriber_item'),
        ],
        'customers' => [
            'title' => clean_string($candidate['customers']['title'] ?? $defaults['customers']['title'], 120),
            'items' => clean_items($candidate['customers']['items'] ?? $defaults['customers']['items'], 'clean_customer_item'),
        ],
        'orders' => [
            'title' => clean_string($candidate['orders']['title'] ?? $defaults['orders']['title'], 120),
            'items' => clean_items($candidate['orders']['items'] ?? $defaults['orders']['items'], 'clean_order_item'),
        ],
        'coupons' => [
            'title' => clean_string($candidate['coupons']['title'] ?? ($defaults['coupons']['title'] ?? 'Coupons'), 120),
            'items' => clean_items($candidate['coupons']['items'] ?? ($defaults['coupons']['items'] ?? []), 'clean_coupon_item'),
        ],
        'footer' => [
            'information_title' => clean_string($candidate['footer']['information_title'] ?? $defaults['footer']['information_title'], 80),
            'information_links' => clean_items($candidate['footer']['information_links'] ?? $defaults['footer']['information_links'], 'clean_link_item'),
            'account_title' => clean_string($candidate['footer']['account_title'] ?? $defaults['footer']['account_title'], 80),
            'account_links' => clean_items($candidate['footer']['account_links'] ?? $defaults['footer']['account_links'], 'clean_link_item'),
            'top_rated_title' => clean_string($candidate['footer']['top_rated_title'] ?? $defaults['footer']['top_rated_title'], 80),
            'top_rated_products' => clean_items($candidate['footer']['top_rated_products'] ?? $defaults['footer']['top_rated_products'], static function (array $item): array {
                return [
                    'category' => clean_string($item['category'] ?? '', 120),
                    'name' => clean_string($item['name'] ?? '', 120),
                    'old_price' => clean_string($item['old_price'] ?? '', 40),
                    'new_price' => clean_string($item['new_price'] ?? '', 40),
                    'image' => clean_image($item['image'] ?? ''),
                ];
            }),
            'bottom_links' => clean_items($candidate['footer']['bottom_links'] ?? $defaults['footer']['bottom_links'], 'clean_link_item'),
            'copyright_year' => clean_string($candidate['footer']['copyright_year'] ?? $defaults['footer']['copyright_year'], 8),
            'copyright_brand' => clean_string($candidate['footer']['copyright_brand'] ?? $defaults['footer']['copyright_brand'], 80),
            'copyright_author' => clean_string($candidate['footer']['copyright_author'] ?? $defaults['footer']['copyright_author'], 120),
            'payment_image' => clean_image($candidate['footer']['payment_image'] ?? $defaults['footer']['payment_image']),
            'payment_alt' => clean_string($candidate['footer']['payment_alt'] ?? $defaults['footer']['payment_alt'], 120),
        ],
        'social_gallery' => [
            'title' => clean_string($candidate['social_gallery']['title'] ?? ($defaults['social_gallery']['title'] ?? 'Say "Yes" with Azuronn'), 120),
            'items' => clean_items($candidate['social_gallery']['items'] ?? ($defaults['social_gallery']['items'] ?? []), 'clean_social_item'),
        ],
        'faq' => [
            'kicker' => clean_string($candidate['faq']['kicker'] ?? ($defaults['faq']['kicker'] ?? 'FREQUENTLY ASKED QUESTIONS'), 120),
            'title' => clean_string($candidate['faq']['title'] ?? ($defaults['faq']['title'] ?? 'Everything you need to know before getting started'), 120),
            'support_image' => clean_image($candidate['faq']['support_image'] ?? ($defaults['faq']['support_image'] ?? '')),
            'support_title' => clean_string($candidate['faq']['support_title'] ?? ($defaults['faq']['support_title'] ?? 'Customer Support'), 120),
            'support_text' => clean_string($candidate['faq']['support_text'] ?? ($defaults['faq']['support_text'] ?? 'Do you have additional questions? No problem, let us help you through the process'), 200),
            'support_btn_label' => clean_string($candidate['faq']['support_btn_label'] ?? ($defaults['faq']['support_btn_label'] ?? 'BOOK ONLINE'), 60),
            'support_btn_url' => clean_link($candidate['faq']['support_btn_url'] ?? ($defaults['faq']['support_btn_url'] ?? '#')),
            'items' => clean_items($candidate['faq']['items'] ?? ($defaults['faq']['items'] ?? []), 'clean_faq_item'),
        ],
    ];
}

function load_site_content(bool $refresh = false): array
{
    $cache = $GLOBALS['azuronn_site_content_cache'] ?? null;

    if (!$refresh && is_array($cache)) {
        return $cache;
    }

    $defaults = default_site_content();
    $localCandidate = local_site_content_candidate($defaults);

    if (supabase_enabled()) {
        $remotePayload = supabase_read_state('site_content');
        if (is_array($remotePayload)) {
            $GLOBALS['azuronn_site_content_cache'] = normalize_site_content($remotePayload);
            return $GLOBALS['azuronn_site_content_cache'];
        }

        $seed = normalize_site_content($localCandidate);
        if (supabase_write_state('site_content', $seed)) {
            $GLOBALS['azuronn_site_content_cache'] = $seed;
            return $GLOBALS['azuronn_site_content_cache'];
        }
    }

    $GLOBALS['azuronn_site_content_cache'] = normalize_site_content($localCandidate);
    if (!is_file(content_file_path())) {
        local_save_site_content($GLOBALS['azuronn_site_content_cache']);
    }
    return $GLOBALS['azuronn_site_content_cache'];
}

function save_site_content(array $content): void
{
    $normalized = normalize_site_content($content);
    $savedRemotely = false;

    if (supabase_enabled()) {
        $savedRemotely = supabase_write_state('site_content', $normalized);
    }

    if (!$savedRemotely) {
        local_save_site_content($normalized);
    }

    $GLOBALS['azuronn_site_content_cache'] = $normalized;
}

function site_content(): array
{
    return load_site_content();
}

function catalog_attribute_profile(string $type, ?array $content = null): array
{
    $type = clean_string($type, 80);
    $content = $content ?? site_content();
    $profiles = is_array($content['catalog_meta']['attribute_profiles'] ?? null) ? $content['catalog_meta']['attribute_profiles'] : [];

    // Products store product_type as "Rings" (plural) while the attribute profile
    // is keyed "Ring" (singular) — and the same singular/plural drift exists for
    // every category. Without this normalization the exact-string match below
    // silently misses, so prices, per-section style cards, and any setting saved
    // in the Attributes studio never reach the product page. Collapse known
    // aliases on BOTH sides so "Ring" and "Rings" resolve to the same profile.
    $typeKey = strtolower(catalog_canonical_type($type));

    foreach ($profiles as $profileType => $profile) {
        if (!is_array($profile) || strtolower(catalog_canonical_type((string) $profileType)) !== $typeKey) {
            continue;
        }
        return clean_attribute_profile_item($profile, (string) $profileType);
    }

    // No stored profile: return a structurally complete but empty profile keyed
    // to the requested type. Never fall back to the demo Ring profile — that is
    // what made a merchant-created category silently inherit demo colours,
    // sizes, metals and delivery options.
    $fallbackType = $type !== '' ? $type : 'Ring';

    return clean_attribute_profile_item(['type' => $fallbackType], $fallbackType);
}

function clean_social_item(array $item): array
{
    return [
        'image' => clean_image($item['image'] ?? ''),
        'username' => clean_string($item['username'] ?? '', 80),
        'alt' => clean_string($item['alt'] ?? '', 120),
    ];
}

function clean_faq_item(array $item): array
{
    return [
        'question' => clean_string($item['question'] ?? '', 200),
        'answer' => clean_string($item['answer'] ?? '', 2000),
    ];
}
