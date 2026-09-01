<?php

include '../app/_base.php';

// TODO: Replace with $_SESSION['user_id'] after login module is completed
$user_id = 8;


// 1. Find active cart for this user

$cartStm = $_db->prepare("
    SELECT cart_id
    FROM cart
    WHERE user_id = ?
      AND deleted_at IS NULL
    LIMIT 1
");

$cartStm->execute([$user_id]);
$cart = $cartStm->fetch();


// 2. If user has no cart, create one

if (!$cart) {

    $createCartStm = $_db->prepare("
        INSERT INTO cart
            (user_id, session_id, created_at, deleted_at)
        VALUES
            (?, ?, NOW(), NULL)
    ");

    // Temporary because your session_id column is currently INT
    $session_id = 1;

    $createCartStm->execute([
        $user_id,
        $session_id
    ]);

    $cart_id = $_db->lastInsertId();
} else {

    $cart_id = $cart['cart_id'];
}

// 3. Receive cart action

$action = $_POST['action'] ?? '';
$variant_id = $_POST['variant_id'] ?? '';

// 4. Add To Cart

if ($action === 'add' && $variant_id !== '') {

    // Check whether variant exists and get stock
    $stockStm = $_db->prepare("
        SELECT stock
        FROM product_variant
        WHERE variant_id = ?
    ");

    $stockStm->execute([$variant_id]);
    $variant = $stockStm->fetch();

    if ($variant && $variant['stock'] > 0) {

        // Check whether this variant is already in cart
        $checkItemStm = $_db->prepare("
            SELECT cartItem_id, quantity
            FROM cart_item
            WHERE cart_id = ?
              AND variant_id = ?
        ");

        $checkItemStm->execute([
            $cart_id,
            $variant_id
        ]);

        $existingItem = $checkItemStm->fetch();

        if ($existingItem) {

            // Already exists → increase quantity
            if ($existingItem['quantity'] < $variant['stock']) {

                $updateItemStm = $_db->prepare("
                    UPDATE cart_item
                    SET quantity = quantity + 1
                    WHERE cartItem_id = ?
                ");

                $updateItemStm->execute([
                    $existingItem['cartItem_id']
                ]);
            }
        } else {

            // First time adding this variant
            $addItemStm = $_db->prepare("
                INSERT INTO cart_item
                    (cart_id, variant_id, quantity)
                VALUES
                    (?, ?, 1)
            ");

            $addItemStm->execute([
                $cart_id,
                $variant_id
            ]);
        }
    }
}

// 5. Update Cart Quantities

if ($action === 'update_cart') {

    $quantities = $_POST['quantities'] ?? [];

    if (is_array($quantities)) {

        foreach ($quantities as $variantId => $quantity) {

            $variantId = (int)$variantId;
            $quantity = (int)$quantity;

            // Get current stock
            $stockStm = $_db->prepare("
                SELECT stock
                FROM product_variant
                WHERE variant_id = ?
            ");

            $stockStm->execute([$variantId]);

            $variant = $stockStm->fetch();


            if (
                !$variant ||
                $quantity < 1 ||
                $quantity > $variant['stock']
            ) {
                continue;
            }


            $updateItemStm = $_db->prepare("
                UPDATE cart_item
                SET quantity = ?
                WHERE cart_id = ?
                  AND variant_id = ?
            ");

            $updateItemStm->execute([
                $quantity,
                $cart_id,
                $variantId
            ]);
        }
    }

    redirect('cart.php');
}

// 6. Remove Item

if ($action === 'remove' && $variant_id !== '') {

    $removeItemStm = $_db->prepare("
        DELETE FROM cart_item
        WHERE cart_id = ?
          AND variant_id = ?
    ");

    $removeItemStm->execute([
        $cart_id,
        $variant_id
    ]);
}

// 7. Retrieve Cart Items

$itemStm = $_db->prepare("
    SELECT
        ci.cartItem_id,
        ci.quantity,
        pv.variant_id,
        pv.size,
        pv.color,
        pv.price,
        pv.stock,
        pv.image_filename,
        p.product_name
    FROM cart_item ci
    JOIN product_variant pv
        ON ci.variant_id = pv.variant_id
    JOIN product p
        ON pv.product_id = p.product_id
    WHERE ci.cart_id = ?
");

$itemStm->execute([$cart_id]);

$cartItems = $itemStm->fetchAll();

// 8. Calculate subtotal and grand total

$grandTotal = 0;

foreach ($cartItems as &$item) {

    $item['subtotal'] =
        $item['price'] * $item['quantity'];

    $grandTotal += $item['subtotal'];
}

unset($item);

?>

<link rel="stylesheet" href="../app/css/app.css">

<div class="order-module">

    <h1>Shopping Cart</h1>

    <?php if (empty($cartItems)): ?>

        <div class="empty-message">
            <p>Your cart is empty.</p>
        </div>

    <?php else: ?>

        <div class="card">

            <table class="order-table">

                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Product</th>
                        <th>Size</th>
                        <th>Color</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($cartItems as $item): ?>

                        <tr>

                            <td>
                                <?php if (!empty($item['image_filename'])): ?>

                                    <img
                                        src="/app/uploads/product/<?= htmlspecialchars($item['image_filename']) ?>"
                                        alt="<?= htmlspecialchars($item['product_name']) ?>"
                                        class="cart-product-image">

                                <?php else: ?>

                                    <span>-</span>

                                <?php endif; ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($item['product_name']) ?>
                                </strong>
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

                                <div class="quantity-wrapper">

                                    <input
                                        type="number"
                                        name="quantities[<?= htmlspecialchars($item['variant_id']) ?>]"
                                        class="quantity-input"
                                        value="<?= (int)$item['quantity'] ?>"
                                        min="1"
                                        max="<?= (int)$item['stock'] ?>"
                                        data-stock="<?= (int)$item['stock'] ?>"
                                        form="updateCartForm">

                                    <?php if ($item['quantity'] >= $item['stock']): ?>

                                        <div class="stock-warning">
                                            No more stock available.
                                        </div>

                                    <?php endif; ?>

                                </div>

                            </td>

                            <td>
                                RM <?= number_format($item['subtotal'], 2) ?>
                            </td>

                            <td>

                                <form
                                    action="cart.php"
                                    method="POST">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="remove">

                                    <input
                                        type="hidden"
                                        name="variant_id"
                                        value="<?= $item['variant_id'] ?>">

                                    <button
                                        type="submit"
                                        class="btn btn-remove">
                                        Remove
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <div class="cart-bottom">

            <div class="total">
                Subtotal: &nbsp &nbsp &nbsp &nbsp &nbsp RM &nbsp &nbsp<?= number_format($grandTotal, 2) ?>
            </div>

            <div class="cart-bottom-actions">

                <form
                    id="updateCartForm"
                    action="cart.php"
                    method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="update_cart">

                    <button
                        type="submit"
                        class="btn">
                        Update Cart
                    </button>

                </form>

                <a
                    href="checkout.php"
                    class="btn checkout-btn">
                    Proceed to Checkout
                </a>

            </div>

        </div>

    <?php endif; ?>

</div>

<script>
document.querySelectorAll('.quantity-input').forEach(function(input) {

    input.addEventListener('input', function() {

        const stock = parseInt(input.dataset.stock);
        const quantity = parseInt(input.value);

        const warning =
            input.closest('.quantity-wrapper')
                 .querySelector('.stock-warning');

        if (quantity >= stock) {
            warning.classList.add('show');
        } else {
            warning.classList.remove('show');
        }

    });

});
</script>