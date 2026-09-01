<?php

include '../app/_base.php';
require_once '../vendor/autoload.php'; 
require_once '../stripe_config.php';// STRIPE

// TODO: Replace with session user after Login module is completed
$user_id = 8;

$order_id = $_GET['order_id'] ?? '';

if ($order_id === '') {
    redirect('cart.php');
}

$stm = $_db->prepare("SELECT order_id, user_id, total_amount, order_status, order_date
                     FROM orders
                     WHERE order_id = ?
                     AND user_id = ?");

$stm->execute([
    $order_id,
    $user_id
]);

$order = $stm->fetch();

if (!$order) {
    redirect('cart.php');
}

// Create Stripe Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {

    $checkoutSession = \Stripe\Checkout\Session::create([

        'mode' => 'payment',

        'line_items' => [[
            'price_data' => [
                'currency' => 'myr',

                'product_data' => [
                    'name' => 'Order #' . $order['order_id']
                ],

                'unit_amount' => (int) round(
                    $order['total_amount'] * 100
                )
            ],

            'quantity' => 1
        ]],

        'success_url' =>
            'http://localhost:8000/member/payment_success.php'
            . '?session_id={CHECKOUT_SESSION_ID}'
            . '&order_id=' . $order['order_id'],

        'cancel_url' =>
            'http://localhost:8000/member/payment.php'
            . '?order_id=' . $order['order_id']
    ]);

    header('Location: ' . $checkoutSession->url);
    exit;
}

?>

<link rel="stylesheet" href="../app/css/app.css">

<div class="order-module">

    <div class="payment-wrapper">

        <div class="payment-card">

            <h1>Payment</h1>

            <div class="payment-info">

                <div class="payment-row">
                    <span>Order ID</span>
                    <strong>
                        #<?= htmlspecialchars($order['order_id']) ?>
                    </strong>
                </div>

                <div class="payment-row">
                    <span>Order Date</span>
                    <strong>
                        <?= htmlspecialchars($order['order_date']) ?>
                    </strong>
                </div>

                <div class="payment-row">
                    <span>Order Status</span>
                    <span class="status">
                        <?= htmlspecialchars($order['order_status']) ?>
                    </span>
                </div>

            </div>

            <div class="payment-total">

                <span>Total Amount</span>

                <strong>
                    RM <?= number_format($order['total_amount'], 2) ?>
                </strong>

            </div>

            <form method="POST">

                <button
                    type="submit"
                    name="pay_now"
                    class="stripe-btn"
                >
                    Pay with Stripe
                </button>

            </form>

            <p class="payment-note">
                You will be redirected to Stripe to complete your payment securely.
            </p>

        </div>

    </div>

</div>