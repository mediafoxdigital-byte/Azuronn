<?php

return [
    'supabase_url' => 'https://hlqaqoqqvbozxdmieaii.supabase.co',
    'supabase_publishable_key' => 'sb_publishable_xxx',
    'supabase_service_role_key' => 'eyJ...service-role...',
    'uploads_root_path' => dirname(__DIR__) . '/assets/uploads/admin',
    'uploads_public_base_url' => '/assets/uploads/admin',

    // Stripe payment gateway (redirect-based Checkout flow).
    // Get these from your Stripe Dashboard → Developers → API keys / Webhooks.
    // Use test keys (sk_test_...) for development; live keys (sk_live_...) for production.
    'stripe_secret_key'      => 'sk_test_...',
    'stripe_publishable_key' => 'pk_test_...',   // optional for redirect flow
    'stripe_webhook_secret'  => 'whsec_...',
];
