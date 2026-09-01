<?php

include '../app/_base.php';


// ============================================================
// AJAX: Update selected shipments to Preparing
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    $action = $data['action'] ?? '';
    $shippingIds = $data['shipping_ids'] ?? [];

    if ($action !== 'generate_shipping_list') {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid action.'
        ]);

        exit;
    }

    if (
        !is_array($shippingIds) ||
        empty($shippingIds)
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'No shipment selected.'
        ]);

        exit;
    }

    try {

        $_db->beginTransaction();

        $updated = 0;

        foreach ($shippingIds as $shippingId) {

            $stm = $_db->prepare("
                SELECT
                    shipping_id,
                    order_id
                FROM shipping
                WHERE shipping_id = ?
                  AND shipping_status = 'Pending'
            ");

            $stm->execute([
                $shippingId
            ]);

            $shipping = $stm->fetch();

            if (!$shipping) {
                continue;
            }


            $stm = $_db->prepare("
                UPDATE shipping
                SET shipping_status = 'Preparing'
                WHERE shipping_id = ?
                  AND shipping_status = 'Pending'
            ");

            $stm->execute([
                $shippingId
            ]);


            $orderStm = $_db->prepare("
                UPDATE orders
                SET order_status = 'Processing'
                WHERE order_id = ?
            ");

            $orderStm->execute([
                $shipping['order_id']
            ]);

            $updated++;
        }

        $_db->commit();

        echo json_encode([
            'success' => true,
            'updated' => $updated
        ]);
    } catch (Exception $e) {

        if ($_db->inTransaction()) {
            $_db->rollBack();
        }

        echo json_encode([
            'success' => false,
            'message' => 'Unable to update shipment status.'
        ]);
    }

    exit;
}



// ============================================================
// Get Pending Shipments
// ============================================================

$stm = $_db->prepare("
    SELECT
        o.order_id,
        o.order_date,
        o.total_amount,

        s.shipping_id,
        s.recipient_name,
        s.phone_number,
        s.shipping_street,
        s.shipping_city,
        s.shipping_state,
        s.shipping_postcode,
        s.shipping_country,
        s.shipping_status

    FROM orders o

    JOIN shipping s
        ON o.order_id = s.order_id

    JOIN payment p
        ON o.order_id = p.order_id

    WHERE p.payment_status = 'Paid'
      AND s.shipping_status = 'Pending'

    ORDER BY o.order_date ASC, o.order_id ASC
");

$stm->execute();

$pendingShipments = $stm->fetchAll();

// ============================================================
// Get Generated / Preparing Shipments
// ============================================================

$preparingStm = $_db->prepare("
    SELECT
        o.order_id,
        o.order_date,
        o.total_amount,

        s.shipping_id,
        s.recipient_name,
        s.phone_number,
        s.shipping_street,
        s.shipping_city,
        s.shipping_state,
        s.shipping_postcode,
        s.shipping_country,
        s.shipping_status

    FROM orders o

    JOIN shipping s
        ON o.order_id = s.order_id

    JOIN payment p
        ON o.order_id = p.order_id

    WHERE p.payment_status = 'Paid'
      AND s.shipping_status = 'Preparing'

    ORDER BY o.order_date ASC, o.order_id ASC
");

$preparingStm->execute();

$preparingShipments =
    $preparingStm->fetchAll();

?>

<link rel="stylesheet" href="../app/css/app.css">

<div class="order-module shipping-print-page">

    <div class="admin-detail-header">

        <div>

            <h1>Shipping Preparation List</h1>

            <p>
                Select the shipments to print and prepare.
            </p>

        </div>

        <a
            href="order_list.php"
            class="btn btn-secondary">
            Back to Orders
        </a>

    </div>

    <div class="print-only shipping-print-header">

        <h1>Shipping Preparation List</h1>

        <p>
            Printed:
            <?= date('d/m/Y h:i A') ?>
        </p>

        <p id="printShipmentCount"></p>

    </div>

    <div class="shipping-table-wrapper">

        <div class="shipping-mode-tabs">
            <button
                type="button"
                class="shipping-mode-btn active"
                data-mode="generate">
                Generate
            </button>

            <button
                type="button"
                class="shipping-mode-btn"
                data-mode="print">
                Print
            </button>
        </div>

        <div class="shipping-mode-content">
            <div class="pending-shipping-section shipping-mode-section" id="generateSection">

                <h2>Pending Shipments</h2>
                <p>Select pending shipments to generate for preparation.</p>

                <?php if (!$pendingShipments): ?>

                    <div class="shipping-table-content">

                        <h2>No Pending Shipments</h2>

                        <p>
                            There are currently no paid orders waiting for shipping preparation.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="shipping-table-content">

                        <div class="shipping-list-toolbar">

                            <label class="select-all-label">

                                <input
                                    type="checkbox"
                                    id="selectAll">

                                Select All

                            </label>

                            <span id="selectedCount">
                                0 shipment(s) selected
                            </span>

                        </div>


                        <table class="order-table shipping-print-table">

                            <thead>

                                <tr>

                                    <th class="selection-column">
                                        Select
                                    </th>

                                    <th>
                                        Order ID
                                    </th>

                                    <th>
                                        Order Date
                                    </th>

                                    <th>
                                        Recipient
                                    </th>

                                    <th>
                                        Phone
                                    </th>

                                    <th>
                                        Delivery Address
                                    </th>

                                    <th>
                                        Postcode
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($pendingShipments as $shipment): ?>

                                    <tr
                                        data-shipping-id="<?= htmlspecialchars($shipment['shipping_id']) ?>">

                                        <td class="selection-column">

                                            <input
                                                type="checkbox"
                                                class="pending-shipment-checkbox"
                                                value="<?= htmlspecialchars($shipment['shipping_id']) ?>">

                                        </td>


                                        <td>
                                            #<?= htmlspecialchars($shipment['order_id']) ?>
                                        </td>


                                        <td>
                                            <?= htmlspecialchars($shipment['order_date']) ?>
                                        </td>


                                        <td>
                                            <?= htmlspecialchars($shipment['recipient_name']) ?>
                                        </td>


                                        <td>
                                            <?= htmlspecialchars($shipment['phone_number']) ?>
                                        </td>


                                        <td>

                                            <?= htmlspecialchars($shipment['shipping_street']) ?>,
                                            <?= htmlspecialchars($shipment['shipping_city']) ?>,
                                            <?= htmlspecialchars($shipment['shipping_state']) ?>,
                                            <?= htmlspecialchars($shipment['shipping_country']) ?>

                                        </td>


                                        <td>
                                            <?= htmlspecialchars($shipment['shipping_postcode']) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>


                        <div class="admin-detail-actions shipping-print-actions">

                            <button
                                type="button"
                                class="btn"
                                onclick="generateShippingList()">
                                Generate Selected
                            </button>

                        </div>

                    </div>

                <?php endif; ?>
            </div>

            <div class="shipping-generated-section shipping-mode-section" id="printSection" style="display: none;">

                <h2>Preparing Shipments</h2>

                <p class="section-description">
                    Select prepared shipments to print.
                </p>


                <?php if (empty($preparingShipments)): ?>

                    <div class="empty-message">

                        <p>
                            No generated shipments are currently waiting for preparation.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="shipping-table-content">

                        <div class="shipping-list-toolbar">

                            <label class="select-all-label">

                                <input
                                    type="checkbox"
                                    id="selectAllPreparing">

                                Select All

                            </label>

                            <span id="preparingSelectedCount">
                                0 shipment(s) selected
                            </span>

                        </div>


                        <table
                            class="order-table shipping-print-table"
                            id="preparingShippingTable">

                            <thead>

                                <tr>

                                    <th class="selection-column">
                                        Select
                                    </th>

                                    <th>Order ID</th>
                                    <th>Order Date</th>
                                    <th>Recipient</th>
                                    <th>Phone</th>
                                    <th>Delivery Address</th>
                                    <th>Postcode</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($preparingShipments as $shipment): ?>

                                    <tr
                                        data-preparing-shipping-id="<?= htmlspecialchars($shipment['shipping_id']) ?>">

                                        <td class="selection-column">

                                            <input
                                                type="checkbox"
                                                class="preparing-shipment-checkbox"
                                                value="<?= htmlspecialchars($shipment['shipping_id']) ?>">

                                        </td>

                                        <td>
                                            #<?= htmlspecialchars($shipment['order_id']) ?>
                                        </td>

                                        <td>
                                            <?= date(
                                                'd/m/Y',
                                                strtotime($shipment['order_date'])
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($shipment['recipient_name']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($shipment['phone_number']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($shipment['shipping_street']) ?>,
                                            <?= htmlspecialchars($shipment['shipping_city']) ?>,
                                            <?= htmlspecialchars($shipment['shipping_state']) ?>,
                                            <?= htmlspecialchars($shipment['shipping_country']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($shipment['shipping_postcode']) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>


                        <div class="admin-detail-actions shipping-print-actions">

                            <button
                                type="button"
                                class="btn"
                                onclick="printPreparingShipments()">
                                Print Selected
                            </button>

                        </div>

                    </div>

                <?php endif; ?>

            </div>
            
        </div>

    </div>

</div>


<script>
    const selectAll =
        document.getElementById('selectAll');

    const shipmentCheckboxes =
        document.querySelectorAll('.pending-shipment-checkbox');

    const selectedCount =
        document.getElementById('selectedCount');

    const selectAllPreparing =
        document.getElementById('selectAllPreparing');

    const preparingCheckboxes =
        document.querySelectorAll(
            '.preparing-shipment-checkbox'
        );

    const preparingSelectedCount =
        document.getElementById(
            'preparingSelectedCount'
        );

    const modeButtons =
        document.querySelectorAll('.shipping-mode-btn');

    const generateSection =
        document.getElementById('generateSection');

    const printSection =
        document.getElementById('printSection');

    modeButtons.forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                modeButtons.forEach(
                    function(btn) {
                        btn.classList.remove('active');
                    }
                );

                button.classList.add('active');


                if (button.dataset.mode === 'generate') {

                    generateSection.style.display = 'block';
                    printSection.style.display = 'none';

                } else {

                    generateSection.style.display = 'none';
                    printSection.style.display = 'block';
                }
            }
        );
    });

    function updatePreparingSelectedCount() {

        const checked =
            document.querySelectorAll(
                '.preparing-shipment-checkbox:checked'
            );

        if (preparingSelectedCount) {

            preparingSelectedCount.textContent =
                checked.length +
                ' shipment(s) selected';
        }

        if (
            selectAllPreparing &&
            preparingCheckboxes.length > 0
        ) {

            selectAllPreparing.checked =
                checked.length ===
                preparingCheckboxes.length;
        }
    }


    if (selectAllPreparing) {

        selectAllPreparing.addEventListener(
            'change',
            function() {

                preparingCheckboxes.forEach(
                    function(checkbox) {

                        checkbox.checked =
                            selectAllPreparing.checked;
                    }
                );

                updatePreparingSelectedCount();
            }
        );
    }


    preparingCheckboxes.forEach(
        function(checkbox) {

            checkbox.addEventListener(
                'change',
                updatePreparingSelectedCount
            );
        }
    );

    function updateSelectedCount() {

        const checked =
            document.querySelectorAll(
                '.pending-shipment-checkbox:checked'
            );

        selectedCount.textContent =
            checked.length +
            ' shipment(s) selected';

        if (shipmentCheckboxes.length > 0) {

            selectAll.checked =
                checked.length ===
                shipmentCheckboxes.length;
        }
    }



    if (selectAll) {

        selectAll.addEventListener(
            'change',
            function() {

                shipmentCheckboxes.forEach(
                    function(checkbox) {

                        checkbox.checked =
                            selectAll.checked;
                    }
                );

                updateSelectedCount();
            }
        );
    }



    shipmentCheckboxes.forEach(
        function(checkbox) {

            checkbox.addEventListener(
                'change',
                function() {

                    updateSelectedCount();
                }
            );
        }
    );

    async function generateShippingList() {

        const selectedShippingIds = [];

        document
            .querySelectorAll(
                '.pending-shipment-checkbox:checked'
            )
            .forEach(function(checkbox) {

                selectedShippingIds.push(
                    checkbox.value
                );

            });


        if (selectedShippingIds.length === 0) {

            alert(
                'Please select at least one shipment.'
            );

            return;
        }


        if (
            !confirm(
                'Generate shipping list for the selected shipment(s)?'
            )
        ) {
            return;
        }


        try {

            const response =
                await fetch(
                    'shipping_print.php', {
                        method: 'POST',

                        headers: {
                            'Content-Type': 'application/json'
                        },

                        body: JSON.stringify({

                            action: 'generate_shipping_list',

                            shipping_ids: selectedShippingIds
                        })
                    }
                );


            const result =
                await response.json();


            if (result.success) {

                alert(
                    result.updated +
                    ' shipment(s) generated successfully.'
                );

                location.reload();

            } else {

                alert(
                    result.message ??
                    'Unable to generate shipping list.'
                );
            }

        } catch (error) {

            console.error(error);

            alert(
                'An error occurred while generating the shipping list.'
            );
        }
    }

    function printPreparingShipments() {

        const selectedShippingIds = [];

        document
            .querySelectorAll(
                '.preparing-shipment-checkbox:checked'
            )
            .forEach(function(checkbox) {

                selectedShippingIds.push(
                    checkbox.value
                );

            });


        if (selectedShippingIds.length === 0) {

            alert(
                'Please select at least one shipment to print.'
            );

            return;
        }

        document
            .querySelectorAll(
                'tr[data-preparing-shipping-id]'
            )
            .forEach(function(row) {

                if (
                    !selectedShippingIds.includes(
                        row.dataset.preparingShippingId
                    )
                ) {

                    row.classList.add(
                        'hide-for-print'
                    );
                }

            });

        const printShipmentCount =
            document.getElementById('printShipmentCount');

        if (printShipmentCount) {

            printShipmentCount.textContent =
                'Total Shipments: ' +
                selectedShippingIds.length;
        }

        window.print();


        document
            .querySelectorAll('.hide-for-print')
            .forEach(function(row) {

                row.classList.remove(
                    'hide-for-print'
                );

            });
    }
</script>