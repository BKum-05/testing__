<?php

include '../app/_base.php';

// TODO: Replace with $_SESSION['user_id']
$user_id = 8;

$order_id = $_GET['id'] ?? '';

if ($order_id === '') {
    redirect('order_history.php');
}


// 1. Get order + payment
$orderStm = $_db->prepare("SELECT o.order_id, o.order_date, o.order_status, o.total_amount, p.payment_method, p.payment_status,
                                  p.transaction_id, p.paid_at
                            FROM orders o
                            LEFT JOIN payment p
                                ON o.order_id = p.order_id
                            WHERE o.order_id = ?
                            AND o.user_id = ?");

$orderStm->execute([$order_id, $user_id]);

$order = $orderStm->fetch();

if (!$order) {
    redirect('order_history.php');
}


// 2. Get order items
$itemStm = $_db->prepare("SELECT oi.quantity, oi.subtotal, pv.size, pv.color, pv.price, p.product_name
                            FROM order_item oi
                            JOIN product_variant pv
                                ON oi.variant_id = pv.variant_id
                            JOIN product p
                                ON pv.product_id = p.product_id
                            WHERE oi.order_id = ?");

$itemStm->execute([$order_id]);

$orderItems = $itemStm->fetchAll();


// 3. Get shipping
$shippingStm = $_db->prepare("SELECT *
                                FROM shipping
                                WHERE order_id = ?");

$shippingStm->execute([$order_id]);

$shipping = $shippingStm->fetch();


// 4. Generate invoice number
$invoice_no = 'INV-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);

?>

<link rel="stylesheet" href="../app/css/app.css">

<div class="order-module">

    <div class="invoice">

        <div class="invoice-header">

            <div>
                <h1>Invoice</h1>
                <p><?= htmlspecialchars($invoice_no) ?></p>
            </div>

            <div class="invoice-status">
                <?= htmlspecialchars($order['payment_status'] ?? 'Pending') ?>
            </div>

        </div>


        <div class="invoice-meta">

            <div>
                <span>Order ID</span>
                <strong>
                    #<?= htmlspecialchars($order['order_id']) ?>
                </strong>
            </div>

            <div>
                <span>Order Date</span>
                <strong>
                    <?= htmlspecialchars($order['order_date']) ?>
                </strong>
            </div>

            <div>
                <span>Payment Method</span>
                <strong>
                    <?= htmlspecialchars($order['payment_method'] ?? '-') ?>
                </strong>
            </div>

        </div>


        <?php if ($shipping): ?>

            <div class="invoice-section">

                <h2>Ship To</h2>

                <p>
                    <strong>
                        <?= htmlspecialchars($shipping['recipient_name']) ?>
                    </strong>
                </p>

                <p>
                    <?= htmlspecialchars($shipping['phone_number']) ?>
                </p>

                <p>
                    <?= htmlspecialchars($shipping['shipping_street']) ?><br>
                    <?= htmlspecialchars($shipping['shipping_postcode']) ?>
                    <?= htmlspecialchars($shipping['shipping_city']) ?><br>
                    <?= htmlspecialchars($shipping['shipping_state']) ?>,
                    <?= htmlspecialchars($shipping['shipping_country']) ?>
                </p>

            </div>

        <?php endif; ?>


        <div class="invoice-section">

            <h2>Order Items</h2>

            <table class="invoice-table">

                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Size</th>
                        <th>Color</th>
                        <th>Price</th>
                        <th>Qty</th>
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
                                RM <?= number_format($item['price'], 2) ?>
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


        <div class="invoice-total">

            <span>Total Amount</span>

            <strong>
                RM <?= number_format($order['total_amount'], 2) ?>
            </strong>

        </div>


        <div class="invoice-payment">

            <p>
                Transaction ID:
                <strong>
                    <?= htmlspecialchars($order['transaction_id'] ?? '-') ?>
                </strong>
            </p>

        </div>


        <div class="invoice-actions">

            <a
                href="order_detail.php?id=<?= $order_id ?>"
                class="btn btn-secondary"
            >
                Back
            </a>

            <button
                type="button"
                class="btn"
                onclick="window.print()"
            >
                Print Invoice
            </button>

        </div>

    </div>

</div>