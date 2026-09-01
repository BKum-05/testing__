<?php

// Temporary user for testing
$user_id = 8;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'remove_side_cart'
) {

    $variantId = $_POST['variant_id'] ?? '';

    if ($variantId !== '') {

        $cartStm = $_db->prepare("
            SELECT cart_id
            FROM cart
            WHERE user_id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $cartStm->execute([$user_id]);

        $cart = $cartStm->fetch();

        if ($cart) {

            $removeStm = $_db->prepare("
                DELETE FROM cart_item
                WHERE cart_id = ?
                  AND variant_id = ?
            ");

            $removeStm->execute([
                $cart['cart_id'],
                $variantId
            ]);
        }
    }

    $separator =
        str_contains($_SERVER['REQUEST_URI'], '?')
        ? '&'
        : '?';

    redirect(
        $_SERVER['REQUEST_URI'] .
            $separator .
            'cart=open'
    );
}

// Find active cart
$sideCartStm = $_db->prepare("
    SELECT cart_id
    FROM cart
    WHERE user_id = ?
      AND deleted_at IS NULL
    LIMIT 1
");

$sideCartStm->execute([$user_id]);

$sideCart = $sideCartStm->fetch();

$sideCartItems = [];
$sideCartTotal = 0;


if ($sideCart) {

    $sideCartItemStm = $_db->prepare("
        SELECT
            ci.cartItem_id,
            ci.quantity,

            pv.variant_id,
            pv.size,
            pv.color,
            pv.price,
            pv.image_filename,

            p.product_name

        FROM cart_item ci

        JOIN product_variant pv
            ON ci.variant_id = pv.variant_id

        JOIN product p
            ON pv.product_id = p.product_id

        WHERE ci.cart_id = ?
    ");

    $sideCartItemStm->execute([
        $sideCart['cart_id']
    ]);

    $sideCartItems =
        $sideCartItemStm->fetchAll();


    foreach ($sideCartItems as $item) {

        $sideCartTotal +=
            $item['price'] *
            $item['quantity'];
    }
}

?>


<div
    class="side-cart-overlay"
    id="sideCartOverlay"></div>


<aside
    class="side-cart"
    id="sideCart">

    <div class="side-cart-header">

        <h2>Your Cart</h2>

        <button
            type="button"
            class="side-cart-close"
            id="closeSideCart"
            aria-label="Close cart">
            &times;
        </button>

    </div>


    <div class="side-cart-body">


        <?php if (empty($sideCartItems)): ?>

            <div class="side-cart-empty">

                <p>
                    Your cart is empty.
                </p>

            </div>


        <?php else: ?>


            <?php foreach ($sideCartItems as $item): ?>

                <div class="side-cart-item">

                    <?php if (!empty($item['image_filename'])): ?>

                        <div class="side-cart-image">

                            <img
                                src="../app/uploads/product/<?= htmlspecialchars($item['image_filename']) ?>"
                                alt="<?= htmlspecialchars($item['product_name']) ?>">

                        </div>

                    <?php endif; ?>


                    <div class="side-cart-item-info">

                        <strong class="side-cart-product-name">

                            <?= htmlspecialchars(
                                $item['product_name']
                            ) ?>

                        </strong>


                        <span class="side-cart-variant">

                            Size:
                            <?= htmlspecialchars(
                                $item['size']
                            ) ?>

                            ·

                            Color:
                            <?= htmlspecialchars(
                                $item['color']
                            ) ?>

                        </span>


                        <div class="side-cart-item-bottom">

                            <span>

                                Qty:
                                <?= htmlspecialchars(
                                    $item['quantity']
                                ) ?>

                            </span>


                            <strong>

                                RM
                                <?= number_format(
                                    $item['price'] *
                                        $item['quantity'],
                                    2
                                ) ?>

                            </strong>

                        </div>

                        <form
                            method="POST"
                            class="side-cart-remove-form">

                            <input
                                type="hidden"
                                name="action"
                                value="remove_side_cart">

                            <input
                                type="hidden"
                                name="variant_id"
                                value="<?= htmlspecialchars($item['variant_id']) ?>">

                            <button
                                type="submit"
                                class="side-cart-remove-btn"
                                onclick="return confirm('Remove this item from your cart?')">
                                Remove
                            </button>

                        </form>

                    </div>

                </div>

            <?php endforeach; ?>


        <?php endif; ?>


    </div>


    <div class="side-cart-footer">


        <div class="side-cart-total">

            <span>Total</span>

            <strong>

                RM
                <?= number_format(
                    $sideCartTotal,
                    2
                ) ?>

            </strong>

        </div>

        <p class="side-cart-shipping-note">
            Shipping calculated at checkout
        </p>

        <div class="side-cart-actions">

            <a
                href="../member/cart.php"
                class="btn btn-secondary">
                View Cart
            </a>


            <?php if (!empty($sideCartItems)): ?>

                <a
                    href="../member/checkout.php"
                    class="btn">
                    Checkout
                </a>

            <?php endif; ?>

        </div>

    </div>

</aside>


<script src="../app/js/side_cart.js"></script>