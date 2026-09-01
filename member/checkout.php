<?php

include '../app/_base.php';

// TODO: Replace with session user after Login module is completed
$user_id = 8;

$cartStm = $_db->prepare("SELECT cart_id FROM cart WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");

$cartStm->execute([$user_id]);
$cart = $cartStm->fetch();

if (!$cart) {
    redirect('cart.php');
}

//if (!isset($_SESSION['user_id'])) {
//  redirect('../login/login.php');
//}

$cart_id = $cart['cart_id'];

$itemStm = $_db->prepare("SELECT ci.cartItem_id, ci.quantity, pv.variant_id, pv.size, pv.color, pv.price, pv.stock, pv.image_filename, p.product_name
                             FROM cart_item ci
                                 JOIN product_variant pv  ON ci.variant_id = pv.variant_id
                                    JOIN product p ON pv.product_id = p.product_id
                                        WHERE ci.cart_id = ?");

$itemStm->execute([$cart_id]);

$cartItems = $itemStm->fetchAll();

if (empty($cartItems)) {
    redirect('cart.php');
}

$grandTotal = 0;

foreach ($cartItems as &$item) {
    $item['subtotal'] = $item['price'] * $item['quantity'];
    $grandTotal += $item['subtotal'];
}

unset($item);

$voucherStm = $_db->prepare("
    SELECT
        v.voucher_id,
        v.name,
        v.code,
        v.startDate,
        v.endDate,
        v.voucherType,

        d.discount_id,
        d.discountType,
        d.percentage,
        d.amount,
        d.buy_x,
        d.get_y

    FROM voucher_assignments va

    JOIN voucher v
        ON va.voucher_id = v.voucher_id

    JOIN discount d
        ON v.discount_id = d.discount_id

    WHERE va.user_id = ?

      AND CURDATE()
          BETWEEN v.startDate AND v.endDate

    ORDER BY v.endDate ASC
");

$voucherStm->execute([$user_id]);

$userVouchers = $voucherStm->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $voucherCode = trim($_POST['voucher_code'] ?? '');

    $voucherDiscount = 0.00;
    $appliedVoucherId = null;

    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    $checkoutErrors = [];

    if ($full_name === '') {
        $checkoutErrors[] = 'Full name is required.';
    }

    if ($phone === '') {
        $checkoutErrors[] = 'Phone number is required.';
    }

    if ($address === '') {
        $checkoutErrors[] = 'Address is required.';
    }

    if (!preg_match('/^\d{5}$/', $postcode)) {
        $checkoutErrors[] = 'Postcode must contain 5 digits.';
    }

    if ($city === '') {
        $checkoutErrors[] = 'City is required.';
    }

    if ($state === '') {
        $checkoutErrors[] = 'State is required.';
    }

    $shippingFee = 0;

    $eastMalaysiaStates = [
        'Sabah',
        'Sarawak',
        'Labuan'
    ];

    if ($state !== '') {

        if (in_array($state, $eastMalaysiaStates, true)) {

            $shippingFee = 20.00;
        } else {

            $shippingFee = 10.00;
        }
    }

    if ($voucherCode !== '') {

        $voucherCheckStm = $_db->prepare("
        SELECT
            v.voucher_id,
            v.code,

            d.discountType,
            d.percentage,
            d.amount,
            d.buy_x,
            d.get_y

        FROM voucher_assignments va

        JOIN voucher v
            ON va.voucher_id = v.voucher_id

        JOIN discount d
            ON v.discount_id = d.discount_id

        WHERE va.user_id = ?
          AND UPPER(v.code) = UPPER(?)
          AND CURDATE()
              BETWEEN v.startDate AND v.endDate

        LIMIT 1
    ");

        $voucherCheckStm->execute([
            $user_id,
            $voucherCode
        ]);

        $selectedVoucher =
            $voucherCheckStm->fetch();


        if (!$selectedVoucher) {

            $checkoutErrors[] =
                'Invalid or unavailable voucher.';
        } else {

            if (
                $selectedVoucher['discountType']
                === 'PERCENTAGE'
            ) {

                $voucherDiscount =
                    $grandTotal *
                    (
                        (float)$selectedVoucher['percentage']
                        / 100
                    );
            } elseif (
                $selectedVoucher['discountType']
                === 'AMOUNT'
            ) {

                $voucherDiscount =
                    (float)$selectedVoucher['amount'];
            } else {

                $checkoutErrors[] =
                    'This voucher type is not supported.';
            }


            $voucherDiscount =
                min(
                    $voucherDiscount,
                    $grandTotal
                );

            $appliedVoucherId =
                $selectedVoucher['voucher_id'];
        }
    }

    $orderTotal =
        max(
            0,
            $grandTotal
                + $shippingFee
                - $voucherDiscount
        );

    if (empty($checkoutErrors)) {

        try {

            $_db->beginTransaction();

            // 1. Create Order
            $orderStm = $_db->prepare("
            INSERT INTO orders
                (user_id, order_date, order_status, total_amount)
            VALUES
                (?, CURDATE(), ?, ?)
        ");

            $order_status = 'Pending Payment';

            $orderStm->execute([
                $user_id,
                $order_status,
                $orderTotal
            ]);

            $order_id = $_db->lastInsertId();


            // 2. Create Order Items
            $orderItemStm = $_db->prepare("
            INSERT INTO order_item
                (order_id, variant_id, quantity, subtotal)
            VALUES
                (?, ?, ?, ?)
        ");

            foreach ($cartItems as $item) {

                $orderItemStm->execute([
                    $order_id,
                    $item['variant_id'],
                    $item['quantity'],
                    $item['subtotal']
                ]);
            }

            // 3. Create Shipping
            $shippingStm = $_db->prepare("
            INSERT INTO shipping
            (
                order_id,
                shipping_street,
                shipping_city,
                shipping_state,
                shipping_postcode,
                shipping_country,
                latitude,
                longitude,
                shipping_status,
                phone_number,
                recipient_name,
                shipping_fee
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

            $shippingStm->execute([
                $order_id,
                $address,
                $city,
                $state,
                $postcode,
                'Malaysia',
                $latitude,
                $longitude,
                'Pending',
                $phone,
                $full_name,
                $shippingFee
            ]);


            $_db->commit();

            redirect("payment.php?order_id=" . $order_id);
        } catch (Exception $e) {

            $_db->rollBack();

            $checkoutErrors[] = 'Unable to place order. Please try again.';
        }
    }
}

?>

<link rel="stylesheet" href="../app/css/app.css">

<div class="order-module">

    <div class="checkout-page-header">

        <h1>Checkout</h1>

        <a
            href="cart.php"
            class="checkout-cart-btn"
            title="Back to cart"
            aria-label="Back to cart">

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true">

                <path
                    d="M6 8h12l1 13H5L6 8Zm3 0V6a3 3 0 0 1 6 0v2"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round">
                </path>

            </svg>

        </a>

    </div>

    <?php if (!empty($checkoutErrors)): ?>

        <div class="error-message">

            <?php foreach ($checkoutErrors as $error): ?>
                <p> <?= htmlspecialchars($error) ?> </p>
            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <div class="checkout-layout">

        <!-- LEFT SIDE -->
        <div class="checkout-left">

            <div class="checkout-form-card">

                <h2>Shipping Information</h2>

                <form method="POST" id="checkoutForm">

                    <div class="checkout-contact-form">

                        <input
                            type="text"
                            name="full_name"
                            value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                            placeholder="Full name"
                            required>

                        <input
                            type="text"
                            name="phone"
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                            placeholder="Phone Number"
                            required>

                    </div>

                    <div class="shipping-map-wrapper">

                        <div id="shipping-map"></div>

                        <div class="map-top-controls">

                            <div
                                id="autocomplete-container"
                                class="map-search-box">
                            </div>

                        </div>
                    </div>

                    <div class="checkout-address-form">

                        <input
                            type="text"
                            id="address"
                            name="address"
                            value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                            placeholder="Address"
                            required>

                        <input
                            type="hidden"
                            id="latitude"
                            name="latitude">

                        <input
                            type="hidden"
                            id="longitude"
                            name="longitude">

                        <input
                            type="text"
                            id="address_unit"
                            name="address_unit"
                            value="<?= htmlspecialchars($_POST['address_unit'] ?? '') ?>"
                            placeholder="Apartment, suite, etc. (optional)">


                        <div class="checkout-location-row">

                            <input
                                type="text"
                                id="postcode"
                                name="postcode"
                                value="<?= htmlspecialchars($_POST['postcode'] ?? '') ?>"
                                placeholder="Postcode"
                                required>

                            <input
                                type="text"
                                id="city"
                                name="city"
                                value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                placeholder="City"
                                required>

                            <select
                                id="state"
                                name="state"
                                required>

                                <option value="" disabled
                                    <?= empty($_POST['state']) ? 'selected' : '' ?>>
                                    State/territory
                                </option>

                                <option value="Penang"
                                    <?= ($_POST['state'] ?? '') === 'Penang' ? 'selected' : '' ?>>
                                    Penang
                                </option>

                                <option value="Kedah"
                                    <?= ($_POST['state'] ?? '') === 'Kedah' ? 'selected' : '' ?>>
                                    Kedah
                                </option>

                                <option value="Perak"
                                    <?= ($_POST['state'] ?? '') === 'Perak' ? 'selected' : '' ?>>
                                    Perak
                                </option>

                                <option value="Perlis"
                                    <?= ($_POST['state'] ?? '') === 'Perlis' ? 'selected' : '' ?>>
                                    Perlis
                                </option>

                                <option value="Selangor"
                                    <?= ($_POST['state'] ?? '') === 'Selangor' ? 'selected' : '' ?>>
                                    Selangor
                                </option>

                                <option value="Kuala Lumpur"
                                    <?= ($_POST['state'] ?? '') === 'Kuala Lumpur' ? 'selected' : '' ?>>
                                    Kuala Lumpur
                                </option>

                                <option value="Johor"
                                    <?= ($_POST['state'] ?? '') === 'Johor' ? 'selected' : '' ?>>
                                    Johor
                                </option>

                                <option value="Melaka"
                                    <?= ($_POST['state'] ?? '') === 'Melaka' ? 'selected' : '' ?>>
                                    Melaka
                                </option>

                                <option value="Negeri Sembilan"
                                    <?= ($_POST['state'] ?? '') === 'Negeri Sembilan' ? 'selected' : '' ?>>
                                    Negeri Sembilan
                                </option>

                                <option value="Pahang"
                                    <?= ($_POST['state'] ?? '') === 'Pahang' ? 'selected' : '' ?>>
                                    Pahang
                                </option>

                                <option value="Kelantan"
                                    <?= ($_POST['state'] ?? '') === 'Kelantan' ? 'selected' : '' ?>>
                                    Kelantan
                                </option>

                                <option value="Terengganu"
                                    <?= ($_POST['state'] ?? '') === 'Terengganu' ? 'selected' : '' ?>>
                                    Terengganu
                                </option>

                                <option value="Sabah"
                                    <?= ($_POST['state'] ?? '') === 'Sabah' ? 'selected' : '' ?>>
                                    Sabah
                                </option>

                                <option value="Sarawak"
                                    <?= ($_POST['state'] ?? '') === 'Sarawak' ? 'selected' : '' ?>>
                                    Sarawak
                                </option>

                                <option value="Putrajaya"
                                    <?= ($_POST['state'] ?? '') === 'Putrajaya' ? 'selected' : '' ?>>
                                    Putrajaya
                                </option>

                                <option value="Labuan"
                                    <?= ($_POST['state'] ?? '') === 'Labuan' ? 'selected' : '' ?>>
                                    Labuan
                                </option>

                            </select>

                        </div>

                    </div>

                    <button
                        type="submit"
                        name="place_order">
                        Place Order
                    </button>

                </form>

            </div>

        </div>

        <div class="checkout-right">

            <div class="checkout-summary-card">

                <h2>Order Summary</h2>

                <?php foreach ($cartItems as $item): ?>

                    <div class="checkout-summary-item">

                        <h3>
                            <?= htmlspecialchars($item['product_name']) ?>
                        </h3>

                        <p>
                            Size:
                            <?= htmlspecialchars($item['size']) ?>
                        </p>

                        <p>
                            Color:
                            <?= htmlspecialchars($item['color']) ?>
                        </p>

                        <div class="summary-price-row">

                            <span>
                                RM <?= number_format($item['price'], 2) ?>
                                ×
                                <?= $item['quantity'] ?>
                            </span>

                            <strong>
                                RM <?= number_format($item['subtotal'], 2) ?>
                            </strong>

                        </div>

                    </div>

                <?php endforeach; ?>


                <div class="checkout-cost-summary">

                    <div class="checkout-cost-row">
                        <span>Subtotal</span>

                        <strong>
                            RM <?= number_format($grandTotal, 2) ?>
                        </strong>
                    </div>

                    <div class="checkout-cost-row">
                        <span>Shipping</span>

                        <strong id="shippingFeeDisplay">
                            Enter shipping address
                        </strong>
                    </div>

                    <div class="checkout-voucher-section">

                        <div class="voucher-input-row">

                            <input
                                type="text"
                                id="voucherCode"
                                name="voucher_code"
                                form="checkoutForm"
                                placeholder="Voucher code"
                                autocomplete="off">

                            <button
                                type="button"
                                id="applyVoucherBtn"
                                class="voucher-apply-btn">
                                Apply
                            </button>

                        </div>

                        <button
                            type="button"
                            id="viewVouchersBtn"
                            class="view-vouchers-btn">
                            View My Vouchers
                        </button>

                        <p
                            id="voucherMessage"
                            class="voucher-message">
                        </p>

                    </div>


                    <div
                        class="checkout-cost-row"
                        id="discountRow"
                        style="display:none;">

                        <span>Voucher Discount</span>

                        <strong id="discountDisplay">
                            - RM 0.00
                        </strong>

                    </div>

                    <div class="checkout-total">
                        <span>Total</span>

                        <strong id="checkoutTotalDisplay">
                            RM <?= number_format($grandTotal, 2) ?>
                        </strong>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<div
    class="voucher-modal-overlay"
    id="voucherModal">

    <div class="voucher-modal">

        <div class="voucher-modal-header">

            <h2>My Vouchers</h2>

            <button
                type="button"
                id="closeVoucherModal"
                class="voucher-modal-close">
                ×
            </button>

        </div>


        <div class="voucher-modal-body">

            <?php if (empty($userVouchers)): ?>

                <div class="voucher-empty">
                    You do not have any available vouchers.
                </div>

            <?php else: ?>

                <?php foreach ($userVouchers as $voucher): ?>

                    <div class="voucher-card">

                        <div class="voucher-card-info">

                            <strong>
                                <?= htmlspecialchars($voucher['name']) ?>
                            </strong>


                            <?php if ($voucher['discountType'] === 'PERCENTAGE'): ?>

                                <span class="voucher-value">
                                    <?= (int)$voucher['percentage'] ?>% OFF
                                </span>

                            <?php elseif ($voucher['discountType'] === 'AMOUNT'): ?>

                                <span class="voucher-value">
                                    RM <?= number_format($voucher['amount'], 2) ?> OFF
                                </span>

                            <?php elseif ($voucher['discountType'] === 'BUY_X_GET_Y'): ?>

                                <span class="voucher-value">
                                    Buy <?= (int)$voucher['buy_x'] ?>
                                    Get <?= (int)$voucher['get_y'] ?>
                                </span>

                            <?php endif; ?>


                            <span>
                                Code:
                                <?= htmlspecialchars($voucher['code']) ?>
                            </span>

                            <small>
                                Valid until
                                <?= date(
                                    'd/m/Y',
                                    strtotime($voucher['endDate'])
                                ) ?>
                            </small>

                        </div>


                        <button
                            type="button"
                            class="use-voucher-btn"
                            data-code="<?= htmlspecialchars($voucher['code']) ?>">
                            Use
                        </button>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<script>
    const userVouchers =
        <?= json_encode($userVouchers) ?>;

    let appliedVoucher = null;
    let voucherDiscount = 0;


    function applyVoucher() {

        const enteredCode =
            voucherCode.value.trim().toUpperCase();

        const voucherMessage =
            document.getElementById('voucherMessage');

        const discountRow =
            document.getElementById('discountRow');

        const discountDisplay =
            document.getElementById('discountDisplay');


        const voucher =
            userVouchers.find(function(item) {

                return item.code.toUpperCase() === enteredCode;
            });


        if (!voucher) {

            appliedVoucher = null;
            voucherDiscount = 0;

            discountRow.style.display = 'none';

            voucherMessage.textContent =
                'Invalid or unavailable voucher.';

            updateShippingFee();

            return;
        }


        appliedVoucher = voucher;

        if (voucher.discountType === 'PERCENTAGE') {

            voucherDiscount =
                cartSubtotal *
                (Number(voucher.percentage) / 100);

        } else if (voucher.discountType === 'AMOUNT') {

            voucherDiscount =
                Number(voucher.amount);

        } else {

            appliedVoucher = null;
            voucherDiscount = 0;

            discountRow.style.display = 'none';

            voucherMessage.textContent =
                'This voucher type is not supported for this checkout yet.';

            updateShippingFee();

            return;
        }


        voucherDiscount =
            Math.min(
                voucherDiscount,
                cartSubtotal
            );


        discountDisplay.textContent =
            '- RM ' + voucherDiscount.toFixed(2);

        discountRow.style.display = 'flex';

        voucherMessage.textContent =
            voucher.code + ' applied.';


        updateShippingFee();
    }


    document
        .getElementById('applyVoucherBtn')
        .addEventListener(
            'click',
            applyVoucher
        );

    async function initAutocomplete() {

        const {
            PlaceAutocompleteElement
        } =
        await google.maps.importLibrary("places");

        const {
            Map
        } =
        await google.maps.importLibrary("maps");

        const {
            AdvancedMarkerElement
        } =
        await google.maps.importLibrary("marker");

        const defaultLocation = {
            lat: 4.2105,
            lng: 101.9758
        };

        // Create map
        const map = new Map(
            document.getElementById("shipping-map"), {
                center: defaultLocation,
                zoom: 6,
                mapId: "DEMO_MAP_ID"
            }
        );


        // Create marker
        const marker = new AdvancedMarkerElement({
            map: map,
            position: defaultLocation
        });

        // Create AutoComplete
        const autocomplete =
            new PlaceAutocompleteElement({
                includedRegionCodes: ["my"]
            });

        autocomplete.placeholder = "Enter your shipping address";

        document
            .getElementById("autocomplete-container")
            .appendChild(autocomplete);

        autocomplete.addEventListener("gmp-select", async (event) => {

            const place = event.placePrediction.toPlace();

            await place.fetchFields({
                fields: [
                    "addressComponents",
                    "location"
                ]
            });

            if (!place.location) {
                return;
            }

            // Latitude / Longitude
            const latitude =
                place.location.lat();

            const longitude =
                place.location.lng();

            document.getElementById("latitude").value =
                place.location.lat();

            document.getElementById("longitude").value =
                place.location.lng();

            // Move Map
            const selectedLocation = {
                lat: latitude,
                lng: longitude
            };

            map.setCenter(selectedLocation);
            map.setZoom(17);

            marker.position = selectedLocation;

            // Address Component
            let premise = "";
            let streetNumber = "";
            let route = "";
            let neighborhood = "";
            let postcode = "";
            let city = "";
            let district = "";
            let state = "";

            for (const component of place.addressComponents ?? []) {

                const types = component.types;

                if (types.includes("premise")) {
                    premise = component.longText;
                }

                if (types.includes("street_number")) {
                    streetNumber = component.longText;
                }

                if (types.includes("route")) {
                    route = component.longText;
                }

                if (
                    types.includes("sublocality") ||
                    types.includes("sublocality_level_1") ||
                    types.includes("neighborhood")
                ) {
                    neighborhood = component.longText;
                }

                if (types.includes("postal_code")) {
                    postcode = component.longText;
                }

                if (
                    types.includes("locality") ||
                    types.includes("postal_town")
                ) {
                    city = component.longText;
                }

                if (types.includes("administrative_area_level_2")) {
                    district = component.longText;
                }

                if (types.includes("administrative_area_level_1")) {
                    state = component.longText;
                }
            }

            if (city === "") {
                city = district;
            }

            // Build Street
            let street = "";

            if (premise !== "") {
                street += premise;
            }

            if (streetNumber !== "") {
                street += (street !== "" ? ", " : "") + streetNumber;
            }

            if (route !== "") {
                street += (street !== "" ? " " : "") + route;
            }

            if (neighborhood !== "") {
                street += (street !== "" ? ", " : "") + neighborhood;
            }

            const stateMap = {
                "Pulau Pinang": "Penang",
                "Penang": "Penang",

                "Kedah": "Kedah",
                "Perak": "Perak",
                "Perlis": "Perlis",
                "Selangor": "Selangor",
                "Johor": "Johor",
                "Melaka": "Melaka",
                "Malacca": "Melaka",
                "Negeri Sembilan": "Negeri Sembilan",
                "Pahang": "Pahang",
                "Kelantan": "Kelantan",
                "Terengganu": "Terengganu",
                "Sabah": "Sabah",
                "Sarawak": "Sarawak",

                "Kuala Lumpur": "Kuala Lumpur",
                "Federal Territory of Kuala Lumpur": "Kuala Lumpur",

                "Putrajaya": "Putrajaya",
                "Federal Territory of Putrajaya": "Putrajaya",

                "Labuan": "Labuan",
                "Federal Territory of Labuan": "Labuan"
            };

            const normalizedState =
                stateMap[state] ?? state;

            // Fill Form
            document.getElementById("address").value = street;
            document.getElementById("postcode").value = postcode;
            document.getElementById("city").value = city;
            document.getElementById("state").value = normalizedState;

            updateShippingFee();
        });
    }

    const cartSubtotal =
        <?= json_encode((float)$grandTotal) ?>;


    function updateShippingFee() {

        const state =
            document.getElementById('state').value;

        const shippingFeeDisplay =
            document.getElementById('shippingFeeDisplay');

        const checkoutTotalDisplay =
            document.getElementById('checkoutTotalDisplay');

        let shippingFee = 0;


        if (state === '') {

            shippingFeeDisplay.textContent =
                'Enter shipping address';

        } else {

            const eastMalaysiaStates = [
                'Sabah',
                'Sarawak',
                'Labuan'
            ];

            shippingFee =
                eastMalaysiaStates.includes(state) ?
                20 :
                10;

            shippingFeeDisplay.textContent =
                'RM ' + shippingFee.toFixed(2);
        }


        const finalTotal =
            Math.max(
                0,
                cartSubtotal +
                shippingFee -
                voucherDiscount
            );


        checkoutTotalDisplay.textContent =
            'RM ' + finalTotal.toFixed(2);
    }

    document
        .getElementById('state')
        .addEventListener(
            'change',
            updateShippingFee
        );


    updateShippingFee();

    const voucherModal =
        document.getElementById('voucherModal');

    const viewVouchersBtn =
        document.getElementById('viewVouchersBtn');

    const closeVoucherModal =
        document.getElementById('closeVoucherModal');

    const voucherCode =
        document.getElementById('voucherCode');


    viewVouchersBtn.addEventListener(
        'click',
        function() {

            voucherModal.classList.add('show');
        }
    );


    closeVoucherModal.addEventListener(
        'click',
        function() {

            voucherModal.classList.remove('show');
        }
    );


    voucherModal.addEventListener(
        'click',
        function(event) {

            if (event.target === voucherModal) {

                voucherModal.classList.remove('show');
            }
        }
    );


    document
        .querySelectorAll('.use-voucher-btn')
        .forEach(function(button) {

            button.addEventListener(
                'click',
                function() {

                    voucherCode.value =
                        button.dataset.code;

                    voucherModal.classList.remove('show');

                    applyVoucher();
                }
            );

        });
</script>

<?php $googleMapsApiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? ''; ?>

<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode($googleMapsApiKey) ?>&loading=async&libraries=places,marker&language=en&region=MY&callback=initAutocomplete"
    async>
</script>