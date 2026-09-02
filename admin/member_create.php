<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

require_role('admin');

$GLOBALS['email']         = $GLOBALS['email'] ?? '';
$GLOBALS['first_name']    = $GLOBALS['first_name'] ?? '';
$GLOBALS['last_name']     = $GLOBALS['last_name'] ?? '';
$GLOBALS['date_of_birth'] = $GLOBALS['date_of_birth'] ?? '';
$GLOBALS['phone_number']  = $GLOBALS['phone_number'] ?? '';
$GLOBALS['address_line1'] = $GLOBALS['address_line1'] ?? '';
$GLOBALS['state']         = $GLOBALS['state'] ?? '';
$GLOBALS['city']          = $GLOBALS['city'] ?? '';
$GLOBALS['postcode']      = $GLOBALS['postcode'] ?? '';
$GLOBALS['gender']        = $GLOBALS['gender'] ?? 'prefer_not_to_say';

$genderOptions = $_genders ?? [
    'male' => 'Male',
    'female' => 'Female',
    'other' => 'Other',
    'prefer_not_to_say' => 'Prefer not to say',
];

include_head("Add Member - Online Shopping System");
?>

<div class="container" style="max-width: 750px;">
    <a href="member_list.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Member List</a>
    <h2>Add Member / Staff</h2>
    <p class="subtitle">An invite link will be emailed for the user to set their own password.</p>

    <div id="alertBox" class="alert-box" style="display:none;" role="status" aria-live="polite"></div>

    <form id="memberCreateForm" method="POST" action="process_member_create.php">
        <?php echo csrf_field(); ?>

        <div class="form-row">
            <div class="form-group">
                <label for="email">Email Address *</label>
                <?php html_email('email', 'placeholder="john.doe@example.com" required'); ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First Name *</label>
                <?php html_text('first_name', 'required placeholder="John"'); ?>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name *</label>
                <?php html_text('last_name', 'required placeholder="Doe"'); ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="gender">Gender</label>
                <?php html_select('gender', $genderOptions, null); ?>
            </div>
            <div class="form-group" novalidate>
                <label for="date_of_birth">Date of Birth *</label>
                <?php
                html_date('date_of_birth', 'class="form-control" min="' . $min . '" max="' . $max . '" required');
                err('date_of_birth');
                ?>
            </div>
            <div class="form-group">
                <label for="phone_number">Phone Number *</label>
                <?php html_phone('phone_number', 'placeholder="0123456789" required'); ?>
            </div>
        </div>

        <div class="form-group">
            <label for="address_line1">Address Line 1 *</label>
            <?php html_text('address_line1', 'placeholder="123 Main Street" required'); ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="state">State *</label>
                <?php html_datalist_data('state', 'Select state', 'state', 'required'); ?>
            </div>
            <div class="form-group">
                <label for="city">City *</label>
                <?php html_datalist_data('city', 'Select city', 'city', 'required disabled'); ?>
            </div>
            <div class="form-group">
                <label for="postcode">Postal Code *</label>
                <?php html_datalist_data('postcode', 'Select postcode', 'postcode', 'pattern="[0-9]{5}" required disabled'); ?>
            </div>
        </div>

        <hr style="border:0; border-top:1px solid var(--border-color); margin: 20px 0;">
        <h3>Account Settings</h3>

        <div class="form-row">
            <div class="form-group">
                <label for="role">Role *</label>
                <?php
                html_select('role', [
                    'member' => 'Member',
                    'admin' => 'Admin',
                ], null, 'required');
                err('role');
                ?>
            </div>
            <div class="form-group">
                <label for="status">Initial Status *</label>
                <!-- <select name="status" id="status" required>
                    <option value="pending" selected>Pending (requires invite link)</option>
                    <option value="active">Active</option>
                </select> -->
                <?php html_select('status', [
                    'pending' => 'Pending (requires invite link)',
                    'active' => 'Active',
                ], null, 'required'); ?>
            </div>
        </div>

        <div class="form-actions-stack">
            <?php html_button('submit', 'Create Account', 'class="btn btn-primary" id="btn-member-create"'); ?>
        </div>
    </form>
</div>

<?php include_foot(); ?>