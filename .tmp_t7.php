<?php
require __DIR__ . '/.tmp_h.php';
// Simulate the browser: hidden Merchandising card's inputs are DISABLED, so
// new_price / image fields post nothing at all for a matrix category.
$_POST['product'] = [
    'name' => 'Solitaire Ring',
    'category_taxonomy' => 'engagement',
    'metal_variations_engagement_rings' => [
        ['metal' => 'Gold', 'price' => '1200', 'active' => '1'],
    ],
];
$p = admin_build_product_from_post([]);
echo "new_price=", var_export($p['new_price'], true),
     " default_image=", var_export($p['default_image'], true),
     " description=", var_export($p['description'], true), "\n";
echo "listing price_value=", product_price_value($p), "\n";
$d = product_option_data($p);
echo "metals=", json_encode(array_column($d['metal_options'] ?? [], 'label')), "\n";
