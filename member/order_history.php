<?php

include '../app/_base.php';

// TODO: Replace with $_SESSION['user_id']
$user_id = 8;

$status = $_GET['status'] ?? '';

$allowedStatus = [
    'Paid',
    'Processing',
    'Shipped',
    'Completed',
    'Cancelled'
];

$sql = "
    SELECT
        o.order_id,
        o.order_date,
        o.order_status,
        o.total_amount,
        p.payment_status

    FROM orders o

    LEFT JOIN payment p
        ON o.order_id = p.order_id

    WHERE o.user_id = ?
";

$params = [$user_id];

if (
    $status !== '' &&
    in_array($status, $allowedStatus)
) {

    $sql .= " AND o.order_status = ?";

    $params[] = $status;
}

$sql .= " ORDER BY o.order_id DESC";

$stm = $_db->prepare($sql);

$stm->execute($params);

$orders = $stm->fetchAll();

?>

<link rel="stylesheet" href="../app/css/app.css">

<div class="order-module">

    <div class="order-history-topbar">

        <h1>My Orders</h1>

        <div class="order-history-wrapper">

            <div class="order-history-tabs">

                <a
                    href="order_history.php"
                    class="order-history-tab <?= $status === '' ? 'active' : '' ?>">
                    All
                </a>

                <a
                    href="order_history.php?status=Paid"
                    class="order-history-tab <?= $status === 'Paid' ? 'active' : '' ?>">
                    Paid
                </a>

                <a
                    href="order_history.php?status=Processing"
                    class="order-history-tab <?= $status === 'Processing' ? 'active' : '' ?>">
                    Processing
                </a>

                <a
                    href="order_history.php?status=Shipped"
                    class="order-history-tab <?= $status === 'Shipped' ? 'active' : '' ?>">
                    Shipped
                </a>

                <a
                    href="order_history.php?status=Completed"
                    class="order-history-tab <?= $status === 'Completed' ? 'active' : '' ?>">
                    Completed
                </a>

                <a
                    href="order_history.php?status=Cancelled"
                    class="order-history-tab <?= $status === 'Cancelled' ? 'active' : '' ?>">
                    Cancelled
                </a>

            </div>

        </div>

        <div class="order-history-content">
            <?php if (empty($orders)): ?>

                <div class="empty-message">

                    <?php if ($status !== ''): ?>

                        <p>
                            No <?= htmlspecialchars($status) ?> orders found.
                        </p>

                    <?php else: ?>

                        <p>No orders found.</p>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="order-history-list">

                    <?php foreach ($orders as $order): ?>

                        <div class="order-history-card">

                            <div class="order-history-header">

                                <div>
                                    <h3>
                                        Order #<?= htmlspecialchars($order['order_id']) ?>
                                    </h3>

                                    <p class="order-date">
                                        <?= date('d/m/Y', strtotime($order['order_date'])) ?>
                                    </p>
                                </div>

                                <span class="status">
                                    <?= htmlspecialchars($order['order_status']) ?>
                                </span>

                            </div>

                            <div class="order-history-body">

                                <div class="order-history-info">

                                    <span>Payment</span>

                                    <strong>
                                        <?= htmlspecialchars($order['payment_status'] ?? 'Pending') ?>
                                    </strong>

                                </div>

                                <div class="order-history-info">

                                    <span>Total</span>

                                    <strong>
                                        RM <?= number_format($order['total_amount'], 2) ?>
                                    </strong>

                                </div>

                            </div>

                            <div class="order-history-actions">

                                <a
                                    href="order_detail.php?id=<?= $order['order_id'] ?>"
                                    class="btn">
                                    View Details
                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>