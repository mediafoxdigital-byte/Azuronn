<?php
declare(strict_types=1);
session_start();
$_SESSION['site_unlocked'] = true;
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
$src = file_get_contents(__DIR__ . '/admin/index.php');
foreach ([
  'admin_product_type_is_matrix','admin_product_type_is_ring','admin_canonical_attribute_type',
  'admin_select_image_or_url','admin_resolve_band_image_options','admin_build_attribute_profile_from_post',
  'admin_choice_values_from_rows','admin_add_upload_notice','admin_build_product_from_post',
  'admin_resolve_indices_from_profile','admin_handle_image_upload','admin_parse_lines','admin_export_lines',
] as $fn) {
  if (preg_match('/\nfunction ' . preg_quote($fn,'/') . '\s*\(.*?\n\}\n/s', $src, $m)) { eval('?>'.'<?php'.$m[0]); }
}
