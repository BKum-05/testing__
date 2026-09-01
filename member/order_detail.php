<?php

include '../app/_base.php';

// TODO: Replace with $_SESSION['user_id']
$user_id = 8;

$order_id = $_GET['id'] ?? '';

if ($order_id === '') {
    redirect('order_history.php');
}


// 1. Order header
$orderStm = $_db->prepare("SELECT o.order_id, o.order_date, o.order_status, o.total_amount, o.cancellation_reason, p.payment_method, p.payment_status,
                                p.transaction_id, p.paid_at
                            FROM orders o
                            LEFT JOIN payment p
                                ON o.order_id = p.order_id
                            WHERE o.order_id = ?
                            AND o.user_id = ?");

$orderStm->execute([
    $order_id,
    $user_id
]);

$order = $orderStm->fetch();

if (!$order) {
    redirect('order_history.php');
}


// 2. Order items
$itemStm = $_db->prepare("SELECT oi.quantity, oi.subtotal, pv.size, pv.color, pv.price, pv.image_filename, p.product_name
                            FROM order_item oi
                            JOIN product_variant pv
                                ON oi.variant_id = pv.variant_id
                            JOIN product p
                                ON pv.product_id = p.product_id
                            WHERE oi.order_id = ?");

$itemStm->execute([$order_id]);

$orderItems = $itemStm->fetchAll();


// 3. Shipping
$shippingStm = $_db->prepare("SELECT *
                            FROM shipping
                            WHERE order_id = ?");

$shippingStm->execute([$order_id]);

$shipping = $shippingStm->fetch();

?>

<link rel="stylesheet" href="../app/css/app.css">

<div class="order-module">

    <div class="order-detail-header">

        <div>
            <h1>Order #<?= htmlspecialchars($order['order_id']) ?></h1>

            <p class="order-date">
                <?= htmlspecialchars($order['order_date']) ?>
            </p>
        </div>

        <span class="status">
            <?= htmlspecialchars($order['order_status']) ?>
        </span>

    </div>


    <div class="order-detail-grid">

        <div class="detail-card">

            <span class="detail-label">Order Total</span>

            <strong class="detail-value">
                RM <?= number_format($order['total_amount'], 2) ?>
            </strong>

        </div>


        <div class="detail-card">

            <span class="detail-label">Payment Status</span>

            <strong class="detail-value">
                <?= htmlspecialchars($order['payment_status'] ?? 'Pending') ?>
            </strong>

        </div>


        <div class="detail-card">

            <span class="detail-label">Payment Method</span>

            <strong class="detail-value">
                <?= htmlspecialchars($order['payment_method'] ?? '-') ?>
            </strong>

        </div>

    </div>


    <div class="card">

        <h2>Order Items</h2>

        <table class="order-table">

            <thead>

                <tr>
                    <th>Product</th>
                    <th>Size</th>
                    <th>Color</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($orderItems as $item): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($item['product_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($item['size']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($item['color']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($item['quantity']) ?>
                        </td>

                        <td>
                            RM <?= number_format($item['subtotal'], 2) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <?php if (
        $order['order_status'] === 'Cancelled'
    ): ?>

        <div class="info-row">

            <span>Cancellation Reason</span>

            <strong>
                <?= htmlspecialchars(
                    $order['cancellation_reason'] ?? '-'
                ) ?>
            </strong>

        </div>

    <?php endif; ?>

    <div class="order-info-grid">

        <div class="card">

            <h2>Payment Information</h2>

            <div class="info-row">
                <span>Method</span>

                <strong>
                    <?= htmlspecialchars($order['payment_method'] ?? '-') ?>
                </strong>
            </div>

            <div class="info-row">
                <span>Status</span>

                <strong>
                    <?= htmlspecialchars($order['payment_status'] ?? 'Pending') ?>
                </strong>
            </div>

            <div class="info-row">
                <span>Transaction ID</span>

                <strong>
                    <?= htmlspecialchars($order['transaction_id'] ?? '-') ?>
                </strong>
            </div>

        </div>


        <?php if ($shipping && $order['order_status'] !== 'Cancelled'): ?>

            <div class="card">

                <h2>Shipping Information</h2>

                <div class="info-row">
                    <span>Recipient</span>

                    <strong>
                        <?= htmlspecialchars($shipping['recipient_name']) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>Phone</span>

                    <strong>
                        <?= htmlspecialchars($shipping['phone_number']) ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>Shipping Status</span>

                    <strong>
                        <?= htmlspecialchars($shipping['shipping_status'] ?? '-') ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>Courier</span>

                    <strong>
                        <?= htmlspecialchars($shipping['shipping_courier'] ?? '-') ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>Tracking Number</span>

                    <strong>
                        <?= htmlspecialchars($shipping['tracking_number'] ?? '-') ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>Shipped Date</span>

                    <strong>
                        <?= htmlspecialchars($shipping['shipped_date'] ?? '-') ?>
                    </strong>
                </div>

                <div class="info-row">
                    <span>Delivered Date</span>

                    <strong>
                        <?= htmlspecialchars($shipping['delivered_date'] ?? '-') ?>
                    </strong>
                </div>

                <div class="shipping-address">

                    <?= htmlspecialchars($shipping['shipping_street']) ?><br>

                    <?= htmlspecialchars($shipping['shipping_postcode']) ?>
                    <?= htmlspecialchars($shipping['shipping_city']) ?><br>

                    <?= htmlspecialchars($shipping['shipping_state']) ?>,
                    <?= htmlspecialchars($shipping['shipping_country']) ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

    <div class="order-detail-actions">

        <a
            href="order_history.php"
            class="btn btn-secondary">
            Back to Orders
        </a>

        <a
            href="invoice.php?id=<?= $order['order_id'] ?>"
            class="btn">
            View Invoice
        </a>

    </div>

</div>