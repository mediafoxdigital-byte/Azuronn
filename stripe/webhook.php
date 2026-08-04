<?php

declare(strict_types=1);

/**
 * Stripe Webhook Endpoint
 *
 * This endpoint does NOT include security.php — it must be reachable by
 * Stripe's servers without a session cookie or Coming-Soon gate.
 *
 * Configure in Stripe Dashboard → Webhooks:
 *   URL: https://yoursite.com/stripe/webhook.php
 *   Events: checkout.session.completed
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/stripe.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = (string) file_get_contents('php://input');
$sigHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if ($payload === '' || $sigHeader === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payload or signature']);
    exit;
}

$event = stripe_construct_webhook_event($payload, $sigHeader);
if ($event === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$eventType = (string) ($event['type'] ?? '');

if ($eventType === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];
    $orderId = (string) ($session['metadata']['order_id'] ?? '');
    $paymentIntentId = (string) ($session['payment_intent'] ?? '');

    if ($orderId !== '') {
        stripe_confirm_order_payment($orderId, $paymentIntentId);
    }
}

// Always return 200 so Stripe doesn't retry
http_response_code(200);
echo json_encode(['received' => true]);
