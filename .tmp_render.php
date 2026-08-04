<?php
// Render the admin catalog editor headlessly to inspect the actual HTML.
session_start();
$_SESSION['site_unlocked'] = true;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_USER_AGENT'] = 'harness';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/admin/?page=catalog&product_form=create';
$_GET = ['view' => 'catalog', 'product_form' => 'create'];

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin-auth.php';
$_SESSION[admin_session_key()] = [
    'logged_in' => true, 'username' => 'harness', 'name' => 'harness',
    'portal' => admin_portal_mode(), 'last_seen' => time(),
    'fingerprint' => admin_fingerprint(),
];
ob_start();
require __DIR__ . '/admin/index.php';
$html = ob_get_clean();
file_put_contents(__DIR__ . '/.tmp_render.html', $html);
echo "bytes=", strlen($html), "\n";
