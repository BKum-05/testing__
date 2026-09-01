<?php

include '../app/_base.php';
require_once '../stripe_config.php';

// TODO: Replace with login session later
$user_id = 8;

$session_id = $_GET['session_id'] ?? '';
$order_id = $_GET['order_id'] ?? '';

if ($session_id === '' || $order_id === '') {
    redirect('cart.php');
}

try {

    // 1. Ask Stripe for the real Checkout Session
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    if ($session->payment_status !== 'paid') {
        die('Payment has not been completed.');
    }

    // 2. Verify that the order belongs to this user
    $orderStm = $_db->prepare("SELECT order_id, user_id, total_amount, order_status
                                FROM orders
                                WHERE order_id = ?
                                AND user_id = ?");

    $orderStm->execute([$order_id, $user_id]);

    $order = $orderStm->fetch();

    if (!$order) {
        die('Invalid order.');
    }

    // Verify paid amount
    $expectedAmount = (int) round($order['total_amount'] * 100);

    if ($session->amount_total != $expectedAmount) {
        die('Payment amount does not match the order total.');
    }

    // Check duplicate payment
    $checkPaymentStm = $_db->prepare("SELECT payment_id
                                    FROM payment
                                    WHERE order_id = ?
                                    AND payment_status = 'Paid'");

    $checkPaymentStm->execute([$order_id]);

    $existingPayment = $checkPaymentStm->fetch();

    if ($existingPayment) {
        die('This order has already been paid.');
    }

    if (!$existingPayment) {
        $_db->beginTransaction();
        // 3. Insert payment record
        $paymentStm = $_db->prepare("INSERT INTO payment
        (
            order_id,
            transaction_id,
            payment_method,
            amount,
            payment_status,
            paid_at,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, NOW(), NOW()) ");

        $paymentStm->execute([
            $order_id,
            $session->payment_intent,
            'Stripe',
            $order['total_amount'],
            'Paid'
        ]);

        // 4. Update order status
        $updateOrderStm = $_db->prepare("UPDATE orders
                                    SET order_status = ?
                                    WHERE order_id = ?");

        $updateOrderStm->execute(['Paid', $order_id]);

        // 5. Find current user's cart
        $cartStm = $_db->prepare("SELECT cart_id
                              FROM cart
                              WHERE user_id = ?
                              AND deleted_at IS NULL
                              LIMIT 1");

        $cartStm->execute([$user_id]);
        $cart = $cartStm->fetch();

        // 6. Clear cart items after successful payment
        if ($cart) {

            $clearCartStm = $_db->prepare("DELETE FROM cart_item
                                        WHERE cart_id = ?");

            $clearCartStm->execute([$cart['cart_id']]);
        }

        $_db->commit();
    }
} catch (Exception $e) {

    if ($_db->inTransaction()) {
        $_db->rollBack();
    }

    die('Payment processing error: ' . $e->getMessage());
}

?>

<link rel="stylesheet" href="../app/css/app.css">

<div class="order-module">

    <div class="success-wrapper">

        <div class="success-card">

            <div class="success-icon">
                ✓
            </div>

            <h1>Payment Successful</h1>

            <p class="success-message">
                Your payment has been completed successfully.
            </p>

            <div class="success-details">

                <div class="success-row">
                    <span>Order ID</span>
                    <strong>
                        #<?= htmlspecialchars($order_id) ?>
                    </strong>
                </div>

                <div class="success-row">
                    <span>Amount Paid</span>
                    <strong>
                        RM <?= number_format($order['total_amount'], 2) ?>
                    </strong>
                </div>

                <div class="success-row">
                    <span>Payment Status</span>
                    <span class="status">
                        Paid
                    </span>
                </div>

            </div>

            <div class="success-actions">

                <a
                    href="order_detail.php?id=<?= $order_id ?>"
                    class="btn"
                >
                    View Order
                </a>

                <a
                    href="order_history.php"
                    class="btn btn-secondary"
                >
                    My Orders
                </a>

            </div>

        </div>

    </div>

</div>