<?php

include '../app/_base.php';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$sql = "SELECT o.order_id, o.user_id, o.order_date, o.order_status, o.total_amount, p.payment_status
        FROM orders o
        LEFT JOIN payment p
            ON o.order_id = p.order_id
        WHERE 1 = 1";

$params = [];

if ($search !== '') {

    $sql .= " AND (o.order_id LIKE ? OR o.user_id LIKE ?)";

    $keyword = '%' . $search . '%';

    $params[] = $keyword;
    $params[] = $keyword;
}

if ($status !== '') {

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

    <div class="admin-order-header">
        <div>
            <h1>Order Management</h1>
            <p>View, search and manage customer orders.</p>
        </div>
    </div>


    <div class="admin-filter-card">

        <form method="GET" class="admin-filter-form">

            <div class="form-group">

                <label>Search</label>

                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Order ID or User ID">

            </div>


            <div class="form-group">

                <label>Status</label>

                <select name="status">

                    <option value="">
                        All Status
                    </option>

                    <option value="Paid"
                        <?= $status === 'Paid' ? 'selected' : '' ?>>
                        Paid
                    </option>

                    <option value="Processing"
                        <?= $status === 'Processing' ? 'selected' : '' ?>>
                        Processing
                    </option>

                    <option value="Shipped"
                        <?= $status === 'Shipped' ? 'selected' : '' ?>>
                        Shipped
                    </option>

                    <option value="Completed"
                        <?= $status === 'Completed' ? 'selected' : '' ?>>
                        Completed
                    </option>

                    <option value="Cancelled"
                        <?= $status === 'Cancelled' ? 'selected' : '' ?>>
                        Cancelled
                    </option>

                </select>

            </div>


            <div class="admin-filter-actions">

                <button type="submit" class="btn">
                    Search
                </button>

                <a
                    href="order_list.php"
                    class="btn btn-secondary">
                    Reset
                </a>

                <a
                    href="shipping_print.php"
                    class="btn">
                    Shipping Preparation List
                </a>

            </div>

        </form>

    </div>


    <?php if (empty($orders)): ?>

        <div class="empty-message">
            No orders found.
        </div>

    <?php else: ?>

        <div class="admin-table-card">

            <table class="order-table">

                <thead>

                    <tr>
                        <th>Order ID</th>
                        <th>User ID</th>
                        <th>Date</th>
                        <th>Order Status</th>
                        <th>Payment</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($orders as $order): ?>

                        <tr>

                            <td>
                                #<?= htmlspecialchars($order['order_id']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($order['user_id']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($order['order_date']) ?>
                            </td>

                            <td>
                                <span class="status">
                                    <?= htmlspecialchars($order['order_status']) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($order['payment_status'] ?? 'Pending') ?>
                            </td>

                            <td>
                                RM <?= number_format($order['total_amount'], 2) ?>
                            </td>

                            <td>

                                <a
                                    href="order_detail.php?id=<?= $order['order_id'] ?>"
                                    class="btn btn-small">
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>