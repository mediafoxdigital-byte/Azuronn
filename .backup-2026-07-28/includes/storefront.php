<?php

declare(strict_types=1);

function site_flash_set(string $type, string $message): void
{
    $_SESSION['site_flash'] = [
        'type' => clean_string($type, 20),
        'message' => clean_string($message, 240),
    ];
}

function site_flash_pull(): ?array
{
    $flash = $_SESSION['site_flash'] ?? null;
    unset($_SESSION['site_flash']);
    return is_array($flash) ? $flash : null;
}

function safe_internal_path(string $candidate, string $fallback = '/'): string
{
    $candidate = clean_link($candidate);
    if (preg_match('~^(?:https?:)?//~i', $candidate) || preg_match('~^(mailto:|tel:)~i', $candidate)) {
        return $fallback;
    }
    return $candidate !== '#' ? $candidate : $fallback;
}

function money_value(string $value): float
{
    $normalized = preg_replace('/[^0-9.]/', '', $value) ?? '0';
    return (float) $normalized;
}

function money_format(float $value): string
{
    return '£' . number_format(max(0, $value), 2);
}

function current_customer(): ?array
{
    $customerId = clean_string($_SESSION['customer_auth']['customer_id'] ?? '', 80);
    if ($customerId === '') {
        return null;
    }

    $fingerprint = clean_string($_SESSION['customer_auth']['fingerprint'] ?? '', 128);
    $currentFingerprint = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($fingerprint !== '' && !hash_equals($fingerprint, $currentFingerprint)) {
        unset($_SESSION['customer_auth']);
        return null;
    }

    foreach (site_content()['customers']['items'] ?? [] as $customer) {
        if ((string) ($customer['id'] ?? '') === $customerId) {
            if (strtolower((string) ($customer['status'] ?? 'active')) !== 'active') {
                unset($_SESSION['customer_auth']);
                return null;
            }
            return $customer;
        }
    }

    unset($_SESSION['customer_auth']);
    return null;
}

function customer_is_logged_in(): bool
{
    return current_customer() !== null;
}

function current_internal_url(string $fallback = '/'): string
{
    return safe_internal_path((string) ($_SERVER['REQUEST_URI'] ?? $fallback), $fallback);
}

function customer_by_email(string $email): ?array
{
    $email = strtolower(sanitize_email($email));
    if ($email === '') {
        return null;
    }

    foreach (site_content()['customers']['items'] ?? [] as $customer) {
        if (strtolower((string) ($customer['email'] ?? '')) === $email) {
            return $customer;
        }
    }

    return null;
}

function newsletter_subscribers(): array
{
    return array_values(array_filter(site_content()['newsletter']['subscribers'] ?? [], 'is_array'));
}

function newsletter_subscribe(string $email, ?array $customer = null): array
{
    $email = strtolower(sanitize_email($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid email address.'];
    }

    try {
        return site_content_with_lock(static function () use ($email, $customer): array {
            $content = site_content();
            $linkedCustomer = $customer;

            if ($linkedCustomer === null) {
                foreach ($content['customers']['items'] ?? [] as $existingCustomer) {
                    if (strtolower((string) ($existingCustomer['email'] ?? '')) === $email) {
                        $linkedCustomer = $existingCustomer;
                        break;
                    }
                }
            }

            $subscribers = array_values(array_filter($content['newsletter']['subscribers'] ?? [], 'is_array'));
            $existingIndex = null;
            foreach ($subscribers as $index => $subscriber) {
                if (strtolower((string) ($subscriber['subscribed_email'] ?? '')) === $email) {
                    $existingIndex = $index;
                    break;
                }
            }

            $now = date('Y-m-d H:i:s');
            $existing = $existingIndex !== null ? $subscribers[$existingIndex] : [];
            $record = [
                'id' => (string) ($existing['id'] ?? ('NLS-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)))),
                'account_customer_id' => clean_string((string) ($linkedCustomer['id'] ?? ''), 80),
                'account_holder_name' => clean_string((string) ($linkedCustomer['name'] ?? ''), 120),
                'account_holder_email' => clean_string((string) ($linkedCustomer['email'] ?? ''), 120),
                'subscribed_email' => $email,
                'source' => $customer !== null ? 'account' : ($linkedCustomer !== null ? 'matched-email' : 'guest'),
                'status' => 'active',
                'subscribed_at' => clean_string((string) ($existing['subscribed_at'] ?? $now), 40),
                'updated_at' => $now,
            ];

            if ($existingIndex !== null) {
                $subscribers[$existingIndex] = array_merge($existing, $record);
            } else {
                $subscribers[] = $record;
            }

            $content['newsletter']['subscribers'] = $subscribers;
            save_site_content($content);

            return [
                'ok' => true,
                'created' => $existingIndex === null,
                'subscriber' => $record,
                'message' => $existingIndex === null
                    ? 'Newsletter subscription saved successfully.'
                    : 'This email is already subscribed. We refreshed the subscriber record.',
            ];
        });
    } catch (Throwable) {
        return ['ok' => false, 'message' => 'Unable to save your subscription right now. Please try again.'];
    }
}

function with_customer_content_update(string $customerId, callable $updater): array
{
    $content = site_content();
    foreach ($content['customers']['items'] ?? [] as $index => $customer) {
        if ((string) ($customer['id'] ?? '') !== $customerId) {
            continue;
        }

        $updatedCustomer = $updater($customer);
        if (!is_array($updatedCustomer)) {
            return ['ok' => false, 'message' => 'Customer update failed.'];
        }

        $content['customers']['items'][$index] = array_merge($customer, $updatedCustomer);
        save_site_content($content);

        return [
            'ok' => true,
            'customer' => current_customer(),
        ];
    }

    return ['ok' => false, 'message' => 'Customer account was not found.'];
}

function customer_login(string $email, string $password): array
{
    $email = strtolower(sanitize_email($email));
    if ($email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Email and password are required.'];
    }

    foreach (site_content()['customers']['items'] ?? [] as $customer) {
        if (strtolower((string) ($customer['email'] ?? '')) !== $email) {
            continue;
        }

        $status = strtolower((string) ($customer['status'] ?? 'active'));
        if ($status !== 'active') {
            return ['ok' => false, 'message' => 'This account is not available right now.'];
        }

        $hash = (string) ($customer['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return ['ok' => false, 'message' => 'Invalid email or password.'];
        }

        session_regenerate_id(true);
        $_SESSION['customer_auth'] = [
            'customer_id' => (string) ($customer['id'] ?? ''),
            'email' => (string) ($customer['email'] ?? ''),
            'fingerprint' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        ];

        return ['ok' => true, 'customer' => $customer];
    }

    return ['ok' => false, 'message' => 'Invalid email or password.'];
}

function customer_logout(): void
{
    unset($_SESSION['customer_auth']);
    session_regenerate_id(true);
}

function customer_register(array $input): array
{
    $name = clean_string($input['name'] ?? '', 120);
    $email = strtolower(sanitize_email((string) ($input['email'] ?? '')));
    $phone = clean_string($input['phone'] ?? '', 40);
    $city = clean_string($input['city'] ?? '', 80);
    $password = (string) ($input['password'] ?? '');
    $confirm = (string) ($input['confirm_password'] ?? '');

    if ($name === '' || $email === '' || $phone === '' || $city === '') {
        return ['ok' => false, 'message' => 'Name, email, phone, and city are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid email address.'];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    if ($password !== $confirm) {
        return ['ok' => false, 'message' => 'Passwords do not match.'];
    }

    $content = site_content();
    foreach ($content['customers']['items'] ?? [] as $customer) {
        if (strtolower((string) ($customer['email'] ?? '')) === $email) {
            return ['ok' => false, 'message' => 'An account with this email already exists.'];
        }
    }

    $content['customers']['items'][] = [
        'id' => 'CUS-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => $phone,
        'city' => $city,
        'state' => '',
        'country' => 'India',
        'postal_code' => '',
        'address_line_1' => '',
        'address_line_2' => '',
        'status' => 'active',
        'joined_at' => date('Y-m-d'),
        'last_order_at' => '',
        'total_orders' => '0',
        'total_spent' => money_format(0),
        'wishlist_product_ids' => [],
        'saved_addresses' => [],
        'notes' => 'Registered from storefront',
    ];

    save_site_content($content);

    $result = customer_login($email, $password);
    if (!$result['ok']) {
        return ['ok' => false, 'message' => 'Account created, but automatic sign in failed.'];
    }

    return ['ok' => true, 'customer' => $result['customer'] ?? null];
}

function require_customer_auth(string $nextUrl): void
{
    if (customer_is_logged_in()) {
        return;
    }

    site_flash_set('error', 'Please sign in to continue.');
    redirect(resolve_link('/account/login/?next=' . urlencode($nextUrl)));
}

function product_by_id(string $productId): ?array
{
    $map = catalog_product_map();
    return $map[$productId] ?? null;
}

function product_url(array $product, array $extraParams = []): string
{
    $id = $product['original_id'] ?? $product['id'] ?? '';
    $url = '/product/?id=' . urlencode((string) $id);
    if (!empty($product['url_metal_param'])) {
        $extraParams['metal'] = $product['url_metal_param'];
    }
    if (!empty($extraParams)) {
        $url .= '&' . http_build_query($extraParams);
    }
    return resolve_link($url);
}

function wishlist_product_id(string $productId): string
{
    $productId = clean_string($productId, 120);
    if ($productId === '') {
        return '';
    }

    if (str_contains($productId, '__metal_')) {
        return clean_string((string) explode('__metal_', $productId, 2)[0], 80);
    }

    return clean_string($productId, 80);
}

function product_option_data(array $product): array
{
    $type = (string) ($product['product_type'] ?? 'Ring');
    $typeKey = strtolower($type);
    $isRingProduct = str_starts_with($typeKey, 'ring');
    $profile = catalog_attribute_profile($type);
    
    $isMatrixProduct = false;
    if (!empty($product['metal_variations'])) {
        foreach ($product['metal_variations'] as $mv) {
            if ($mv['active'] ?? false) {
                $isMatrixProduct = true;
                break;
            }
        }
    }
    if (!$isMatrixProduct && !empty($profile['option_metal_options'])) {
        $isMatrixProduct = true;
    }
    
    $primaryColor = (string) ($product['color'] ?? 'Yellow Gold');
    $catalogColors = site_content()['catalog_meta']['colors'] ?? [];

    $colors = [$primaryColor];
    foreach ($catalogColors as $color) {
        if (!in_array($color, $colors, true) && count($colors) < 4) {
            $colors[] = $color;
        }
    }

    $sizes = match ($typeKey) {
        'ring', 'rings' => ['4', '5', '6', '7', '8', '9'],
        'bracelet' => ['16 cm', '17 cm', '18 cm', '19 cm'],
        'necklace', 'pendant' => ['16 in', '18 in', '20 in', '22 in'],
        'earring', 'brooch' => ['Standard'],
        'jewellery set' => ['S', 'M', 'L'],
        default => ['Standard'],
    };

    $materials = match (strtolower($primaryColor)) {
        'silver' => ['Sterling Silver', 'White Gold'],
        'platinum' => ['Platinum', 'White Gold'],
        default => ['18K Gold', '14K Gold', 'Platinum'],
    };

    $colorLabel = 'Color';
    $sizeLabel = 'Size';
    $colorDisplay = 'compact';
    $sizeDisplay = 'compact';
    $colorChoices = array_map(static fn (string $color): array => [
        'value' => $color,
        'label' => $color,
    ], $colors);
    $sizeChoices = array_map(static fn (string $size): array => [
        'value' => $size,
        'label' => $size,
    ], $sizes);

    if ($typeKey === 'earring') {
        $colorLabel = 'Metal';
        $sizeLabel = 'Total Carat Weight';
        $colorDisplay = 'jewellery-metals';
        $sizeDisplay = 'stone-weights';
        $colors = ['18K White Gold', '18K Yellow Gold', '18K Rose Gold', '9K White Gold', '9K Yellow Gold', '9K Rose Gold'];
        $sizes = ['0.6 ctw', '0.8 ctw', '1 ctw', '2.4 ctw'];
        $materials = $colors;
        $colorChoices = [
            ['value' => '18K White Gold', 'label' => 'White Gold', 'kicker' => '18K', 'tone' => 'white'],
            ['value' => '18K Yellow Gold', 'label' => 'Yellow Gold', 'kicker' => '18K', 'tone' => 'yellow'],
            ['value' => '18K Rose Gold', 'label' => 'Rose Gold', 'kicker' => '18K', 'tone' => 'rose'],
            ['value' => '9K White Gold', 'label' => 'White Gold', 'kicker' => '9K', 'tone' => 'white'],
            ['value' => '9K Yellow Gold', 'label' => 'Yellow Gold', 'kicker' => '9K', 'tone' => 'yellow'],
            ['value' => '9K Rose Gold', 'label' => 'Rose Gold', 'kicker' => '9K', 'tone' => 'rose'],
        ];
        $sizeChoices = [
            ['value' => '0.6 ctw', 'label' => '0.6 ctw', 'tone' => 'accent'],
            ['value' => '0.8 ctw', 'label' => '0.8 ctw', 'tone' => 'neutral'],
            ['value' => '1 ctw', 'label' => '1 ctw', 'tone' => 'neutral'],
            ['value' => '2.4 ctw', 'label' => '2.4 ctw', 'tone' => 'neutral'],
        ];
    } elseif ($typeKey === 'bracelet') {
        $colorLabel = 'Metal';
        $sizeLabel = 'Diamond Weight & Length';
        $colorDisplay = 'jewellery-metals';
        $sizeDisplay = 'stone-weights';
        $colors = ['18K White Gold', '18K Yellow Gold', '9K White Gold', '9K Yellow Gold'];
        $sizes = ['3.6 ctw / 165mm', '3.8 ctw / 175mm'];
        $materials = $colors;
        $colorChoices = [
            ['value' => '18K White Gold', 'label' => 'White Gold', 'kicker' => '18K', 'tone' => 'white'],
            ['value' => '18K Yellow Gold', 'label' => 'Yellow Gold', 'kicker' => '18K', 'tone' => 'yellow'],
            ['value' => '9K White Gold', 'label' => 'White Gold', 'kicker' => '9K', 'tone' => 'white'],
            ['value' => '9K Yellow Gold', 'label' => 'Yellow Gold', 'kicker' => '9K', 'tone' => 'yellow'],
        ];
        $sizeChoices = [
            ['value' => '3.6 ctw / 165mm', 'label' => '3.6 ctw', 'caption' => '165mm', 'tone' => 'accent'],
            ['value' => '3.8 ctw / 175mm', 'label' => '3.8 ctw', 'caption' => '175mm', 'tone' => 'neutral'],
        ];
    } elseif (in_array($typeKey, ['necklace', 'pendant', 'brooch', 'jewellery set'], true)) {
        $colorLabel = 'Metal';
        $sizeLabel = 'Total Carat Weight';
        $colorDisplay = 'jewellery-metals';
        $sizeDisplay = 'stone-weights';
        $colors = ['18K White Gold', '18K Yellow Gold', '18K Rose Gold'];
        $sizes = ['0.5 ctw', '0.7 ctw', '1 ctw', '1.25 ctw', '1.5 ctw', '2 ctw', '3 ctw'];
        $materials = $colors;
        $colorChoices = [
            ['value' => '18K White Gold', 'label' => 'White Gold', 'kicker' => '18K', 'tone' => 'white'],
            ['value' => '18K Yellow Gold', 'label' => 'Yellow Gold', 'kicker' => '18K', 'tone' => 'yellow'],
            ['value' => '18K Rose Gold', 'label' => 'Rose Gold', 'kicker' => '18K', 'tone' => 'rose'],
        ];
        $sizeChoices = [
            ['value' => '0.5 ctw', 'label' => '0.5 ctw', 'tone' => 'neutral'],
            ['value' => '0.7 ctw', 'label' => '0.7 ctw', 'tone' => 'neutral'],
            ['value' => '1 ctw', 'label' => '1 ctw', 'tone' => 'accent'],
            ['value' => '1.25 ctw', 'label' => '1.25 ctw', 'tone' => 'neutral'],
            ['value' => '1.5 ctw', 'label' => '1.5 ctw', 'tone' => 'neutral'],
            ['value' => '2 ctw', 'label' => '2 ctw', 'tone' => 'neutral'],
            ['value' => '3 ctw', 'label' => '3 ctw', 'tone' => 'neutral'],
        ];
    }

    $profileColors = array_values(array_filter(array_map(static fn (mixed $item): string => clean_string((string) $item, 80), (array) ($profile['option_colors'] ?? [])), static fn (string $item): bool => $item !== ''));
    $profileSizes = array_values(array_filter(array_map(static fn (mixed $item): string => clean_string((string) $item, 80), (array) ($profile['option_sizes'] ?? [])), static fn (string $item): bool => $item !== ''));
    $profileColorChoices = array_values(array_filter((array) ($profile['option_color_choices'] ?? []), static fn (mixed $item): bool => is_array($item) && clean_string((string) ($item['value'] ?? ''), 80) !== '' && clean_string((string) ($item['label'] ?? ''), 120) !== ''));
    $profileSizeChoices = array_values(array_filter((array) ($profile['option_size_choices'] ?? []), static fn (mixed $item): bool => is_array($item) && clean_string((string) ($item['value'] ?? ''), 80) !== '' && clean_string((string) ($item['label'] ?? ''), 120) !== ''));
    $profileMetalOptions = array_values(array_filter((array) ($profile['option_metal_options'] ?? []), static fn (mixed $item): bool => is_array($item) && clean_string((string) ($item['value'] ?? ''), 80) !== '' && clean_string((string) ($item['label'] ?? ''), 120) !== ''));
    $profileBandClawOptions = array_values(array_filter((array) ($profile['option_band_claw_metal_options'] ?? []), static fn (mixed $item): bool => is_array($item) && clean_string((string) ($item['value'] ?? ''), 80) !== '' && clean_string((string) ($item['label'] ?? ''), 120) !== ''));
    $profileDeliveryOptions = array_values(array_filter((array) ($profile['option_delivery_options'] ?? []), static fn (mixed $item): bool => is_array($item) && clean_string((string) ($item['value'] ?? ''), 80) !== '' && clean_string((string) ($item['label'] ?? ''), 120) !== ''));

    if ($profileColors !== []) {
        $colors = $profileColors;
        $materials = $profileColors;
    }
    if ($profileSizes !== []) {
        $sizes = $profileSizes;
    }
    if (clean_string((string) ($profile['option_color_label'] ?? ''), 60) !== '') {
        $colorLabel = clean_string((string) ($profile['option_color_label'] ?? ''), 60);
    }
    if (clean_string((string) ($profile['option_size_label'] ?? ''), 60) !== '') {
        $sizeLabel = clean_string((string) ($profile['option_size_label'] ?? ''), 60);
    }
    if (in_array((string) ($profile['option_color_display'] ?? ''), ['compact', 'jewellery-metals'], true)) {
        $colorDisplay = (string) $profile['option_color_display'];
    }
    if (in_array((string) ($profile['option_size_display'] ?? ''), ['compact', 'stone-weights'], true)) {
        $sizeDisplay = (string) $profile['option_size_display'];
    }
    if ($profileColorChoices !== []) {
        $colorChoices = $profileColorChoices;
        $colors = array_values(array_unique(array_column($profileColorChoices, 'value')));
        if ($colorLabel === 'Metal' || $colorDisplay === 'jewellery-metals') {
            $materials = $colors;
        }
    }
    if ($profileSizeChoices !== []) {
        $sizeChoices = $profileSizeChoices;
        $sizes = array_values(array_unique(array_column($profileSizeChoices, 'value')));
    }
    if ($isMatrixProduct && $profileMetalOptions !== []) {
        $metalOptions = $profileMetalOptions;
    }
    if ($isMatrixProduct && $profileDeliveryOptions !== []) {
        $deliveryOptions = array_map(static function (array $option): array {
            if (($option['price_label'] ?? '') === '') {
                $option['price_label'] = ((float) ($option['price'] ?? 0)) > 0 ? '+' . money_format((float) $option['price']) : 'Included';
            }
            if (($option['badge'] ?? '') === '') {
                $option['badge'] = ((float) ($option['price'] ?? 0)) > 0 ? 'Priority' : 'Basic';
            }
            return $option;
        }, $profileDeliveryOptions);
    }

    $requestedMetal = strtolower(clean_string($_POST['metal'] ?? $_GET['metal'] ?? '', 40));
    
    if ($requestedMetal === '' && $isMatrixProduct && !empty($product['metal_variations'])) {
        foreach ($product['metal_variations'] as $mv) {
            if ($mv['active'] ?? false) {
                $requestedMetal = strtolower(content_slug($mv['metal'] ?? '', 'metal'));
                break;
            }
        }
    }

    $metalGallery = [];
    $metalHover = '';
    $metalDescription = '';
    $metalFeatures = [];
    if ($isMatrixProduct && isset($product['metal_variations']) && is_array($product['metal_variations'])) {
        foreach ($product['metal_variations'] as $mv) {
            if (($mv['active'] ?? false) && strtolower(content_slug($mv['metal'] ?? '', 'metal')) === $requestedMetal) {
                if (!empty($mv['gallery']) && is_array($mv['gallery'])) {
                    $metalGallery = $mv['gallery'];
                } elseif (!empty($mv['image'])) {
                    $metalGallery = [$mv['image']];
                }
                if (!empty($mv['hover_image'])) {
                    $metalHover = $mv['hover_image'];
                }
                if (!empty($mv['description'])) {
                    $metalDescription = $mv['description'];
                }
                if (!empty($mv['features']) && is_array($mv['features'])) {
                    $metalFeatures = $mv['features'];
                }
                break;
            }
        }
    }

    if (!empty($metalGallery)) {
        $gallery = $metalGallery;
        if ($metalHover !== '' && !in_array($metalHover, $gallery, true)) {
            $gallery[] = $metalHover;
        }
    } else {
        $gallery = array_values(array_unique(array_filter([
            $product['default_image'] ?? '',
            $product['hover_image'] ?? '',
            $product['popup_image'] ?? '',
        ], static fn (string $item): bool => $item !== '')));
    }

    $features = [];

    $metalOptions = [];
    $bandClawMetalOptions = [];
    $matrixShapes = [];
    $matrixSizes = [];
    
    if ($isMatrixProduct) {
        $metalVariations = (array) ($product['metal_variations'] ?? []);
        if ($metalVariations !== []) {
            foreach ($metalVariations as $var) {
                if (($var['active'] ?? false) && ($var['metal'] ?? '') !== '') {
                    $metalLabel = $var['metal'];
                    $metalSlug = content_slug($metalLabel, 'metal');
                    
                    $varShapes = (array)($var['shapes'] ?? []);
                    foreach ($varShapes as $s) {
                        $s = clean_string((string)$s, 40);
                        if ($s !== '' && !in_array($s, $matrixShapes, true)) {
                            $matrixShapes[] = $s;
                        }
                    }
                    
                    $varSizes = (array)($var['sizes'] ?? []);
                    foreach ($varSizes as $sz) {
                        $sz = clean_string((string)$sz, 40);
                        if ($sz !== '' && !in_array($sz, $matrixSizes, true)) {
                            $matrixSizes[] = $sz;
                        }
                    }

                    $varGallery = [];
                    foreach ((array) ($var['gallery'] ?? []) as $mediaItem) {
                        $mediaItem = clean_image($mediaItem);
                        if ($mediaItem !== '' && !in_array($mediaItem, $varGallery, true)) {
                            $varGallery[] = $mediaItem;
                        }
                    }
                    $varImage = clean_image((string) ($var['image'] ?? ''));
                    if ($varImage !== '' && !in_array($varImage, $varGallery, true)) {
                        array_unshift($varGallery, $varImage);
                    }
                    $varHoverImage = clean_image((string) ($var['hover_image'] ?? ''));
                    if ($varHoverImage !== '' && !in_array($varHoverImage, $varGallery, true)) {
                        $varGallery[] = $varHoverImage;
                    }
                    if ($varGallery === []) {
                        foreach ([$product['default_image'] ?? '', $product['hover_image'] ?? '', $product['popup_image'] ?? ''] as $fallbackMedia) {
                            $fallbackMedia = clean_image((string) $fallbackMedia);
                            if ($fallbackMedia !== '' && !in_array($fallbackMedia, $varGallery, true)) {
                                $varGallery[] = $fallbackMedia;
                            }
                        }
                    }
                    
                    $varBands = [];
                    $varBandOptions = [];
                    foreach ((array)($var['band_options'] ?? []) as $band) {
                        if (($band['active'] ?? false) && ($band['label'] ?? '') !== '') {
                            $bandLabel = clean_string((string) ($band['label'] ?? ''), 120);
                            $bandSlug = content_slug($bandLabel, 'band');
                            $bandSurcharge = max(0, (float) ($band['surcharge'] ?? 0));
                            $varBands[] = $bandSlug;
                            $varBandOptions[] = [
                                'value' => $bandSlug,
                                'label' => $bandLabel,
                                'description' => $bandSurcharge > 0 ? '+' . money_format($bandSurcharge) : '',
                                'surcharge' => $bandSurcharge,
                            ];
                        }
                    }
                    
                    // Add to metal options
                    $metalOptions[] = [
                        'value' => $metalSlug,
                        'label' => $metalLabel,
                        'description' => 'Starting at ' . money_format((float) ($var['price'] ?? 0)),
                        'base_price' => (float) ($var['price'] ?? 0),
                        'shapes' => $varShapes,
                        'sizes' => $varSizes,
                        'bands' => $varBands,
                        'band_options' => $varBandOptions,
                        'metal_desc' => $var['description'] ?? '',
                        'gallery' => $varGallery,
                        'features' => clean_string_list((array) ($var['features'] ?? []), 160),
                    ];
                    
                    // Collect band options
                    foreach ((array)($var['band_options'] ?? []) as $band) {
                        if (($band['active'] ?? false) && ($band['label'] ?? '') !== '') {
                            $bandLabel = $band['label'];
                            $bandSlug = content_slug($bandLabel, 'band');
                            $surcharge = (float) ($band['surcharge'] ?? 0);
                            
                            $existingBandIndex = null;
                            foreach ($bandClawMetalOptions as $idx => $bOpt) {
                                if ($bOpt['label'] === $bandLabel) {
                                    $existingBandIndex = $idx;
                                    break;
                                }
                            }
                            
                            if ($existingBandIndex === null) {
                                $bandClawMetalOptions[] = [
                                    'value' => $bandSlug,
                                    'label' => $bandLabel,
                                    'description' => $surcharge > 0 ? '+' . money_format($surcharge) : '',
                                    'surcharge' => $surcharge
                                ];
                            } elseif ($surcharge < (float) ($bandClawMetalOptions[$existingBandIndex]['surcharge'] ?? 0)) {
                                $bandClawMetalOptions[$existingBandIndex]['surcharge'] = $surcharge;
                                $bandClawMetalOptions[$existingBandIndex]['description'] = $surcharge > 0 ? '+' . money_format($surcharge) : '';
                            }
                        }
                    }
                }
            }
        }
        
        // Fallback if no variations were set yet
        if ($metalOptions === []) {
            $metalOptions = [
                ['value' => 'platinum', 'label' => 'Platinum', 'description' => 'Naturally white premium metal with a substantial feel.', 'shapes' => ['round', 'oval', 'cushion'], 'gallery' => $gallery],
                ['value' => '18k-yellow-gold', 'label' => '18K Yellow Gold', 'description' => 'Classic gold craftsmanship with a warm, refined finish.', 'shapes' => ['round', 'oval', 'cushion'], 'gallery' => $gallery],
            ];
            $matrixShapes = ['round', 'oval', 'cushion'];
        }
        if ($bandClawMetalOptions === []) {
            $bandClawMetalOptions = [
                ['value' => 'standard', 'label' => 'Standard', 'description' => '', 'surcharge' => 0],
            ];
        }
    }

    $availableShapes = available_diamond_shapes();
    $shapeKeys = [];
    
    // Prefer matrix shapes if available
    $sourceShapes = $matrixShapes !== [] ? $matrixShapes : ($product['diamondShapes'] ?? []);
    foreach ($sourceShapes as $shapeKey) {
        $cleanShapeKey = clean_string((string) $shapeKey, 40);
        if ($cleanShapeKey !== '' && isset($availableShapes[$cleanShapeKey]) && !in_array($cleanShapeKey, $shapeKeys, true)) {
            $shapeKeys[] = $cleanShapeKey;
        }
    }
    
    if ($isRingProduct && $shapeKeys === []) {
        $shapeKeys = ['round', 'oval', 'cushion', 'emerald', 'pear'];
    }

    $diamondShapes = [];
    foreach ($shapeKeys as $shapeKey) {
        $diamondShapes[] = [
            'value' => $shapeKey,
            'label' => $availableShapes[$shapeKey],
        ];
    }

    $deliveryOptions = $isMatrixProduct
        ? [
            [
                'value' => 'standard',
                'label' => '4-5 Weeks Delivery',
                'description' => 'Included with your order and preselected for made-to-order craftsmanship.',
                'price' => 0.0,
                'price_label' => 'Included',
                'badge' => 'Basic',
            ],
            [
                'value' => 'priority',
                'label' => '3-4 Weeks Delivery',
                'description' => 'Priority production and dispatch for a faster delivery window.',
                'price' => 100.0,
                'price_label' => '+£100',
                'badge' => 'Priority',
            ],
        ]
        : [];

    $customFeatures = array_values(array_filter(array_map(static fn (mixed $item): string => clean_string((string) $item, 160), (array) ($product['features'] ?? [])), static fn (string $item): bool => $item !== ''));
    if (!empty($metalFeatures)) {
        $features = $metalFeatures;
    } elseif ($customFeatures !== []) {
        $features = $customFeatures;
    }

    $customColors = array_values(array_filter(array_map(static fn (mixed $item): string => clean_string((string) $item, 80), (array) ($product['option_colors'] ?? [])), static fn (string $item): bool => $item !== ''));
    $customSizes = array_values(array_filter(array_map(static fn (mixed $item): string => clean_string((string) $item, 80), (array) ($product['option_sizes'] ?? [])), static fn (string $item): bool => $item !== ''));
    $customColorLabel = clean_string((string) ($product['option_color_label'] ?? ''), 60);
    $customSizeLabel = clean_string((string) ($product['option_size_label'] ?? ''), 60);
    $customColorDisplay = clean_string((string) ($product['option_color_display'] ?? ''), 40);
    $customSizeDisplay = clean_string((string) ($product['option_size_display'] ?? ''), 40);
    $effectiveColorDisplay = in_array($customColorDisplay, ['compact', 'jewellery-metals'], true) ? $customColorDisplay : $colorDisplay;
    $effectiveSizeDisplay = in_array($customSizeDisplay, ['compact', 'stone-weights'], true) ? $customSizeDisplay : $sizeDisplay;

    $customColorChoices = [];
    foreach ((array) ($product['option_color_choices'] ?? []) as $choice) {
        if (!is_array($choice)) {
            continue;
        }
        $value = clean_string((string) ($choice['value'] ?? ''), 80);
        $label = clean_string((string) ($choice['label'] ?? ''), 120);
        if ($value === '' || $label === '') {
            continue;
        }
        $customColorChoices[] = [
            'value' => $value,
            'label' => $label,
            'kicker' => clean_string((string) ($choice['kicker'] ?? ''), 30),
            'tone' => clean_string((string) ($choice['tone'] ?? ''), 40),
        ];
    }

    $customSizeChoices = [];
    foreach ((array) ($product['option_size_choices'] ?? []) as $choice) {
        if (!is_array($choice)) {
            continue;
        }
        $value = clean_string((string) ($choice['value'] ?? ''), 80);
        $label = clean_string((string) ($choice['label'] ?? ''), 120);
        if ($value === '' || $label === '') {
            continue;
        }
        $customSizeChoices[] = [
            'value' => $value,
            'label' => $label,
            'caption' => clean_string((string) ($choice['caption'] ?? ''), 60),
            'tone' => clean_string((string) ($choice['tone'] ?? ''), 40),
        ];
    }

    $customMetalOptions = [];
    foreach ((array) ($product['option_metal_options'] ?? []) as $option) {
        if (!is_array($option)) {
            continue;
        }
        $value = clean_string((string) ($option['value'] ?? ''), 80);
        $label = clean_string((string) ($option['label'] ?? ''), 120);
        if ($value === '' || $label === '') {
            continue;
        }
        $customMetalOptions[] = [
            'value' => $value,
            'label' => $label,
            'description' => clean_multiline((string) ($option['description'] ?? ''), 220),
        ];
    }

    $customBandClawOptions = [];
    foreach ((array) ($product['option_band_claw_metal_options'] ?? []) as $option) {
        if (!is_array($option)) {
            continue;
        }
        $value = clean_string((string) ($option['value'] ?? ''), 80);
        $label = clean_string((string) ($option['label'] ?? ''), 120);
        if ($value === '' || $label === '') {
            continue;
        }
        $customBandClawOptions[] = [
            'value' => $value,
            'label' => $label,
            'description' => clean_multiline((string) ($option['description'] ?? ''), 220),
        ];
    }

    $customDeliveryOptions = [];
    foreach ((array) ($product['option_delivery_options'] ?? []) as $option) {
        if (!is_array($option)) {
            continue;
        }
        $value = clean_string((string) ($option['value'] ?? ''), 80);
        $label = clean_string((string) ($option['label'] ?? ''), 120);
        if ($value === '' || $label === '') {
            continue;
        }
        $price = (float) ($option['price'] ?? 0);
        $customDeliveryOptions[] = [
            'value' => $value,
            'label' => $label,
            'description' => clean_multiline((string) ($option['description'] ?? ''), 220),
            'price' => max(0, $price),
            'price_label' => clean_string((string) ($option['price_label'] ?? ''), 40),
            'badge' => clean_string((string) ($option['badge'] ?? ''), 40),
        ];
    }

    if ($customColorChoices !== []) {
        $colorChoices = $customColorChoices;
        $colors = array_values(array_unique(array_column($customColorChoices, 'value')));
    } elseif ($customColors !== []) {
        $colors = $customColors;
        $colorChoices = array_map(static function (string $color) use ($effectiveColorDisplay): array {
            $tone = match (true) {
                str_contains(strtolower($color), 'rose') => 'rose',
                str_contains(strtolower($color), 'yellow') => 'yellow',
                default => 'white',
            };
            $kicker = '';
            if (preg_match('/\b(18k|9k|14k)\b/i', $color, $matches)) {
                $kicker = strtoupper($matches[1]);
            }
            $label = trim(preg_replace('/\b(18k|9k|14k)\b/i', '', $color) ?? $color);
            return [
                'value' => $color,
                'label' => $label !== '' ? $label : $color,
                'kicker' => $effectiveColorDisplay === 'jewellery-metals' ? $kicker : '',
                'tone' => $effectiveColorDisplay === 'jewellery-metals' ? $tone : '',
            ];
        }, $colors);
    }

    if ($customSizeChoices !== []) {
        $sizeChoices = $customSizeChoices;
        $sizes = array_values(array_unique(array_column($customSizeChoices, 'value')));
    } elseif ($customSizes !== []) {
        $sizes = $customSizes;
        $sizeChoices = array_map(static function (string $size) use ($effectiveSizeDisplay): array {
            $label = $size;
            $caption = '';
            if ($effectiveSizeDisplay === 'stone-weights' && str_contains($size, '/')) {
                [$label, $caption] = array_map('trim', explode('/', $size, 2));
            }
            return [
                'value' => $size,
                'label' => $label,
                'caption' => $caption,
                'tone' => $effectiveSizeDisplay === 'stone-weights' ? 'neutral' : '',
            ];
        }, $sizes);
    }

    if ($customColorLabel !== '') {
        $colorLabel = $customColorLabel;
    }

    if ($customSizeLabel !== '') {
        $sizeLabel = $customSizeLabel;
    }

    if (in_array($customColorDisplay, ['compact', 'jewellery-metals'], true)) {
        $colorDisplay = $customColorDisplay;
    }

    if (in_array($customSizeDisplay, ['compact', 'stone-weights'], true)) {
        $sizeDisplay = $customSizeDisplay;
    }

    if ($customMetalOptions !== []) {
        $metalOptions = $customMetalOptions;
    }
    if ($customBandClawOptions !== [] && (!$isMatrixProduct || $bandClawMetalOptions === [])) {
        $bandClawMetalOptions = $customBandClawOptions;
    } elseif ($bandClawMetalOptions === [] && $profileBandClawOptions !== []) {
        $bandClawMetalOptions = array_map(static function (array $option): array {
            return [
                'value' => clean_string((string) ($option['value'] ?? ''), 80),
                'label' => clean_string((string) ($option['label'] ?? ''), 120),
                'description' => clean_multiline((string) ($option['description'] ?? ''), 220),
                'surcharge' => max(0, (float) ($option['surcharge'] ?? 0)),
            ];
        }, $profileBandClawOptions);
    }
    if ($customDeliveryOptions !== []) {
        $deliveryOptions = array_map(static function (array $option): array {
            if (($option['price_label'] ?? '') === '') {
                $option['price_label'] = ((float) ($option['price'] ?? 0)) > 0 ? '+' . money_format((float) $option['price']) : 'Included';
            }
            if (($option['badge'] ?? '') === '') {
                $option['badge'] = ((float) ($option['price'] ?? 0)) > 0 ? 'Priority' : 'Basic';
            }
            return $option;
        }, $customDeliveryOptions);
    }

    if ($colorLabel === 'Metal' || $colorDisplay === 'jewellery-metals') {
        $materials = $colors;
    }

    usort($bandClawMetalOptions, static function (array $left, array $right): int {
        $leftSurcharge = max(0, (float) ($left['surcharge'] ?? 0));
        $rightSurcharge = max(0, (float) ($right['surcharge'] ?? 0));

        if ($leftSurcharge !== $rightSurcharge) {
            return $leftSurcharge <=> $rightSurcharge;
        }

        return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    });

    return [
        'is_ring_product' => $isRingProduct,
        'is_matrix_product' => $isMatrixProduct,
        'colors' => $colors,
        'sizes' => $sizes,
        'materials' => $materials,
        'color_label' => $colorLabel,
        'size_label' => $sizeLabel,
        'color_display' => $colorDisplay,
        'size_display' => $sizeDisplay,
        'color_choices' => $colorChoices,
        'size_choices' => $sizeChoices,
        'gallery' => $gallery,
        'features' => $features,
        'sku' => strtoupper((string) ($product['original_id'] ?? $product['id'] ?? '')),
        'diamond_shapes' => $diamondShapes,
        'metal_options' => $metalOptions,
        'metal_description' => $metalDescription ?? '',
        'band_claw_metal_options' => $bandClawMetalOptions,
        'delivery_options' => $deliveryOptions,
    ];
}

function product_diamond_inventory(array $product, string $shape = ''): array
{
    $shape = clean_string($shape, 40);
    $productType = clean_string((string) ($product['product_type'] ?? 'Ring'), 80);
    $profileTypes = [$productType];
    if (strcasecmp($productType, 'Rings') === 0) {
        $profileTypes[] = 'Ring';
    } elseif (strcasecmp($productType, 'Ring') === 0) {
        $profileTypes[] = 'Rings';
    }

    $profileInventory = [];
    foreach ($profileTypes as $profileType) {
        $candidateInventory = array_values((array) (catalog_attribute_profile($profileType)['diamond_inventory'] ?? []));
        if ($candidateInventory !== []) {
            $profileInventory = $candidateInventory;
            break;
        }
    }
    $productInventory = array_values((array) ($product['diamond_inventory'] ?? []));
    $inventorySource = $profileInventory !== [] ? $profileInventory : $productInventory;
    $inventory = [];

    foreach ($inventorySource as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $status = strtolower(clean_string((string) ($item['status'] ?? 'active'), 20));
        if ($status !== '' && $status !== 'active') {
            continue;
        }

        $itemShape = strtolower(clean_string((string) ($item['shape'] ?? ''), 40));
        if ($shape !== '' && $itemShape !== '' && $itemShape !== 'all' && $itemShape !== strtolower($shape)) {
            continue;
        }

        $price = (float) ($item['price'] ?? 0);
        $image = clean_string((string) ($item['image'] ?? ''), 2048);
        $inventory[] = [
            'id' => clean_string((string) ($item['id'] ?? ('diamond-' . ($index + 1))), 80),
            'shape' => $itemShape !== '' ? $itemShape : ($shape !== '' ? strtolower($shape) : 'round'),
            'title' => clean_string((string) ($item['title'] ?? ''), 140),
            'image' => $image !== '' ? clean_image($image) : '',
            'carat' => clean_string((string) ($item['carat'] ?? ''), 20),
            'color' => clean_string((string) ($item['color'] ?? ''), 20),
            'clarity' => clean_string((string) ($item['clarity'] ?? ''), 20),
            'cut' => clean_string((string) ($item['cut'] ?? ''), 40),
            'ratio' => clean_string((string) ($item['ratio'] ?? ''), 40),
            'measurement' => clean_string((string) ($item['measurement'] ?? ''), 80),
            'ref' => clean_string((string) ($item['ref'] ?? ''), 80),
            'igi_certificate' => clean_string((string) ($item['igi_certificate'] ?? ''), 160),
            'price' => max(0, $price),
            'description' => clean_multiline((string) ($item['description'] ?? ''), 280),
            'badge' => clean_string((string) ($item['badge'] ?? ''), 40),
        ];
    }

    if ($inventory !== []) {
        return $inventory;
    }

    if ($inventorySource !== []) {
        return [];
    }

    $fallbackShape = $shape !== '' ? strtolower($shape) : 'round';
    return [
        [
            'id' => 'diamond-1',
            'shape' => $fallbackShape,
            'title' => '',
            'image' => '',
            'carat' => '1.02',
            'color' => 'D',
            'clarity' => 'VVS1',
            'cut' => 'Excellent',
            'ratio' => '',
            'measurement' => '',
            'ref' => '',
            'igi_certificate' => '',
            'price' => 5400.0,
            'description' => '',
            'badge' => 'Lab Selected',
        ],
        [
            'id' => 'diamond-2',
            'shape' => $fallbackShape,
            'title' => '',
            'image' => '',
            'carat' => '0.90',
            'color' => 'E',
            'clarity' => 'VS1',
            'cut' => 'Excellent',
            'ratio' => '',
            'measurement' => '',
            'ref' => '',
            'igi_certificate' => '',
            'price' => 4100.0,
            'description' => '',
            'badge' => 'Lab Selected',
        ],
        [
            'id' => 'diamond-3',
            'shape' => $fallbackShape,
            'title' => '',
            'image' => '',
            'carat' => '1.20',
            'color' => 'F',
            'clarity' => 'VVS2',
            'cut' => 'Very Good',
            'ratio' => '',
            'measurement' => '',
            'ref' => '',
            'igi_certificate' => '',
            'price' => 6200.0,
            'description' => '',
            'badge' => 'Lab Selected',
        ],
        [
            'id' => 'diamond-4',
            'shape' => $fallbackShape,
            'title' => '',
            'image' => '',
            'carat' => '1.50',
            'color' => 'G',
            'clarity' => 'VS2',
            'cut' => 'Excellent',
            'ratio' => '',
            'measurement' => '',
            'ref' => '',
            'igi_certificate' => '',
            'price' => 7800.0,
            'description' => '',
            'badge' => 'Lab Selected',
        ],
        [
            'id' => 'diamond-5',
            'shape' => $fallbackShape,
            'title' => '',
            'image' => '',
            'carat' => '2.01',
            'color' => 'D',
            'clarity' => 'IF',
            'cut' => 'Excellent',
            'ratio' => '',
            'measurement' => '',
            'ref' => '',
            'igi_certificate' => '',
            'price' => 15000.0,
            'description' => '',
            'badge' => 'Lab Selected',
        ],
    ];
}

function product_diamond_inventory_item(array $product, string $diamondId, string $shape = ''): ?array
{
    $diamondId = clean_string($diamondId, 80);
    if ($diamondId === '') {
        return null;
    }

    foreach (product_diamond_inventory($product, $shape) as $item) {
        if ((string) ($item['id'] ?? '') === $diamondId) {
            return $item;
        }
    }

    return null;
}

function product_normalize_selection(array $product, array $selection = [], bool $bypassSizeValidation = false): array
{
    $options = product_option_data($product);

    $color = clean_string($selection['color'] ?? '', 80);
    if ($color === '' || !in_array($color, $options['colors'], true)) {
        $color = $options['colors'][0] ?? (string) ($product['color'] ?? 'Yellow Gold');
    }

    $size = clean_string($selection['size'] ?? '', 40);
    if (!$bypassSizeValidation && ($size === '' || !in_array($size, $options['sizes'], true))) {
        $size = $options['sizes'][0] ?? 'Standard';
    }
    if ($size === '') {
        $size = $options['sizes'][0] ?? 'Standard';
    }

    $diamondShape = '';
    $metal = '';
    $bandClawMetal = '';
    $deliveryOption = '';
    $deliveryLabel = '';
    $deliverySurcharge = 0.0;

    $metalValues = array_column($options['metal_options'], 'value');
    if ($metalValues !== []) {
        $metal = clean_string($selection['metal'] ?? ($selection['color'] ?? ''), 40);
        if ($metal === '' || !in_array($metal, $metalValues, true)) {
            $metal = $metalValues[0] ?? '';
        }
    }

    if ($options['is_ring_product']) {
        $shapeValues = array_column($options['diamond_shapes'], 'value');
        $diamondShape = clean_string($selection['diamond_shape'] ?? '', 40);
        if ($diamondShape === '' || !in_array($diamondShape, $shapeValues, true)) {
            $diamondShape = $shapeValues[0] ?? '';
        }

        $bandOptions = [];
        foreach ((array) ($options['metal_options'] ?? []) as $option) {
            if ((string) ($option['value'] ?? '') !== $metal) {
                continue;
            }

            $bandOptions = array_values(array_filter(
                (array) ($option['band_options'] ?? []),
                static fn (mixed $item): bool => is_array($item) && clean_string((string) ($item['value'] ?? ''), 80) !== ''
            ));
            break;
        }
        if ($bandOptions === []) {
            $bandOptions = array_values(array_filter(
                (array) ($options['band_claw_metal_options'] ?? []),
                static fn (mixed $item): bool => is_array($item)
            ));
        }

        $bandValues = array_column($bandOptions, 'value');
        $bandClawMetal = clean_string($selection['band_claw_metal'] ?? '', 60);
        if ($bandClawMetal === '' || !in_array($bandClawMetal, $bandValues, true)) {
            $freeBandOption = null;
            foreach ($bandOptions as $option) {
                if (max(0, (float) ($option['surcharge'] ?? 0)) <= 0) {
                    $freeBandOption = $option;
                    break;
                }
            }

            if ($freeBandOption !== null) {
                $bandClawMetal = (string) ($freeBandOption['value'] ?? '');
            } else {
                $bandClawMetal = $bandValues[0] ?? '';
            }
        }

        $deliveryValues = array_column($options['delivery_options'], 'value');
        $deliveryOption = clean_string($selection['delivery_option'] ?? '', 30);
        if ($deliveryOption === '' || !in_array($deliveryOption, $deliveryValues, true)) {
            $deliveryOption = $deliveryValues[0] ?? 'standard';
        }

        foreach ($options['delivery_options'] as $option) {
            if (($option['value'] ?? '') !== $deliveryOption) {
                continue;
            }
            $deliveryLabel = (string) ($option['label'] ?? '');
            $deliverySurcharge = (float) ($option['price'] ?? 0.0);
            break;
        }
    }

    return [
        'color' => $color,
        'size' => $size,
        'diamond_shape' => $diamondShape,
        'metal' => $metal,
        'band_claw_metal' => $bandClawMetal,
        'delivery_option' => $deliveryOption,
        'delivery_label' => $deliveryLabel,
        'delivery_surcharge' => $deliverySurcharge,
    ];
}

function product_option_label(array $choices, string $value): string
{
    foreach ($choices as $choice) {
        if ((string) ($choice['value'] ?? '') === $value) {
            return (string) ($choice['label'] ?? $value);
        }
    }

    return $value;
}

function product_selection_setting_price(array $product, array $selection): float
{
    $optionData = product_option_data($product);
    $basePrice = product_price_value($product);
    $selectedMetal = clean_string((string) ($selection['metal'] ?? ''), 40);
    $selectedBandClawMetal = clean_string((string) ($selection['band_claw_metal'] ?? ''), 60);
    $selectedMetalBandOptions = [];

    foreach ((array) ($optionData['metal_options'] ?? []) as $option) {
        if ((string) ($option['value'] ?? '') !== $selectedMetal) {
            continue;
        }

        $metalBasePrice = max(0, (float) ($option['base_price'] ?? 0));
        if ($metalBasePrice > 0) {
            $basePrice = $metalBasePrice;
        }
        $selectedMetalBandOptions = array_values(array_filter(
            (array) ($option['band_options'] ?? []),
            static fn (mixed $item): bool => is_array($item)
        ));
        break;
    }

    $bandClawSurcharge = 0.0;
    $bandOptions = $selectedMetalBandOptions !== [] ? $selectedMetalBandOptions : (array) ($optionData['band_claw_metal_options'] ?? []);
    foreach ($bandOptions as $option) {
        if ((string) ($option['value'] ?? '') !== $selectedBandClawMetal) {
            continue;
        }

        $bandClawSurcharge = max(0, (float) ($option['surcharge'] ?? 0));
        break;
    }

    return $basePrice + $bandClawSurcharge;
}

function inventory_low_stock_threshold(): int
{
    return 2;
}

function product_inventory_resolve_metal(array $product, array $selection = []): string
{
    $variations = array_values(array_filter(
        (array) ($product['metal_variations'] ?? []),
        static fn (mixed $item): bool => is_array($item) && clean_string((string) ($item['metal'] ?? ''), 120) !== ''
    ));
    if ($variations === []) {
        return '';
    }

    $candidates = array_filter([
        clean_string((string) ($selection['metal'] ?? ''), 120),
        clean_string((string) ($selection['color'] ?? ''), 120),
        clean_string((string) ($product['color'] ?? ''), 120),
    ], static fn (string $value): bool => $value !== '');

    foreach ($candidates as $candidate) {
        $candidateSlug = content_slug($candidate, '');
        foreach ($variations as $variation) {
            $metal = clean_string((string) ($variation['metal'] ?? ''), 120);
            if ($metal === '') {
                continue;
            }
            if (strcasecmp($metal, $candidate) === 0 || ($candidateSlug !== '' && content_slug($metal, '') === $candidateSlug)) {
                return $metal;
            }
        }
    }

    if (count($variations) === 1) {
        return clean_string((string) ($variations[0]['metal'] ?? ''), 120);
    }

    return '';
}

function product_inventory_target(array $product, array $selection = []): array
{
    $productId = clean_string((string) ($product['id'] ?? ''), 80);
    $productName = clean_string((string) ($product['name'] ?? ''), 140);
    $resolvedMetal = product_inventory_resolve_metal($product, $selection);

    foreach ((array) ($product['metal_variations'] ?? []) as $index => $variation) {
        if (!is_array($variation)) {
            continue;
        }

        $metal = clean_string((string) ($variation['metal'] ?? ''), 120);
        if ($metal === '' || $resolvedMetal === '' || strcasecmp($metal, $resolvedMetal) !== 0) {
            continue;
        }

        $tracked = !empty($variation['inventory_tracked']);
        $quantity = clean_int($variation['inventory_quantity'] ?? 0, 0, 1000000);
        return [
            'scope' => 'metal',
            'product_id' => $productId,
            'metal' => $metal,
            'tracked' => $tracked,
            'quantity' => $tracked ? $quantity : 0,
            'stock_key' => $productId . '::metal::' . content_slug($metal, 'metal-' . $index),
            'label' => trim($productName . ($metal !== '' ? ' - ' . $metal : '')),
        ];
    }

    $tracked = !empty($product['inventory_tracked']);
    $quantity = clean_int($product['inventory_quantity'] ?? 0, 0, 1000000);

    return [
        'scope' => 'product',
        'product_id' => $productId,
        'metal' => '',
        'tracked' => $tracked,
        'quantity' => $tracked ? $quantity : 0,
        'stock_key' => $productId . '::product',
        'label' => $productName !== '' ? $productName : 'This item',
    ];
}

function product_inventory_status(array $product, array $selection = []): array
{
    $target = product_inventory_target($product, $selection);
    $quantity = (int) ($target['quantity'] ?? 0);
    $tracked = !empty($target['tracked']);

    return [
        'tracked' => $tracked,
        'quantity' => $tracked ? $quantity : null,
        'out_of_stock' => $tracked && $quantity <= 0,
        'low_stock' => $tracked && $quantity > 0 && $quantity <= inventory_low_stock_threshold(),
        'label' => (string) ($target['label'] ?? 'This item'),
        'stock_key' => (string) ($target['stock_key'] ?? ''),
        'scope' => (string) ($target['scope'] ?? 'product'),
        'metal' => (string) ($target['metal'] ?? ''),
    ];
}

function product_inventory_unavailable_message(array $status, ?int $allowedQuantity = null): string
{
    $label = clean_string((string) ($status['label'] ?? 'This item'), 180);
    $quantity = (int) ($status['quantity'] ?? 0);

    if (!$status['tracked']) {
        return $label . ' is available.';
    }

    if ($allowedQuantity !== null && $allowedQuantity > 0) {
        return 'Only ' . $allowedQuantity . ' left for ' . $label . '.';
    }

    if ($quantity > 0) {
        return 'Only ' . $quantity . ' left for ' . $label . '.';
    }

    return $label . ' is currently out of stock.';
}

function cart_inventory_reserved_quantity(array $items, string $stockKey, string $excludeLineKey = ''): int
{
    $reserved = 0;

    foreach ($items as $line) {
        if (!is_array($line)) {
            continue;
        }

        $product = product_by_id(clean_string((string) ($line['product_id'] ?? ''), 80));
        if ($product === null) {
            continue;
        }

        $selection = product_normalize_selection($product, $line, true);
        $lineSelection = $selection;
        $lineSelection['diamond_id'] = clean_string((string) ($line['diamond_id'] ?? ''), 80);
        $lineKey = cart_line_key((string) ($product['id'] ?? ''), $lineSelection);
        if ($excludeLineKey !== '' && $lineKey === $excludeLineKey) {
            continue;
        }

        $status = product_inventory_status($product, $selection);
        if ((string) ($status['stock_key'] ?? '') !== $stockKey) {
            continue;
        }

        $reserved += clean_int($line['quantity'] ?? 0, 0, 99);
    }

    return $reserved;
}

function product_selection_primary_media(array $product, array $selection): string
{
    $optionData = product_option_data($product);
    $selectedMetal = clean_string((string) ($selection['metal'] ?? ''), 40);

    foreach ((array) ($optionData['metal_options'] ?? []) as $option) {
        if ((string) ($option['value'] ?? '') !== $selectedMetal) {
            continue;
        }

        foreach ((array) ($option['gallery'] ?? []) as $media) {
            $media = clean_image((string) $media);
            if ($media !== '') {
                return $media;
            }
        }
        break;
    }

    foreach ([
        $product['default_image'] ?? '',
        $product['hover_image'] ?? '',
        $product['popup_image'] ?? '',
    ] as $media) {
        $media = clean_image((string) $media);
        if ($media !== '') {
            return $media;
        }
    }

    return '';
}

function product_primary_media(array $product): string
{
    foreach ((array) ($product['gallery'] ?? []) as $media) {
        $media = clean_image((string) $media);
        if ($media !== '') {
            return $media;
        }
    }

    foreach ([
        $product['default_image'] ?? '',
        $product['hover_image'] ?? '',
        $product['popup_image'] ?? '',
    ] as $media) {
        $media = clean_image((string) $media);
        if ($media !== '') {
            return $media;
        }
    }

    foreach ((array) ($product['metal_variations'] ?? []) as $variation) {
        if (!is_array($variation)) {
            continue;
        }

        foreach ((array) ($variation['gallery'] ?? []) as $media) {
            $media = clean_image((string) $media);
            if ($media !== '') {
                return $media;
            }
        }

        foreach ([
            $variation['image'] ?? '',
            $variation['hover_image'] ?? '',
        ] as $media) {
            $media = clean_image((string) $media);
            if ($media !== '') {
                return $media;
            }
        }
    }

    return resolve_link('/assets/AZURONN Logo_2-01.png');
}

function product_selection_total_price(array $product, array $selection, float $diamondPrice = 0.0): float
{
    $settingPrice = product_selection_setting_price($product, $selection);
    $deliverySurcharge = max(0, (float) ($selection['delivery_surcharge'] ?? 0));

    return $settingPrice + max(0, $diamondPrice) + $deliverySurcharge;
}

function cart_variant_signature(array $selection): string
{
    return implode('|', [
        clean_string($selection['color'] ?? '', 80),
        clean_string($selection['size'] ?? '', 40),
        clean_string($selection['diamond_shape'] ?? '', 40),
        clean_string($selection['diamond_id'] ?? '', 80),
        clean_string($selection['metal'] ?? '', 40),
        clean_string($selection['band_claw_metal'] ?? '', 60),
        clean_string($selection['delivery_option'] ?? '', 30),
    ]);
}

function cart_line_key(string $productId, array $selection): string
{
    return sha1($productId . '|' . cart_variant_signature($selection));
}

function line_variant_parts(array $line): array
{
    $parts = [];

    $diamondTitle = clean_string((string) ($line['diamond_title'] ?? ''), 140);
    if ($diamondTitle !== '') {
        $parts[] = 'Diamond ' . $diamondTitle;
    }

    $color = clean_string((string) ($line['color'] ?? ''), 80);
    if ($color !== '') {
        $parts[] = $color;
    }

    $size = clean_string((string) ($line['size'] ?? ''), 40);
    if ($size !== '') {
        $parts[] = 'Size ' . $size;
    }

    $diamondShape = clean_string((string) ($line['diamond_shape_label'] ?? $line['diamond_shape'] ?? ''), 60);
    if ($diamondTitle === '' && $diamondShape !== '') {
        $parts[] = $diamondShape . ' Diamond';
    }

    $metal = clean_string((string) ($line['metal_label'] ?? $line['metal'] ?? ''), 60);
    if ($metal !== '') {
        $parts[] = $metal;
    }

    $bandClawMetal = clean_string((string) ($line['band_claw_metal_label'] ?? $line['band_claw_metal'] ?? ''), 80);
    if ($bandClawMetal !== '') {
        $parts[] = 'Band/Claw ' . $bandClawMetal;
    }

    $deliveryLabel = clean_string((string) ($line['delivery_label'] ?? ''), 80);
    if ($deliveryLabel !== '') {
        $parts[] = $deliveryLabel;
    }

    return $parts;
}

function line_variant_summary(array $line): string
{
    $parts = line_variant_parts($line);
    return $parts === [] ? 'Default Selection' : implode(' / ', $parts);
}

function cart_cookie_name(): string
{
    return 'azuronn_cart';
}

function cart_has_persistent_token(): bool
{
    return clean_string($_SESSION['store_cart_token'] ?? ($_COOKIE[cart_cookie_name()] ?? ''), 80) !== '';
}

function cart_session_key(bool $persistCookie = true): string
{
    $key = clean_string($_SESSION['store_cart_token'] ?? ($_COOKIE[cart_cookie_name()] ?? ''), 80);
    if ($key === '') {
        $key = 'cart-' . bin2hex(random_bytes(16));
    }

    $_SESSION['store_cart_token'] = $key;

    if ($persistCookie && !headers_sent()) {
        setcookie(cart_cookie_name(), $key, [
            'expires' => time() + (86400 * 365),
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    return $key;
}

function cart_remote_enabled(): bool
{
    return supabase_enabled();
}

function cart_remote_payload(array $cart): array
{
    return [
        'items' => array_values($cart['items'] ?? []),
        'coupon_code' => clean_string($cart['coupon_code'] ?? '', 40),
    ];
}

function cart_remote_row_to_cart(?array $row): ?array
{
    if ($row === null || !is_array($row['payload'] ?? null)) {
        return null;
    }

    return [
        'items' => is_array($row['payload']['items'] ?? null) ? array_values($row['payload']['items']) : [],
        'coupon_code' => clean_string($row['payload']['coupon_code'] ?? '', 40),
    ];
}

function cart_remote_load(): ?array
{
    if (!cart_remote_enabled()) {
        return null;
    }

    if (!cart_has_persistent_token()) {
        return null;
    }

    $row = supabase_select_first('cart_sessions', ['session_key' => cart_session_key(false)], 'session_key,customer_id,payload,updated_at');
    return cart_remote_row_to_cart($row);
}

function cart_remote_save(array $cart): void
{
    if (!cart_remote_enabled()) {
        return;
    }

    $customer = current_customer();
    supabase_upsert_rows('cart_sessions', [[
        'session_key' => cart_session_key(true),
        'customer_id' => ($customer['id'] ?? '') !== '' ? (string) $customer['id'] : null,
        'payload' => cart_remote_payload($cart),
        'updated_at' => gmdate('c'),
    ]], 'session_key');
}

function cart_remote_clear(): void
{
    if (cart_remote_enabled() && cart_has_persistent_token()) {
        supabase_delete_rows('cart_sessions', ['session_key' => cart_session_key(false)]);
    }

    unset($_SESSION['store_cart_token']);
    if (!headers_sent()) {
        setcookie(cart_cookie_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function cart_session_data(): array
{
    static $remoteLoaded = false;

    if (!isset($_SESSION['store_cart']) || !is_array($_SESSION['store_cart'])) {
        $_SESSION['store_cart'] = [
            'items' => [],
            'coupon_code' => '',
        ];
    }

    if (!$remoteLoaded && cart_remote_enabled() && cart_has_persistent_token()) {
        $remoteCart = cart_remote_load();
        if (is_array($remoteCart)) {
            $_SESSION['store_cart'] = $remoteCart;
        }
        $remoteLoaded = true;
    }

    $_SESSION['store_cart']['items'] = is_array($_SESSION['store_cart']['items'] ?? null) ? $_SESSION['store_cart']['items'] : [];
    $_SESSION['store_cart']['coupon_code'] = clean_string($_SESSION['store_cart']['coupon_code'] ?? '', 40);
    return $_SESSION['store_cart'];
}

function cart_save(array $cart): void
{
    $_SESSION['store_cart'] = [
        'items' => array_values($cart['items'] ?? []),
        'coupon_code' => clean_string($cart['coupon_code'] ?? '', 40),
    ];

    cart_remote_save($_SESSION['store_cart']);
}

function coupon_lookup(string $code): ?array
{
    $needle = strtoupper(clean_string($code, 40));
    if ($needle === '') {
        return null;
    }

    foreach (site_content()['coupons']['items'] ?? [] as $coupon) {
        if (strtoupper((string) ($coupon['code'] ?? '')) === $needle) {
            return $coupon;
        }
    }

    return null;
}

function coupon_details_for_subtotal(string $code, float $subtotal): array
{
    $coupon = coupon_lookup($code);
    if ($coupon === null) {
        return ['valid' => false, 'message' => 'Coupon code was not found.', 'discount' => 0.0];
    }

    if (strtolower((string) ($coupon['status'] ?? 'inactive')) !== 'active') {
        return ['valid' => false, 'message' => 'This coupon is inactive.', 'discount' => 0.0];
    }

    $expiresAt = clean_string($coupon['expires_at'] ?? '', 30);
    if ($expiresAt !== '' && strtotime($expiresAt . ' 23:59:59') !== false && strtotime($expiresAt . ' 23:59:59') < time()) {
        return ['valid' => false, 'message' => 'This coupon has expired.', 'discount' => 0.0];
    }

    $minimum = money_value((string) ($coupon['min_order'] ?? '0'));
    if ($minimum > 0 && $subtotal < $minimum) {
        return ['valid' => false, 'message' => 'Minimum order for this coupon is ' . money_format($minimum) . '.', 'discount' => 0.0];
    }

    $value = money_value((string) ($coupon['value'] ?? '0'));
    $discount = strtolower((string) ($coupon['type'] ?? 'percent')) === 'fixed'
        ? min($value, $subtotal)
        : min(($subtotal * $value) / 100, $subtotal);

    return [
        'valid' => $discount > 0,
        'message' => $discount > 0 ? 'Coupon applied.' : 'Coupon did not change the order total.',
        'discount' => $discount,
        'coupon' => $coupon,
    ];
}

function cart_state(): array
{
    $cart = cart_session_data();
    $lines = [];
    $count = 0;
    $subtotal = 0.0;
    $deliveryTotal = 0.0;

    foreach ($cart['items'] as $line) {
        if (!is_array($line)) {
            continue;
        }

        $product = product_by_id(clean_string($line['product_id'] ?? '', 80));
        if ($product === null || strtolower((string) ($product['status'] ?? 'active')) !== 'active') {
            continue;
        }

        $quantity = clean_int($line['quantity'] ?? 1, 1, 99);
        $selection = product_normalize_selection($product, $line, true);
        $inventoryStatus = product_inventory_status($product, $selection);
        $diamondId = clean_string((string) ($line['diamond_id'] ?? ''), 80);
        $diamondTitle = clean_string((string) ($line['diamond_title'] ?? ''), 140);
        $diamondImage = clean_image((string) ($line['diamond_image'] ?? ''));
        $diamondPrice = max(0, (float) ($line['diamond_price'] ?? 0));
        if ($diamondId !== '') {
            $inventoryDiamond = product_diamond_inventory_item($product, $diamondId, (string) ($selection['diamond_shape'] ?? ''));
            if ($inventoryDiamond !== null) {
                if ($diamondTitle === '') {
                    $diamondTitle = clean_string((string) ($inventoryDiamond['title'] ?? ''), 140);
                    if ($diamondTitle === '') {
                        $diamondTitle = trim((string) ($inventoryDiamond['carat'] ?? '') . 'ct ' . (string) ($inventoryDiamond['color'] ?? '') . ' / ' . (string) ($inventoryDiamond['clarity'] ?? ''));
                    }
                }
                if ($diamondImage === '' && clean_string((string) ($inventoryDiamond['image'] ?? ''), 2048) !== '') {
                    $diamondImage = clean_image((string) ($inventoryDiamond['image'] ?? ''));
                }
                if ($diamondPrice <= 0) {
                    $diamondPrice = max(0, (float) ($inventoryDiamond['price'] ?? 0));
                }
            }
        }

        $price = product_selection_setting_price($product, $selection);
        $deliverySurcharge = max(0, (float) ($selection['delivery_surcharge'] ?? 0.0));
        $itemUnitPrice = $price + $diamondPrice;
        $unitPrice = $itemUnitPrice + $deliverySurcharge;
        $lineItemTotal = $itemUnitPrice * $quantity;
        $lineDeliveryTotal = $deliverySurcharge * $quantity;
        $lineTotal = $lineItemTotal + $lineDeliveryTotal;
        $optionData = product_option_data($product);
        $lineSelection = $selection;
        $lineSelection['diamond_id'] = $diamondId;
        $ringMedia = product_selection_primary_media($product, $selection);
        $ringMediaAlt = trim(implode(' ', array_filter([
            clean_string((string) ($product['name'] ?? ''), 140),
            clean_string((string) product_option_label($optionData['metal_options'] ?? [], $selection['metal']), 120),
        ], static fn (string $item): bool => $item !== '')));

        $lines[] = [
            'key' => cart_line_key((string) $product['id'], $lineSelection),
            'product' => $product,
            'quantity' => $quantity,
            'ring_media' => $ringMedia,
            'ring_media_type' => $ringMedia !== '' ? media_asset_type($ringMedia) : 'image',
            'ring_media_alt' => $ringMediaAlt !== '' ? $ringMediaAlt : (string) ($product['name'] ?? 'Ring'),
            'color' => $selection['color'],
            'size' => $selection['size'],
            'diamond_shape' => $selection['diamond_shape'],
            'diamond_shape_label' => product_option_label($optionData['diamond_shapes'] ?? [], $selection['diamond_shape']),
            'diamond_id' => $diamondId,
            'diamond_title' => $diamondTitle,
            'diamond_image' => $diamondImage,
            'diamond_media_type' => $diamondImage !== '' ? media_asset_type($diamondImage) : 'image',
            'diamond_price' => $diamondPrice,
            'diamond_price_label' => money_format($diamondPrice),
            'metal' => $selection['metal'],
            'metal_label' => product_option_label($optionData['metal_options'] ?? [], $selection['metal']),
            'band_claw_metal' => $selection['band_claw_metal'],
            'band_claw_metal_label' => product_option_label($optionData['band_claw_metal_options'] ?? [], $selection['band_claw_metal']),
            'delivery_option' => $selection['delivery_option'],
            'delivery_label' => $selection['delivery_label'],
            'delivery_surcharge' => $deliverySurcharge,
            'delivery_surcharge_label' => $deliverySurcharge > 0 ? money_format($deliverySurcharge) : 'Free',
            'delivery_line_total' => $lineDeliveryTotal,
            'delivery_line_total_label' => $lineDeliveryTotal > 0 ? money_format($lineDeliveryTotal) : 'Free',
            'inventory_tracked' => (bool) ($inventoryStatus['tracked'] ?? false),
            'inventory_quantity' => $inventoryStatus['quantity'],
            'inventory_stock_key' => (string) ($inventoryStatus['stock_key'] ?? ''),
            'inventory_low_stock' => (bool) ($inventoryStatus['low_stock'] ?? false),
            'inventory_out_of_stock' => (bool) ($inventoryStatus['out_of_stock'] ?? false),
            'item_unit_price' => $itemUnitPrice,
            'item_unit_price_label' => money_format($itemUnitPrice),
            'item_line_total' => $lineItemTotal,
            'item_line_total_label' => money_format($lineItemTotal),
            'unit_price' => $unitPrice,
            'unit_price_label' => money_format($unitPrice),
            'base_unit_price' => $price,
            'base_unit_price_label' => money_format($price),
            'line_total' => $lineTotal,
            'line_total_label' => money_format($lineTotal),
            'url' => product_url($product),
        ];

        $count += $quantity;
        $subtotal += $lineItemTotal;
        $deliveryTotal += $lineDeliveryTotal;
    }

    $couponCode = clean_string($cart['coupon_code'] ?? '', 40);
    $couponData = $couponCode !== '' ? coupon_details_for_subtotal($couponCode, $subtotal) : ['valid' => false, 'discount' => 0.0];
    if (!($couponData['valid'] ?? false)) {
        $couponCode = '';
    }

    $discount = (float) ($couponData['discount'] ?? 0.0);
    $shipping = $subtotal >= 500 || $subtotal === 0.0 ? 0.0 : 25.0;
    $total = max(0, $subtotal - $discount + $deliveryTotal + $shipping);

    return [
        'items' => $lines,
        'count' => $count,
        'subtotal' => $subtotal,
        'subtotal_label' => money_format($subtotal),
        'delivery_total' => $deliveryTotal,
        'delivery_total_label' => $deliveryTotal > 0 ? money_format($deliveryTotal) : 'Free',
        'delivery_summary_label' => $deliveryTotal > 0 ? 'Priority Delivery' : 'Basic Delivery',
        'discount' => $discount,
        'discount_label' => money_format($discount),
        'shipping' => $shipping,
        'shipping_label' => $shipping > 0 ? money_format($shipping) : 'Free',
        'total' => $total,
        'total_label' => money_format($total),
        'coupon_code' => $couponCode,
        'coupon' => $couponData['coupon'] ?? null,
    ];
}

function cart_add_item(string $productId, int $quantity, array $selection = [], bool $bypassValidation = false): array
{
    $product = product_by_id($productId);
    if ($product === null || strtolower((string) ($product['status'] ?? 'active')) !== 'active') {
        return ['ok' => false, 'message' => 'Product is no longer available.'];
    }

    $rawSelection = $selection;
    $selection = product_normalize_selection($product, $selection, $bypassValidation);
    $diamondId = clean_string((string) ($rawSelection['diamond_id'] ?? ''), 80);
    $diamondTitle = clean_string((string) ($rawSelection['diamond_title'] ?? ''), 140);
    $diamondImage = clean_string((string) ($rawSelection['diamond_image'] ?? ''), 2048) !== '' ? clean_image((string) ($rawSelection['diamond_image'] ?? '')) : '';
    $diamondPrice = max(0, (float) ($rawSelection['diamond_price'] ?? 0));
    if ($diamondId !== '') {
        $selectedDiamond = product_diamond_inventory_item($product, $diamondId, (string) ($selection['diamond_shape'] ?? ''));
        if ($selectedDiamond === null) {
            return ['ok' => false, 'message' => 'Selected diamond is no longer available.'];
        }

        if ($diamondTitle === '') {
            $diamondTitle = clean_string((string) ($selectedDiamond['title'] ?? ''), 140);
            if ($diamondTitle === '') {
                $diamondTitle = trim((string) ($selectedDiamond['carat'] ?? '') . 'ct ' . (string) ($selectedDiamond['color'] ?? '') . ' / ' . (string) ($selectedDiamond['clarity'] ?? ''));
            }
        }
        if ($diamondImage === '' && clean_string((string) ($selectedDiamond['image'] ?? ''), 2048) !== '') {
            $diamondImage = clean_image((string) ($selectedDiamond['image'] ?? ''));
        }
        if ($diamondPrice <= 0) {
            $diamondPrice = max(0, (float) ($selectedDiamond['price'] ?? 0));
        }
    }

    $selection['diamond_id'] = $diamondId;
    $requestedQuantity = max(1, min(99, $quantity));
    $quantity = $requestedQuantity;
    $cart = cart_session_data();
    $inventoryStatus = product_inventory_status($product, $selection);
    if ($inventoryStatus['tracked']) {
        $reserved = cart_inventory_reserved_quantity($cart['items'], (string) ($inventoryStatus['stock_key'] ?? ''));
        $remaining = max(0, (int) ($inventoryStatus['quantity'] ?? 0) - $reserved);
        if ($remaining <= 0) {
            return ['ok' => false, 'message' => product_inventory_unavailable_message($inventoryStatus)];
        }
        if ($quantity > $remaining) {
            $quantity = $remaining;
        }
    }

    $key = cart_line_key((string) $product['id'], $selection);
    $found = false;

    foreach ($cart['items'] as &$line) {
        if (!is_array($line)) {
            continue;
        }

        $lineSelection = product_normalize_selection($product, $line, true);
        $lineSelection['diamond_id'] = clean_string((string) ($line['diamond_id'] ?? ''), 80);
        $lineKey = cart_line_key(clean_string($line['product_id'] ?? '', 80), $lineSelection);
        if ($lineKey === $key) {
            $line['quantity'] = max(1, min(99, clean_int($line['quantity'] ?? 1, 1, 99) + $quantity));
            $found = true;
            break;
        }
    }
    unset($line);

    if (!$found) {
        $cart['items'][] = [
            'product_id' => (string) $product['id'],
            'quantity' => $quantity,
            'color' => $selection['color'],
            'size' => $selection['size'],
            'diamond_shape' => $selection['diamond_shape'],
            'diamond_id' => $diamondId,
            'diamond_title' => $diamondTitle,
            'diamond_image' => $diamondImage,
            'diamond_price' => $diamondPrice,
            'metal' => $selection['metal'],
            'band_claw_metal' => $selection['band_claw_metal'],
            'delivery_option' => $selection['delivery_option'],
        ];
    }

    cart_save($cart);
    if ($inventoryStatus['tracked'] && $quantity < $requestedQuantity) {
        return ['ok' => true, 'message' => product_inventory_unavailable_message($inventoryStatus, $quantity) . ' Added available quantity to cart.'];
    }

    return ['ok' => true, 'message' => 'Added to cart.'];
}

function cart_update_items(array $quantities): array
{
    $state = cart_state();
    $nextItems = [];
    $allocations = [];
    $messages = [];

    foreach ($state['items'] as $line) {
        $quantity = clean_int($quantities[$line['key']] ?? $line['quantity'], 0, 99);
        if ($quantity <= 0) {
            continue;
        }

        $allowedQuantity = $quantity;
        if (!empty($line['inventory_tracked'])) {
            $stockKey = (string) ($line['inventory_stock_key'] ?? '');
            $availableTotal = clean_int($line['inventory_quantity'] ?? 0, 0, 1000000);
            $allocated = $allocations[$stockKey] ?? 0;
            $remaining = max(0, $availableTotal - $allocated);

            if ($remaining <= 0) {
                $allowedQuantity = 0;
            } elseif ($quantity > $remaining) {
                $allowedQuantity = $remaining;
            }

            $allocations[$stockKey] = $allocated + $allowedQuantity;

            if ($allowedQuantity !== $quantity) {
                $messages[] = product_inventory_unavailable_message([
                    'tracked' => true,
                    'label' => trim((string) (($line['product']['name'] ?? 'This item') . ((string) ($line['metal_label'] ?? '') !== '' ? ' - ' . (string) ($line['metal_label'] ?? '') : ''))),
                    'quantity' => $remaining,
                ], $remaining > 0 ? $remaining : null);
            }
        }

        if ($allowedQuantity <= 0) {
            continue;
        }

        $nextItems[] = [
            'product_id' => (string) ($line['product']['id'] ?? ''),
            'quantity' => $allowedQuantity,
            'color' => $line['color'],
            'size' => $line['size'],
            'diamond_shape' => $line['diamond_shape'] ?? '',
            'diamond_id' => $line['diamond_id'] ?? '',
            'diamond_title' => $line['diamond_title'] ?? '',
            'diamond_image' => $line['diamond_image'] ?? '',
            'diamond_price' => $line['diamond_price'] ?? 0,
            'metal' => $line['metal'] ?? '',
            'band_claw_metal' => $line['band_claw_metal'] ?? '',
            'delivery_option' => $line['delivery_option'] ?? '',
        ];
    }

    $cart = cart_session_data();
    $cart['items'] = $nextItems;
    cart_save($cart);

    if ($messages !== []) {
        return ['ok' => false, 'message' => $messages[0]];
    }

    return ['ok' => true, 'message' => 'Cart updated.'];
}

function cart_remove_item(string $key): void
{
    $state = cart_state();
    $cart = cart_session_data();
    $cart['items'] = [];

    foreach ($state['items'] as $line) {
        if ($line['key'] === $key) {
            continue;
        }
        $cart['items'][] = [
            'product_id' => (string) ($line['product']['id'] ?? ''),
            'quantity' => $line['quantity'],
            'color' => $line['color'],
            'size' => $line['size'],
            'diamond_shape' => $line['diamond_shape'] ?? '',
            'diamond_id' => $line['diamond_id'] ?? '',
            'diamond_title' => $line['diamond_title'] ?? '',
            'diamond_image' => $line['diamond_image'] ?? '',
            'diamond_price' => $line['diamond_price'] ?? 0,
            'metal' => $line['metal'] ?? '',
            'band_claw_metal' => $line['band_claw_metal'] ?? '',
            'delivery_option' => $line['delivery_option'] ?? '',
        ];
    }

    cart_save($cart);
}

function cart_apply_coupon(string $code): array
{
    $code = strtoupper(clean_string($code, 40));
    $state = cart_state();
    $check = coupon_details_for_subtotal($code, $state['subtotal']);
    if (!($check['valid'] ?? false)) {
        return ['ok' => false, 'message' => $check['message'] ?? 'Unable to apply coupon.'];
    }

    $cart = cart_session_data();
    $cart['coupon_code'] = $code;
    cart_save($cart);
    return ['ok' => true, 'message' => $check['message'] ?? 'Coupon applied.'];
}

function cart_clear_coupon(): void
{
    $cart = cart_session_data();
    $cart['coupon_code'] = '';
    cart_save($cart);
}

function cart_clear(): void
{
    cart_remote_clear();
    unset($_SESSION['store_cart']);
}

function customer_orders(array $customer): array
{
    $email = strtolower((string) ($customer['email'] ?? ''));
    $orders = array_values(array_filter(site_content()['orders']['items'] ?? [], static function (array $order) use ($email): bool {
        return strtolower((string) ($order['customer_email'] ?? '')) === $email;
    }));

    usort($orders, static function (array $left, array $right): int {
        return strcmp((string) ($right['placed_at'] ?? ''), (string) ($left['placed_at'] ?? ''));
    });

    return $orders;
}

function customer_order_by_id(array $customer, string $orderId): ?array
{
    $needle = strtolower(clean_string($orderId, 80));
    if ($needle === '') {
        return null;
    }

    foreach (customer_orders($customer) as $order) {
        if (strtolower((string) ($order['id'] ?? '')) === $needle) {
            return $order;
        }
    }

    return null;
}

function customer_update_profile(array $input): array
{
    $customer = current_customer();
    if ($customer === null) {
        return ['ok' => false, 'message' => 'Please sign in to update your profile.'];
    }

    $name = clean_string($input['name'] ?? ($customer['name'] ?? ''), 120);
    $phone = clean_string($input['phone'] ?? ($customer['phone'] ?? ''), 40);
    $city = clean_string($input['city'] ?? ($customer['city'] ?? ''), 80);
    $state = clean_string($input['state'] ?? ($customer['state'] ?? ''), 80);
    $country = clean_string($input['country'] ?? ($customer['country'] ?? 'India'), 80);
    $postal = clean_string($input['postal_code'] ?? ($customer['postal_code'] ?? ''), 20);
    $address1 = clean_multiline($input['address_line_1'] ?? ($customer['address_line_1'] ?? ''), 160);
    $address2 = clean_multiline($input['address_line_2'] ?? ($customer['address_line_2'] ?? ''), 160);

    if ($name === '' || $phone === '') {
        return ['ok' => false, 'message' => 'Name and phone are required.'];
    }

    $hasAddressData = $address1 !== '' || $address2 !== '' || $city !== '' || $state !== '' || $postal !== '';
    if ($hasAddressData && ($address1 === '' || $city === '' || $state === '' || $postal === '' || $country === '')) {
        return ['ok' => false, 'message' => 'Complete the full address block or leave it empty.'];
    }

    $currentPassword = (string) ($input['current_password'] ?? '');
    $newPassword = (string) ($input['new_password'] ?? '');
    $confirmPassword = (string) ($input['confirm_password'] ?? '');
    $wantsPasswordChange = $currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '';

    $passwordHash = (string) ($customer['password_hash'] ?? '');
    if ($wantsPasswordChange) {
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return ['ok' => false, 'message' => 'Complete all password fields to change your password.'];
        }

        if ($passwordHash === '' || !password_verify($currentPassword, $passwordHash)) {
            return ['ok' => false, 'message' => 'Current password is incorrect.'];
        }

        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'New password must be at least 8 characters.'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['ok' => false, 'message' => 'New password and confirmation do not match.'];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    $update = with_customer_content_update((string) $customer['id'], static function (array $entry) use (
        $name,
        $phone,
        $city,
        $state,
        $country,
        $postal,
        $address1,
        $address2,
        $passwordHash
    ): array {
        $entry['name'] = $name;
        $entry['phone'] = $phone;
        $entry['city'] = $city;
        $entry['state'] = $state;
        $entry['country'] = $country;
        $entry['postal_code'] = $postal;
        $entry['address_line_1'] = $address1;
        $entry['address_line_2'] = $address2;
        $entry['password_hash'] = $passwordHash;
        return $entry;
    });

    if (!($update['ok'] ?? false)) {
        return $update;
    }

    return [
        'ok' => true,
        'message' => $wantsPasswordChange ? 'Profile and password updated.' : 'Profile updated.',
        'customer' => $update['customer'] ?? null,
    ];
}

function with_order_content_update(string $orderId, callable $updater): array
{
    $content = site_content();
    foreach ($content['orders']['items'] ?? [] as $index => $order) {
        if ((string) ($order['id'] ?? '') !== $orderId) {
            continue;
        }

        $updatedOrder = $updater($order);
        if (!is_array($updatedOrder)) {
            return ['ok' => false, 'message' => 'Order update failed.'];
        }

        $content['orders']['items'][$index] = array_merge($order, $updatedOrder);
        save_site_content($content);

        return [
            'ok' => true,
            'order' => $content['orders']['items'][$index],
        ];
    }

    return ['ok' => false, 'message' => 'Order was not found.'];
}

function order_customer_request_label(string $type): string
{
    return match (strtolower($type)) {
        'cancel' => 'Cancellation Requested',
        'return' => 'Return Requested',
        default => '',
    };
}

function order_customer_request_status_label(string $type, string $status): string
{
    $type = strtolower($type);
    $status = strtolower($status);

    return match ($status) {
        'approved' => $type === 'cancel' ? 'Cancellation Approved' : ($type === 'return' ? 'Return Approved' : 'Request Approved'),
        'rejected' => $type === 'cancel' ? 'Cancellation Rejected' : ($type === 'return' ? 'Return Rejected' : 'Request Rejected'),
        'completed' => $type === 'cancel' ? 'Cancellation Completed' : ($type === 'return' ? 'Return Completed' : 'Request Completed'),
        default => order_customer_request_label($type),
    };
}

function order_customer_request_summary(array $order): ?array
{
    $requestType = strtolower(clean_string($order['customer_request_type'] ?? '', 20));
    if (!in_array($requestType, ['cancel', 'return'], true)) {
        return null;
    }

    $requestStatus = strtolower(clean_string($order['customer_request_status'] ?? 'pending', 20));
    $requestedAt = clean_string($order['customer_request_requested_at'] ?? '', 40);
    $requestedAtFormatted = $requestedAt !== '' && strtotime($requestedAt) !== false
        ? date('d M Y, h:i A', strtotime($requestedAt))
        : $requestedAt;
    $resolvedAt = clean_string($order['customer_request_resolved_at'] ?? '', 40);
    $resolvedAtFormatted = $resolvedAt !== '' && strtotime($resolvedAt) !== false
        ? date('d M Y, h:i A', strtotime($resolvedAt))
        : $resolvedAt;
    $reason = clean_multiline($order['customer_request_reason'] ?? '', 500);

    return [
        'type' => $requestType,
        'status' => $requestStatus !== '' ? $requestStatus : 'pending',
        'label' => order_customer_request_status_label($requestType, $requestStatus),
        'requested_at' => $requestedAt,
        'requested_at_formatted' => $requestedAtFormatted,
        'resolved_at' => $resolvedAt,
        'resolved_at_formatted' => $resolvedAtFormatted,
        'reason' => $reason,
    ];
}

function order_available_customer_action(array $order): ?array
{
    if (order_customer_request_summary($order) !== null) {
        return null;
    }

    $status = strtolower(clean_string($order['status'] ?? 'pending', 40));
    return match (true) {
        in_array($status, ['pending', 'processing'], true) => [
            'type' => 'cancel',
            'label' => 'Request Cancellation',
            'headline' => 'Request Cancellation',
            'description' => 'Send a cancellation request before the order is fully dispatched.',
            'button_label' => 'Send Cancellation Request',
            'reason_label' => 'Why are you cancelling?',
        ],
        $status === 'completed' => [
            'type' => 'return',
            'label' => 'Request Return',
            'headline' => 'Request Return',
            'description' => 'Send a return request with the reason so the team can review the order.',
            'button_label' => 'Send Return Request',
            'reason_label' => 'Why do you want to return this order?',
        ],
        default => null,
    };
}

function order_presenter_data(array $order, array $customer): array
{
    $orderItems = array_values(array_filter($order['items'] ?? [], 'is_array'));
    $subtotalValue = money_value((string) ($order['subtotal'] ?? ''));
    if ($subtotalValue <= 0 && $orderItems !== []) {
        $subtotalValue = array_reduce($orderItems, static function (float $carry, array $line): float {
            $lineTotal = money_value((string) ($line['line_total'] ?? ''));
            if ($lineTotal > 0) {
                return $carry + $lineTotal;
            }

            return $carry + (money_value((string) ($line['price'] ?? '0')) * max(1, clean_int($line['quantity'] ?? 1, 1, 99)));
        }, 0.0);
    }

    $discountValue = money_value((string) ($order['discount_amount'] ?? ''));
    $shippingRaw = (string) ($order['shipping_amount'] ?? '');
    $shippingValue = strtolower(trim($shippingRaw)) === 'free' ? 0.0 : money_value($shippingRaw);
    $totalValue = money_value((string) ($order['total'] ?? ''));
    if ($totalValue <= 0) {
        $totalValue = max(0, $subtotalValue - $discountValue + $shippingValue);
    }

    $subtotalLabel = $subtotalValue > 0 ? money_format($subtotalValue) : (string) (($order['subtotal'] ?? '') !== '' ? $order['subtotal'] : money_format($totalValue));
    $discountLabel = $discountValue > 0 ? money_format($discountValue) : money_format(0);
    $shippingLabel = $shippingRaw !== '' ? (strtolower(trim($shippingRaw)) === 'free' ? 'Free' : $shippingRaw) : ($orderItems !== [] ? money_format(0) : 'Included');
    $totalLabel = (string) (($order['total'] ?? '') !== '' ? $order['total'] : money_format($totalValue));
    $itemCount = clean_int($order['item_count'] ?? count($orderItems), 0, 999);
    if ($itemCount === 0 && $orderItems !== []) {
        $itemCount = count($orderItems);
    }

    $shippingAddress = is_array($order['shipping_address'] ?? null) ? $order['shipping_address'] : [];
    $addressLines = array_values(array_filter([
        clean_string($shippingAddress['address_line_1'] ?? '', 160),
        clean_string($shippingAddress['address_line_2'] ?? '', 160),
        trim(implode(', ', array_filter([
            clean_string($shippingAddress['city'] ?? '', 80),
            clean_string($shippingAddress['state'] ?? '', 80),
        ]))),
        trim(implode(' ', array_filter([
            clean_string($shippingAddress['postal_code'] ?? '', 20),
            clean_string($shippingAddress['country'] ?? '', 80),
        ]))),
    ]));

    $placedAtRaw = clean_string($order['placed_at'] ?? '', 40);
    $placedAtFormatted = $placedAtRaw !== '' && strtotime($placedAtRaw) !== false
        ? date('d M Y, h:i A', strtotime($placedAtRaw))
        : $placedAtRaw;
    $paymentMethodLabel = strtolower((string) ($order['payment_method'] ?? 'online')) === 'cash' ? 'Cash on Delivery' : 'Online Payment';
    $paymentStatusLabel = ucfirst(strtolower((string) ($order['payment_status'] ?? 'pending')));
    $statusLabel = ucfirst(strtolower((string) ($order['status'] ?? 'processing')));
    $requestSummary = order_customer_request_summary($order);

    return [
        'order' => $order,
        'customer' => $customer,
        'items' => $orderItems,
        'subtotal_label' => $subtotalLabel,
        'discount_label' => $discountLabel,
        'shipping_label' => $shippingLabel,
        'total_label' => $totalLabel,
        'item_count' => $itemCount,
        'address_lines' => $addressLines,
        'placed_at_raw' => $placedAtRaw,
        'placed_at_formatted' => $placedAtFormatted,
        'payment_method_label' => $paymentMethodLabel,
        'payment_status_label' => $paymentStatusLabel,
        'status_label' => $statusLabel,
        'request_summary' => $requestSummary,
        'available_action' => order_available_customer_action($order),
        'invoice_file_name' => content_slug((string) ($order['id'] ?? 'invoice'), 'invoice') . '.pdf',
    ];
}

function customer_request_order_action(string $orderId, string $requestType, string $reason): array
{
    $customer = current_customer();
    if ($customer === null) {
        return ['ok' => false, 'message' => 'Please sign in to manage your orders.'];
    }

    $requestType = strtolower(clean_string($requestType, 20));
    if (!in_array($requestType, ['cancel', 'return'], true)) {
        return ['ok' => false, 'message' => 'This order action is not available.'];
    }

    $reason = clean_multiline($reason, 500);
    if ($reason === '' || strlen($reason) < 8) {
        return ['ok' => false, 'message' => 'Please add a short reason for your request.'];
    }

    $order = customer_order_by_id($customer, $orderId);
    if ($order === null) {
        return ['ok' => false, 'message' => 'Order was not found for this account.'];
    }

    $availableAction = order_available_customer_action($order);
    if ($availableAction === null || ($availableAction['type'] ?? '') !== $requestType) {
        return ['ok' => false, 'message' => 'This order can no longer accept that request.'];
    }

    $requestedAt = date('Y-m-d H:i');
    $noteLine = 'Customer requested ' . ($requestType === 'cancel' ? 'cancellation' : 'return') . ' on ' . $requestedAt . ': ' . str_replace("\n", ' ', $reason);

    $update = with_order_content_update((string) ($order['id'] ?? ''), static function (array $entry) use ($requestType, $reason, $requestedAt, $noteLine): array {
        $entry['customer_request_type'] = $requestType;
        $entry['customer_request_status'] = 'pending';
        $entry['customer_request_reason'] = $reason;
        $entry['customer_request_requested_at'] = $requestedAt;
        $entry['customer_request_resolved_at'] = '';
        $existingNotes = trim((string) ($entry['notes'] ?? ''));
        $entry['notes'] = $existingNotes !== '' ? $existingNotes . "\n" . $noteLine : $noteLine;
        return $entry;
    });

    if (!($update['ok'] ?? false)) {
        return $update;
    }

    return [
        'ok' => true,
        'message' => $requestType === 'cancel' ? 'Cancellation request sent.' : 'Return request sent.',
        'order' => $update['order'] ?? null,
    ];
}

function pdf_escape_text(string $text): string
{
    $text = str_replace('£', 'GBP ', $text);
    $text = preg_replace('/[^\x20-\x7E\n\t]/', '?', $text) ?? $text;
    return str_replace(
        ["\\", "(", ")"],
        ["\\\\", "\\(", "\\)"],
        preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? ''
    );
}

function pdf_wrap_text(string $text, int $width = 88): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = [];
    foreach (explode("\n", $text) as $segment) {
        $segment = trim($segment);
        if ($segment === '') {
            $lines[] = '';
            continue;
        }

        $wrapped = wordwrap($segment, $width, "\n", true);
        foreach (explode("\n", $wrapped) as $wrappedLine) {
            $lines[] = $wrappedLine;
        }
    }

    return $lines === [] ? [''] : $lines;
}

function simple_pdf_document(array $pages, string $title = 'Document'): string
{
    $objects = [];
    $pageObjectNumbers = [];
    $contentObjectNumbers = [];
    $fontObjectNumber = 3;
    $infoObjectNumber = 4;
    $nextObjectNumber = 5;

    foreach ($pages as $pageIndex => $pageLines) {
        $pageObjectNumbers[$pageIndex] = $nextObjectNumber++;
        $contentObjectNumbers[$pageIndex] = $nextObjectNumber++;
    }

    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $kids = implode(' ', array_map(static fn (int $number): string => $number . ' 0 R', $pageObjectNumbers));
    $objects[2] = "<< /Type /Pages /Kids [ {$kids} ] /Count " . count($pageObjectNumbers) . " >>";
    $objects[$fontObjectNumber] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[$infoObjectNumber] = "<< /Title (" . pdf_escape_text($title) . ") >>";

    foreach ($pages as $pageIndex => $pageLines) {
        $content = "BT\n/F1 11 Tf\n50 790 Td\n14 TL\n";
        foreach ($pageLines as $line) {
            $content .= '(' . pdf_escape_text($line) . ") Tj\nT*\n";
        }
        $content .= "ET";

        $contentObjectNumber = $contentObjectNumbers[$pageIndex];
        $pageObjectNumber = $pageObjectNumbers[$pageIndex];
        $objects[$contentObjectNumber] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
        $objects[$pageObjectNumber] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObjectNumber} 0 R >> >> /Contents {$contentObjectNumber} 0 R >>";
    }

    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $count = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($count + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $count; $i++) {
        $offset = $offsets[$i] ?? 0;
        $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size " . ($count + 1) . " /Root 1 0 R /Info {$infoObjectNumber} 0 R >>\n";
    $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

function order_invoice_pdf_bytes(array $presented): string
{
    $order = $presented['order'];
    $customer = $presented['customer'];
    $lines = [
        'AZURONN ORDER INVOICE',
        str_repeat('=', 84),
        'Invoice Number: ' . (string) ($order['id'] ?? ''),
        'Placed At: ' . (string) ($presented['placed_at_formatted'] ?? ''),
        'Order Status: ' . (string) ($presented['status_label'] ?? ''),
        'Payment Status: ' . (string) ($presented['payment_status_label'] ?? ''),
        'Payment Method: ' . (string) ($presented['payment_method_label'] ?? ''),
    ];

    if (($order['payment_reference'] ?? '') !== '') {
        $lines[] = 'Payment Reference: ' . (string) $order['payment_reference'];
    }

    $lines[] = '';
    $lines[] = 'Customer';
    $lines[] = str_repeat('-', 84);
    $lines[] = 'Name: ' . (string) (($order['customer_name'] ?? '') !== '' ? $order['customer_name'] : ($customer['name'] ?? ''));
    $lines[] = 'Email: ' . (string) (($order['customer_email'] ?? '') !== '' ? $order['customer_email'] : ($customer['email'] ?? ''));
    $lines[] = 'Phone: ' . (string) (($order['customer_phone'] ?? '') !== '' ? $order['customer_phone'] : ($customer['phone'] ?? ''));

    $lines[] = '';
    $lines[] = 'Shipping Address';
    $lines[] = str_repeat('-', 84);
    if (($presented['address_lines'] ?? []) === []) {
        $lines[] = 'No shipping address stored.';
    } else {
        foreach ($presented['address_lines'] as $line) {
            foreach (pdf_wrap_text((string) $line) as $wrappedLine) {
                $lines[] = $wrappedLine;
            }
        }
    }

    $lines[] = '';
    $lines[] = 'Items';
    $lines[] = str_repeat('-', 84);
    if (($presented['items'] ?? []) === []) {
        $lines[] = 'Legacy order record: line-item detail was not stored for this order.';
    } else {
        foreach ($presented['items'] as $index => $line) {
            $headline = ($index + 1) . '. ' . (string) ($line['product_name'] ?? 'Item');
            foreach (pdf_wrap_text($headline) as $wrappedLine) {
                $lines[] = $wrappedLine;
            }
            $lines[] = '   Variant: ' . line_variant_summary($line);
            if (money_value((string) ($line['delivery_surcharge'] ?? '0')) > 0) {
                $lines[] = '   Priority Delivery Upgrade: ' . (string) ($line['delivery_surcharge'] ?? money_format(0));
            }
            $lines[] = '   Unit Price: ' . (string) ($line['price'] ?? money_format(0)) . '   Qty: ' . (string) ($line['quantity'] ?? 1) . '   Line Total: ' . (string) ($line['line_total'] ?? money_format(0));
            $lines[] = '';
        }
    }

    $lines[] = 'Totals';
    $lines[] = str_repeat('-', 84);
    $lines[] = 'Items: ' . (string) ($presented['item_count'] ?? 0);
    $lines[] = 'Subtotal: ' . (string) ($presented['subtotal_label'] ?? money_format(0));
    $lines[] = 'Discount: -' . (string) ($presented['discount_label'] ?? money_format(0));
    $lines[] = 'Shipping: ' . (string) ($presented['shipping_label'] ?? money_format(0));
    if (($order['coupon_code'] ?? '') !== '') {
        $lines[] = 'Coupon: ' . (string) $order['coupon_code'];
    }
    $lines[] = 'Grand Total: ' . (string) ($presented['total_label'] ?? money_format(0));

    $requestSummary = $presented['request_summary'] ?? null;
    if (is_array($requestSummary)) {
        $lines[] = '';
        $lines[] = 'Customer Request';
        $lines[] = str_repeat('-', 84);
        $lines[] = (string) ($requestSummary['label'] ?? 'Request Submitted');
        if (($requestSummary['requested_at_formatted'] ?? '') !== '') {
            $lines[] = 'Requested At: ' . (string) $requestSummary['requested_at_formatted'];
        }
        if (($requestSummary['reason'] ?? '') !== '') {
            foreach (pdf_wrap_text('Reason: ' . (string) $requestSummary['reason']) as $wrappedLine) {
                $lines[] = $wrappedLine;
            }
        }
    }

    if (($order['notes'] ?? '') !== '') {
        $lines[] = '';
        $lines[] = 'Notes';
        $lines[] = str_repeat('-', 84);
        foreach (pdf_wrap_text((string) $order['notes']) as $wrappedLine) {
            $lines[] = $wrappedLine;
        }
    }

    $pages = array_chunk($lines, 48);
    return simple_pdf_document($pages, 'Azuronn Invoice ' . (string) ($order['id'] ?? ''));
}

function customer_saved_addresses(array $customer): array
{
    $addresses = array_values(array_filter($customer['saved_addresses'] ?? [], 'is_array'));
    usort($addresses, static function (array $left, array $right): int {
        return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    });
    return $addresses;
}

function customer_primary_saved_address(array $customer): ?array
{
    $addresses = customer_saved_addresses($customer);
    return $addresses[0] ?? null;
}

function customer_has_wishlist_product(?array $customer, string $productId): bool
{
    $productId = wishlist_product_id($productId);
    if ($customer === null || $productId === '') {
        return false;
    }

    return in_array($productId, $customer['wishlist_product_ids'] ?? [], true);
}

function customer_wishlist_products(array $customer): array
{
    $products = [];
    foreach (($customer['wishlist_product_ids'] ?? []) as $productId) {
        $product = product_by_id((string) $productId);
        if ($product !== null && strtolower((string) ($product['status'] ?? 'active')) === 'active') {
            $products[] = $product;
        }
    }
    return $products;
}

function customer_wishlist_count(?array $customer = null): int
{
    $customer = $customer ?? current_customer();
    if ($customer === null) {
        return 0;
    }

    return count(customer_wishlist_products($customer));
}

function wishlist_add_product_to_cart(string $productId): array
{
    $productId = wishlist_product_id($productId);
    $product = product_by_id($productId);
    if ($product === null) {
        return ['ok' => false, 'message' => 'Product is no longer available.'];
    }

    $options = product_option_data($product);
    return cart_add_item(
        $productId,
        1,
        [
            'color' => (string) ($options['colors'][0] ?? ($product['color'] ?? '')),
            'size' => (string) ($options['sizes'][0] ?? 'Standard'),
            'diamond_shape' => (string) (($options['diamond_shapes'][0]['value'] ?? '')),
            'metal' => (string) (($options['metal_options'][0]['value'] ?? '')),
            'band_claw_metal' => (string) (($options['band_claw_metal_options'][0]['value'] ?? '')),
            'delivery_option' => (string) (($options['delivery_options'][0]['value'] ?? '')),
        ]
    );
}

function customer_toggle_wishlist(string $productId): array
{
    $productId = wishlist_product_id($productId);
    $customer = current_customer();
    if ($customer === null) {
        return ['ok' => false, 'message' => 'Please sign in to use your wishlist.'];
    }

    $product = product_by_id($productId);
    if ($product === null || strtolower((string) ($product['status'] ?? 'active')) !== 'active') {
        return ['ok' => false, 'message' => 'Product is not available.'];
    }

    $wishlistIds = $customer['wishlist_product_ids'] ?? [];
    $isSaved = in_array($productId, $wishlistIds, true);
    if ($isSaved) {
        $wishlistIds = array_values(array_filter($wishlistIds, static fn (string $id): bool => $id !== $productId));
    } else {
        $wishlistIds[] = $productId;
    }

    $update = with_customer_content_update((string) $customer['id'], static function (array $entry) use ($wishlistIds): array {
        $entry['wishlist_product_ids'] = $wishlistIds;
        return $entry;
    });

    if (!($update['ok'] ?? false)) {
        return $update;
    }

    return [
        'ok' => true,
        'saved' => !$isSaved,
        'message' => !$isSaved ? 'Added to wishlist.' : 'Removed from wishlist.',
        'customer' => $update['customer'] ?? null,
    ];
}

function customer_remove_wishlist_product(string $productId): array
{
    $productId = wishlist_product_id($productId);
    $customer = current_customer();
    if ($customer === null) {
        return ['ok' => false, 'message' => 'Please sign in to manage wishlist items.'];
    }

    $wishlistIds = array_values(array_filter($customer['wishlist_product_ids'] ?? [], static fn (string $id): bool => $id !== $productId));
    $update = with_customer_content_update((string) $customer['id'], static function (array $entry) use ($wishlistIds): array {
        $entry['wishlist_product_ids'] = $wishlistIds;
        return $entry;
    });

    if (!($update['ok'] ?? false)) {
        return $update;
    }

    return ['ok' => true, 'message' => 'Wishlist item removed.', 'customer' => $update['customer'] ?? null];
}

function customer_save_address(array $input, ?int $addressIndex = null): array
{
    $customer = current_customer();
    if ($customer === null) {
        return ['ok' => false, 'message' => 'Please sign in to save addresses.'];
    }

    $label = clean_string($input['label'] ?? '', 80);
    $recipientName = clean_string($input['recipient_name'] ?? ($customer['name'] ?? ''), 120);
    $phone = clean_string($input['phone'] ?? ($customer['phone'] ?? ''), 40);
    $address1 = clean_multiline($input['address_line_1'] ?? '', 160);
    $address2 = clean_multiline($input['address_line_2'] ?? '', 160);
    $city = clean_string($input['city'] ?? '', 80);
    $state = clean_string($input['state'] ?? '', 80);
    $postal = clean_string($input['postal_code'] ?? '', 20);
    $country = clean_string($input['country'] ?? 'India', 80);

    if ($label === '' || $recipientName === '' || $phone === '' || $address1 === '' || $city === '' || $state === '' || $postal === '' || $country === '') {
        return ['ok' => false, 'message' => 'Complete all required address fields.'];
    }

    $savedAddresses = array_values(array_filter($customer['saved_addresses'] ?? [], 'is_array'));
    $addressPayload = [
        'id' => clean_string($input['id'] ?? '', 80),
        'label' => $label,
        'recipient_name' => $recipientName,
        'phone' => $phone,
        'address_line_1' => $address1,
        'address_line_2' => $address2,
        'city' => $city,
        'state' => $state,
        'postal_code' => $postal,
        'country' => $country,
    ];

    if ($addressIndex !== null && isset($savedAddresses[$addressIndex])) {
        $savedAddresses[$addressIndex] = array_merge($savedAddresses[$addressIndex], $addressPayload);
    } else {
        $savedAddresses[] = $addressPayload;
    }

    $update = with_customer_content_update((string) $customer['id'], static function (array $entry) use ($savedAddresses): array {
        $entry['saved_addresses'] = $savedAddresses;
        return $entry;
    });

    if (!($update['ok'] ?? false)) {
        return $update;
    }

    return [
        'ok' => true,
        'message' => $addressIndex !== null ? 'Saved address updated.' : 'Address saved.',
        'customer' => $update['customer'] ?? null,
    ];
}

function customer_delete_address(int $addressIndex): array
{
    $customer = current_customer();
    if ($customer === null) {
        return ['ok' => false, 'message' => 'Please sign in to manage addresses.'];
    }

    $savedAddresses = array_values(array_filter($customer['saved_addresses'] ?? [], 'is_array'));
    if (!isset($savedAddresses[$addressIndex])) {
        return ['ok' => false, 'message' => 'Saved address was not found.'];
    }

    array_splice($savedAddresses, $addressIndex, 1);
    $update = with_customer_content_update((string) $customer['id'], static function (array $entry) use ($savedAddresses): array {
        $entry['saved_addresses'] = $savedAddresses;
        return $entry;
    });

    if (!($update['ok'] ?? false)) {
        return $update;
    }

    return ['ok' => true, 'message' => 'Address removed.', 'customer' => $update['customer'] ?? null];
}

function next_order_id(array $orders): string
{
    $max = 24000;
    foreach ($orders as $order) {
        if (preg_match('/ORD-(\d+)/i', (string) ($order['id'] ?? ''), $match)) {
            $max = max($max, (int) $match[1]);
        }
    }
    return 'ORD-' . ($max + 1);
}

function checkout_place_order(array $input): array
{
    $customer = current_customer();
    if ($customer === null) {
        return ['ok' => false, 'message' => 'Please sign in before checkout.'];
    }

    $cart = cart_state();
    if ($cart['items'] === []) {
        return ['ok' => false, 'message' => 'Your cart is empty.'];
    }

    $fullName = clean_string($input['full_name'] ?? $customer['name'] ?? '', 120);
    $phone = clean_string($input['phone'] ?? $customer['phone'] ?? '', 40);
    $address1 = clean_multiline($input['address_line_1'] ?? $customer['address_line_1'] ?? '', 160);
    $address2 = clean_multiline($input['address_line_2'] ?? $customer['address_line_2'] ?? '', 160);
    $city = clean_string($input['city'] ?? $customer['city'] ?? '', 80);
    $state = clean_string($input['state'] ?? $customer['state'] ?? '', 80);
    $postal = clean_string($input['postal_code'] ?? $customer['postal_code'] ?? '', 20);
    $country = clean_string($input['country'] ?? $customer['country'] ?? 'India', 80);
    $paymentMethod = clean_string($input['payment_method'] ?? 'online', 20);
    $notes = clean_multiline($input['notes'] ?? '', 500);

    if ($fullName === '' || $phone === '' || $address1 === '' || $city === '' || $state === '' || $postal === '' || $country === '') {
        return ['ok' => false, 'message' => 'Complete all required shipping fields.'];
    }

    if (!in_array($paymentMethod, ['online', 'cash'], true)) {
        $paymentMethod = 'online';
    }

    return site_content_with_lock(static function () use (
        $address1,
        $address2,
        $cart,
        $city,
        $country,
        $customer,
        $fullName,
        $notes,
        $paymentMethod,
        $phone,
        $postal,
        $state
    ): array {
        $content = load_site_content(true);
        $GLOBALS['azuronn_site_content_cache'] = $content;
        $cart = cart_state();
        if ($cart['items'] === []) {
            return ['ok' => false, 'message' => 'Your cart is empty.'];
        }

        $stockAllocations = [];
        foreach ($cart['items'] as $line) {
            $stockKey = (string) ($line['inventory_stock_key'] ?? '');
            if (empty($line['inventory_tracked']) || $stockKey === '') {
                continue;
            }

            $stockAllocations[$stockKey] = ($stockAllocations[$stockKey] ?? 0) + clean_int($line['quantity'] ?? 0, 0, 99);
            $availableQuantity = clean_int($line['inventory_quantity'] ?? 0, 0, 1000000);
            if ($stockAllocations[$stockKey] > $availableQuantity) {
                return [
                    'ok' => false,
                    'message' => product_inventory_unavailable_message([
                        'tracked' => true,
                        'label' => trim((string) (($line['product']['name'] ?? 'This item') . ((string) ($line['metal_label'] ?? '') !== '' ? ' - ' . (string) ($line['metal_label'] ?? '') : ''))),
                        'quantity' => $availableQuantity,
                    ], $availableQuantity > 0 ? $availableQuantity : null) . ' Please update your cart and try again.',
                ];
            }
        }

        foreach ($cart['items'] as $line) {
            $productId = clean_string((string) ($line['product']['id'] ?? ''), 80);
            $productIndex = null;
            foreach ($content['products']['items'] as $index => $product) {
                if ((string) ($product['id'] ?? '') === $productId) {
                    $productIndex = $index;
                    break;
                }
            }
            if ($productIndex === null) {
                return ['ok' => false, 'message' => 'One of the items in your cart is no longer available.'];
            }

            $selection = [
                'color' => $line['color'] ?? '',
                'size' => $line['size'] ?? '',
                'diamond_shape' => $line['diamond_shape'] ?? '',
                'metal' => $line['metal'] ?? '',
                'band_claw_metal' => $line['band_claw_metal'] ?? '',
                'delivery_option' => $line['delivery_option'] ?? '',
            ];
            $inventoryTarget = product_inventory_target($content['products']['items'][$productIndex], $selection);
            $decrementBy = clean_int($line['quantity'] ?? 0, 0, 99);
            if ($decrementBy <= 0 || empty($inventoryTarget['tracked'])) {
                continue;
            }

            if (($inventoryTarget['scope'] ?? 'product') === 'metal') {
                foreach ($content['products']['items'][$productIndex]['metal_variations'] as &$variation) {
                    if (strcasecmp((string) ($variation['metal'] ?? ''), (string) ($inventoryTarget['metal'] ?? '')) !== 0) {
                        continue;
                    }
                    $currentQuantity = clean_int($variation['inventory_quantity'] ?? 0, 0, 1000000);
                    if ($currentQuantity < $decrementBy) {
                        return ['ok' => false, 'message' => product_inventory_unavailable_message($inventoryTarget, $currentQuantity > 0 ? $currentQuantity : null) . ' Please update your cart and try again.'];
                    }
                    $variation['inventory_quantity'] = $currentQuantity - $decrementBy;
                    break;
                }
                unset($variation);
            } else {
                $currentQuantity = clean_int($content['products']['items'][$productIndex]['inventory_quantity'] ?? 0, 0, 1000000);
                if ($currentQuantity < $decrementBy) {
                    return ['ok' => false, 'message' => product_inventory_unavailable_message($inventoryTarget, $currentQuantity > 0 ? $currentQuantity : null) . ' Please update your cart and try again.'];
                }
                $content['products']['items'][$productIndex]['inventory_quantity'] = $currentQuantity - $decrementBy;
            }
        }

        $orderId = next_order_id($content['orders']['items'] ?? []);
        $placedAt = date('Y-m-d H:i');
        $paymentReference = $paymentMethod === 'online' ? 'AZR-' . strtoupper(bin2hex(random_bytes(3))) : '';
        $paymentStatus = $paymentMethod === 'online' ? 'paid' : 'awaiting';
        $orderStatus = $paymentMethod === 'online' ? 'processing' : 'pending';

        $orderItems = array_map(static function (array $line): array {
            return [
                'id' => $line['key'],
                'product_id' => (string) ($line['product']['id'] ?? ''),
                'product_name' => (string) ($line['product']['name'] ?? ''),
                'image' => (string) ($line['ring_media'] ?? ($line['product']['default_image'] ?? '')),
                'quantity' => $line['quantity'],
                'size' => $line['size'],
                'color' => $line['color'],
                'diamond_shape' => $line['diamond_shape'],
                'diamond_shape_label' => $line['diamond_shape_label'],
                'diamond_id' => $line['diamond_id'],
                'diamond_title' => $line['diamond_title'],
                'diamond_price' => $line['diamond_price'] > 0 ? $line['diamond_price_label'] : '',
                'metal' => $line['metal'],
                'metal_label' => $line['metal_label'],
                'band_claw_metal' => $line['band_claw_metal'],
                'band_claw_metal_label' => $line['band_claw_metal_label'],
                'delivery_option' => $line['delivery_option'],
                'delivery_label' => $line['delivery_label'],
                'delivery_surcharge' => $line['delivery_surcharge'] > 0 ? $line['delivery_surcharge_label'] : '',
                'price' => $line['unit_price_label'],
                'base_price' => $line['base_unit_price_label'],
                'line_total' => $line['line_total_label'],
            ];
        }, $cart['items']);

        $content['orders']['items'][] = [
            'id' => $orderId,
            'customer_name' => $fullName,
            'customer_email' => (string) ($customer['email'] ?? ''),
            'customer_phone' => $phone,
            'status' => $orderStatus,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'payment_reference' => $paymentReference,
            'total' => $cart['total_label'],
            'subtotal' => $cart['subtotal_label'],
            'discount_amount' => $cart['discount_label'],
            'shipping_amount' => is_string($cart['shipping_label']) ? $cart['shipping_label'] : money_format((float) $cart['shipping']),
            'coupon_code' => (string) ($cart['coupon_code'] ?? ''),
            'item_count' => (string) $cart['count'],
            'placed_at' => $placedAt,
            'shipping_address' => [
                'address_line_1' => $address1,
                'address_line_2' => $address2,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postal,
                'country' => $country,
            ],
            'customer_request_type' => '',
            'customer_request_status' => '',
            'customer_request_reason' => '',
            'customer_request_requested_at' => '',
            'customer_request_resolved_at' => '',
            'items' => $orderItems,
            'notes' => $notes !== '' ? $notes : ($paymentMethod === 'online' ? 'Secure card payment captured.' : 'Cash on delivery order placed.'),
        ];

        foreach ($content['customers']['items'] as &$entry) {
            if ((string) ($entry['id'] ?? '') !== (string) ($customer['id'] ?? '')) {
                continue;
            }

            $entry['name'] = $fullName;
            $entry['phone'] = $phone;
            $entry['city'] = $city;
            $entry['state'] = $state;
            $entry['country'] = $country;
            $entry['postal_code'] = $postal;
            $entry['address_line_1'] = $address1;
            $entry['address_line_2'] = $address2;
            $entry['last_order_at'] = $placedAt;
            $entry['total_orders'] = (string) (clean_int($entry['total_orders'] ?? 0, 0, 100000) + 1);
            $entry['total_spent'] = money_format(money_value((string) ($entry['total_spent'] ?? '0')) + (float) $cart['total']);
            break;
        }
        unset($entry);

        save_site_content($content);
        cart_clear();

        return [
            'ok' => true,
            'order' => end($content['orders']['items']),
        ];
    });
}
