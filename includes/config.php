<?php
/**
 * config.php
 * Site-wide configuration constants.
 */
declare(strict_types=1);

require_once __DIR__ . '/content.php';

$siteContent = site_content();
$settings = $siteContent['settings'];

// Site identity
define('SITE_NAME', $settings['site_name']);
define('SITE_TAGLINE', $settings['site_tagline']);
define('SITE_URL', $settings['site_url']);

// Contact
define('STORE_ADDRESS', $settings['store_address']);
define('STORE_PHONE', $settings['store_phone']);
define('STORE_EMAIL', $settings['store_email']);

// Social links
define('SOCIAL_FACEBOOK', $settings['social']['facebook']);
define('SOCIAL_TWITTER', $settings['social']['twitter']);
define('SOCIAL_RSS', $settings['social']['rss']);
define('SOCIAL_GOOGLEPLUS', $settings['social']['googleplus']);
define('SOCIAL_YOUTUBE', $settings['social']['youtube']);

// Announcement bar
define('ANN_TEXT', $settings['announcement_text']);
define('ANN_CODE', $settings['announcement_code']);
define('ANN_CODE_URL', $settings['announcement_url']);

// Cart placeholder
define('CART_COUNT', $settings['cart_count']);
define('CART_TOTAL', $settings['cart_total']);

// Paths
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', SITE_URL);
define('SITE_LOGO_PATH', $settings['logo_path']);
define('SUPABASE_URL', supabase_project_url());
define('SUPABASE_PUBLISHABLE_KEY', supabase_publishable_key());
define('SUPABASE_ENABLED', supabase_enabled());
define('UPLOADS_ROOT_PATH', app_runtime_config_value('uploads_root_path') ?: (getenv('AZURONN_UPLOADS_ROOT') ?: (BASE_PATH . '/assets/uploads/admin')));
define('UPLOADS_PUBLIC_BASE_URL', app_runtime_config_value('uploads_public_base_url') ?: (getenv('AZURONN_UPLOADS_PUBLIC_BASE_URL') ?: '/assets/uploads/admin'));

// Admin auth
$envAdminUser = getenv('AZURONN_ADMIN_USERNAME');
$envAdminHash = getenv('AZURONN_ADMIN_PASSWORD_HASH');
$envEmployeeAdminUser = getenv('AZURONN_EMPLOYEE_ADMIN_USERNAME');
$envEmployeeAdminHash = getenv('AZURONN_EMPLOYEE_ADMIN_PASSWORD_HASH');

define('ADMIN_USERNAME', $envAdminUser !== false && $envAdminUser !== '' ? $envAdminUser : 'admin');
define('ADMIN_PASSWORD_HASH', $envAdminHash !== false && $envAdminHash !== '' ? $envAdminHash : '$2y$12$LKcq58UxxJP8tmc47zS70un4Oy3ozqWTCToecp.3BKJ88zCW4hm1S');
define('EMPLOYEE_ADMIN_USERNAME', $envEmployeeAdminUser !== false && $envEmployeeAdminUser !== '' ? $envEmployeeAdminUser : 'employee');
define('EMPLOYEE_ADMIN_PASSWORD_HASH', $envEmployeeAdminHash !== false && $envEmployeeAdminHash !== '' ? $envEmployeeAdminHash : ADMIN_PASSWORD_HASH);
define('ADMIN_IDLE_TIMEOUT', 1800);
define('ADMIN_MAX_LOGIN_ATTEMPTS', 5);
define('ADMIN_LOCKOUT_MINUTES', 15);
