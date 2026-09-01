<?php

$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;

if (!$stripeSecretKey) {
    die('Stripe secret key is not configured.');
}

\Stripe\Stripe::setApiKey($stripeSecretKey);