<?php

include '../app/_base.php';

$order_id = $_GET['order_id'] ?? '';

if ($order_id === '') {
    redirect('cart.php');
}

$stm = $_db->prepare("
    SELECT order_id, order_status, total_amount
    FROM orders
    WHERE order_id = ?
");

$stm->execute([$order_id]);

$order = $stm->fetch();

if (!$order) {
    redirect('cart.php');
}

?>

<h1>Payment</h1>

<p>
    Order ID:
    <?= htmlspecialchars($order['order_id']) ?>
</p>

<p>
    Total:
    RM <?= number_format($order['total_amount'], 2) ?>
</p>

<p>
    Status:
    <?= htmlspecialchars($order['order_status']) ?>
</p>

<button>
    Pay Now
</button>