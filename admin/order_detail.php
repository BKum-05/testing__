<?php

include '../app/_base.php';

$order_id = $_GET['id'] ?? '';
$error = '';
$success = '';

if ($order_id === '') {
    redirect('order_list.php');
}

// 1. Order + payment
$orderStm = $_db->prepare("SELECT o.order_id, o.user_id, o.order_date, o.order_status, o.total_amount, o.cancellation_reason, p.payment_method, p.payment_status,
                            p.transaction_id, p.paid_at
                           FROM orders o
                           LEFT JOIN payment p
                                ON o.order_id = p.order_id
                           WHERE o.order_id = ?");

$orderStm->execute([$order_id]);

$order = $orderStm->fetch();

if (!$order) {
    redirect('order_list.php');
}

// 2. Order items
$itemStm = $_db->prepare("SELECT oi.quantity, oi.subtotal, pv.variant_id, pv.size, pv.color, p.product_name
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

// Update Shipping Status (Order Status auto updated by system)
$action = $_POST['action'] ?? '';

if ($action === 'cancel_order') {

    $cancelReason = $_POST['cancellation_reason'] ?? '';

    $allowedReasons = [
        'Item out of stock',
        'Item damaged or unavailable',
        'Unable to fulfil order',
        'Delivery address issue',
        'Duplicate order'
    ];

    if (!in_array($cancelReason, $allowedReasons)) {
        die('Invalid cancellation reason.');
    }

    if (!in_array($order['order_status'], ['Paid', 'Processing'])) {
        die('This order can no longer be cancelled.');
    }

    try {

        $_db->beginTransaction();

        $stm = $_db->prepare("
            UPDATE orders
            SET order_status = 'Cancelled',
                cancellation_reason = ?
            WHERE order_id = ?
              AND order_status IN ('Paid', 'Processing')
        ");

        $stm->execute([
            $cancelReason,
            $order_id
        ]);

        if ($shipping) {

            $shippingStm = $_db->prepare("
                UPDATE shipping
                SET shipping_status = NULL
                WHERE order_id = ?
                  AND shipping_status IN ('Pending', 'Preparing')
            ");

            $shippingStm->execute([
                $order_id
            ]);
        }

        $_db->commit();

        redirect(
            'order_detail.php?id=' .
                $order_id
        );
    } catch (Exception $e) {

        if ($_db->inTransaction()) {
            $_db->rollBack();
        }

        die('Unable to cancel order.');
    }
}

if ($action === 'update_shipping') {

    if ($order['order_status'] === 'Cancelled') {
        die('Cancelled order cannot be shipped.');
    }

    $courier = trim($_POST['shipping_courier'] ?? '');
    $trackingNumber = trim($_POST['tracking_number'] ?? '');
    $shippingStatus = $_POST['shipping_status'] ?? '';
    $currentShippingStatus = $shipping['shipping_status'];

    // Status no change, only change courier / tracking
    if ($shippingStatus === $currentShippingStatus) {

        $stm = $_db->prepare("
        UPDATE shipping
        SET shipping_courier = ?,
            tracking_number = ?
        WHERE order_id = ?
    ");

        $stm->execute([
            $courier !== '' ? $courier : null,
            $trackingNumber !== '' ? $trackingNumber : null,
            $order_id
        ]);

        redirect("order_detail.php?id=" . $order_id);
    }

    $validTransitions = [
        'Pending' => ['Preparing'],
        'Preparing' => ['Shipped'],
        'Shipped' => ['Delivered'],
        'Delivered' => []
    ];

    $allowedShippingStatus = [
        'Pending',
        'Preparing',
        'Shipped',
        'Delivered'
    ];

    if (
        $shippingStatus !== $currentShippingStatus && !in_array(
            $shippingStatus,
            $validTransitions[$currentShippingStatus] ?? []
        )
    ) {
        $error = 'Invalid shipping status transition.';
    }

    if (!in_array($shippingStatus, $allowedShippingStatus)) {
        $error = 'Invalid shipping status.';
    }

    if (
        in_array($shippingStatus, ['Shipped', 'Delivered']) &&
        ($courier === '' || $trackingNumber === '')
    ) {
        $error = 'Please select a courier and enter a tracking number before marking the order as shipped.';
    }

    if ($error === "") {
        if ($shippingStatus === 'Shipped') {

            $stm = $_db->prepare("
                UPDATE shipping
                SET shipping_courier = ?,
                    tracking_number = ?,
                    shipping_status = ?,
                    shipped_date = CURDATE()
                WHERE order_id = ?
            ");
        } elseif ($shippingStatus === 'Delivered') {

            $stm = $_db->prepare("
                UPDATE shipping
                SET shipping_courier = ?,
                    tracking_number = ?,
                    shipping_status = ?,
                    shipped_date = COALESCE(shipped_date, CURDATE()),
                    delivered_date = CURDATE()
                WHERE order_id = ?  
            ");
        } else {

            $stm = $_db->prepare("
                UPDATE shipping
                SET shipping_courier = ?,
                    tracking_number = ?,
                    shipping_status = ?
                WHERE order_id = ?
            ");
        }

        $stm->execute([
            $courier !== '' ? $courier : null,
            $trackingNumber !== '' ? $trackingNumber : null,
            $shippingStatus,
            $order_id
        ]);

        if ($shippingStatus === 'Preparing') {

            $orderStatusStm = $_db->prepare("
        UPDATE orders
        SET order_status = 'Processing'
        WHERE order_id = ?
    ");

            $orderStatusStm->execute([$order_id]);
        }

        if ($shippingStatus === 'Shipped') {

            $orderStatusStm = $_db->prepare("
        UPDATE orders
        SET order_status = 'Shipped'
        WHERE order_id = ?
    ");

            $orderStatusStm->execute([$order_id]);
        }

        if ($shippingStatus === 'Delivered') {

            $orderStatusStm = $_db->prepare("
        UPDATE orders
        SET order_status = 'Completed'
        WHERE order_id = ?
    ");

            $orderStatusStm->execute([$order_id]);
        }

        redirect("order_detail.php?id=" . $order_id);
    }
}

?>

<link rel="stylesheet" href="../app/css/app.css">

<div class="order-module admin-order-detail">

    <?php if ($error !== ''): ?>

        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <div class="admin-detail-header">

        <div>
            <h1>
                Order #<?= htmlspecialchars($order['order_id']) ?>
            </h1>

            <p>
                User ID:
                <?= htmlspecialchars($order['user_id']) ?>
                ·
                <?= htmlspecialchars($order['order_date']) ?>
            </p>
        </div>

        <div class="admin-detail-actions">

            <a
                href="order_list.php"
                class="btn btn-secondary">
                Back to Orders
            </a>

        </div>
    </div>

    <div class="admin-order-layout">

        <div class="admin-order-main">

            <div class="admin-summary-grid">

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
                    <span class="detail-label">Order Status</span>

                    <strong class="detail-value">
                        <?= htmlspecialchars($order['order_status'] ?? '-') ?>
                    </strong>
                </div>

            </div>

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

                <div class="info-row">
                    <span>Paid At</span>

                    <strong>
                        <?= htmlspecialchars($order['paid_at'] ?? '-') ?>
                    </strong>
                </div>

            </div>

            <div class="card">

                <h2>Order Items</h2>

                <table class="order-table">

                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Variant ID</th>
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
                                    <?= htmlspecialchars($item['variant_id']) ?>
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

        </div>

        <div class="admin-order-sidebar">

            <div class="admin-info-grid">

                <?php if ($shipping && $order['order_status'] !== 'Cancelled'): ?>

                    <?php
                    $currentShippingStatus = $shipping['shipping_status'];
                    $nextShippingStatus = [
                        'Pending' => 'Preparing',
                        'Preparing' => 'Shipped',
                        'Shipped' => 'Delivered',
                        'Delivered' => null
                    ];
                    ?>

                    <div class="card">

                        <h2>Shipping Information</h2>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="update_shipping">

                            <label>Courier</label>

                            <?php if (
                                in_array(
                                    $shipping['shipping_status'],
                                    ['Shipped', 'Delivered']
                                )
                            ): ?>

                                <div class="shipping-readonly-value">
                                    <?= htmlspecialchars(
                                        $shipping['shipping_courier'] ?? '-'
                                    ) ?>
                                </div>

                                <input
                                    type="hidden"
                                    name="shipping_courier"
                                    value="<?= htmlspecialchars(
                                                $shipping['shipping_courier'] ?? ''
                                            ) ?>">

                            <?php else: ?>

                                <select name="shipping_courier">

                                    <option value="">
                                        Select Courier
                                    </option>

                                    <option value="J&T Express"
                                        <?= $shipping['shipping_courier'] === 'J&T Express'
                                            ? 'selected'
                                            : '' ?>>
                                        J&T Express
                                    </option>

                                    <option value="Pos Laju"
                                        <?= $shipping['shipping_courier'] === 'Pos Laju'
                                            ? 'selected'
                                            : '' ?>>
                                        Pos Laju
                                    </option>

                                    <option value="Ninja Van"
                                        <?= $shipping['shipping_courier'] === 'Ninja Van'
                                            ? 'selected'
                                            : '' ?>>
                                        Ninja Van
                                    </option>

                                    <option value="DHL eCommerce"
                                        <?= $shipping['shipping_courier'] === 'DHL eCommerce'
                                            ? 'selected'
                                            : '' ?>>
                                        DHL eCommerce
                                    </option>

                                </select>

                            <?php endif; ?>

                            <label>Tracking Number</label>

                            <input
                                type="text"
                                name="tracking_number"
                                value="<?= htmlspecialchars(
                                            $shipping['tracking_number'] ?? ''
                                        ) ?>"
                                placeholder="Enter tracking number"
                                <?= in_array(
                                    $shipping['shipping_status'],
                                    ['Shipped', 'Delivered']
                                ) ? 'readonly' : '' ?>>

                            <label>Shipping Status</label>

                            <select name="shipping_status">

                                <option
                                    value="<?= htmlspecialchars($currentShippingStatus) ?>"
                                    selected>
                                    <?= htmlspecialchars($currentShippingStatus) ?>
                                </option>

                                <?php if (!empty($nextShippingStatus[$currentShippingStatus])): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                                    $nextShippingStatus[$currentShippingStatus]
                                                ) ?>">
                                        <?= htmlspecialchars(
                                            $nextShippingStatus[$currentShippingStatus]
                                        ) ?>
                                    </option>

                                <?php endif; ?>

                            </select>

                            <div class="info-row">
                                <span>Shipped Date</span>

                                <strong>
                                    <?= !empty($shipping['shipped_date']) &&
                                        $shipping['shipped_date'] !== '0000-00-00'
                                        ? date('d/m/Y', strtotime($shipping['shipped_date']))
                                        : '-' ?>
                                </strong>
                            </div>

                            <div class="info-row">
                                <span>Delivered Date</span>

                                <strong>
                                    <?= !empty($shipping['delivered_date']) &&
                                        $shipping['delivered_date'] !== '0000-00-00'
                                        ? date('d/m/Y', strtotime($shipping['delivered_date']))
                                        : '-' ?>
                                </strong>
                            </div>

                            <?php if (
                                $shipping['shipping_status'] !== 'Delivered'
                            ): ?>

                                <button
                                    type="submit"
                                    class="btn">
                                    Update Shipping
                                </button>

                            <?php endif; ?>

                        </form>

                    </div>

                <?php endif; ?>

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

            <div class="admin-update-grid">

                <?php if (
                    in_array(
                        $order['order_status'],
                        ['Paid', 'Processing']
                    )
                ): ?>

                    <div class="admin-update-card">

                        <h2>Cancel Order</h2>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="cancel_order">

                            <label>Cancellation Reason</label>

                            <select
                                name="cancellation_reason"
                                required>

                                <option value="">
                                    Select Reason
                                </option>

                                <option value="Item out of stock">
                                    Item out of stock
                                </option>

                                <option value="Delivery address issue">
                                    Delivery address issue
                                </option>

                            </select>

                            <button type="submit">
                                Cancel Order
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>