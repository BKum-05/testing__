<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

require_login();

$pdo = get_pdo();
$userId = (int) $_SESSION['user_id'];

$user = fetch_user_by_id($pdo, $userId);

if ($user !== null && user_requires_password_reset($user)) {
    redirect('login/set_password.php');
}

$GLOBALS['first_name']    = $user['first_name'] ?? '';
$GLOBALS['last_name']     = $user['last_name'] ?? '';
$GLOBALS['gender']        = $user['gender'] ?? 'prefer_not_to_say';
$GLOBALS['phone_number']  = $user['phone_number'] ?? '';
$GLOBALS['address_line1'] = $user['address_line1'] ?? '';
$GLOBALS['city']          = $user['city'] ?? '';
$GLOBALS['state']         = $user['state'] ?? '';
$GLOBALS['postcode']      = $user['postal_code'] ?? '';
$GLOBALS['country']       = $user['country'] ?? 'Malaysia';
$GLOBALS['date_of_birth'] = !empty($user['date_of_birth'])
    ? date('d/m/Y', strtotime($user['date_of_birth']))
    : '';

$avatarUrl       = get_avatar_url($user['avatar_url'] ?? null);
$cloudflareToken = $cloudflareToken ?? '';

$genderOptions = $_genders ?? [
    'male'              => 'Male',
    'female'            => 'Female',
    'other'             => 'Other',
    'prefer_not_to_say' => 'Prefer not to say',
];

include_head("Edit Profile - Online Shopping System");
?>

<div class="container account-dashboard">
    <div class="dashboard-header">
        <h2>Manage Profile</h2>
        <p class="subtitle">Update your personal information, profile photo, and security details.</p>
    </div>

    <?php if ($msg = temp('flash_success')): ?>
        <div class="alert-box alert-success"><?php echo encode($msg); ?></div>
    <?php endif; ?>

    <?php if ($msg = temp('flash_notice')): ?>
        <div class="alert-box alert-notice"><?php echo encode($msg); ?></div>
    <?php endif; ?>

    <div id="alertBox" class="alert-box" style="display: none;" role="status" aria-live="polite"></div>

    <form id="profileForm" method="post" action="process_profile.php" enctype="multipart/form-data" class="profile-form">
        <?php echo csrf_field(); ?>

        <!-- MARK: Personal -->
        <div class="card profile-card">
            <div class="card-header">
                <h3>Personal Details</h3>
            </div>

            <div class="card-body">
                <!-- Avatar Upload Row -->
                <div class="avatar-upload-wrapper">
                    <div class="avatar-preview-container">
                        <img id="avatarPreview" src="<?php echo encode($avatarUrl); ?>" alt="Profile Photo" class="avatar-preview">
                    </div>
                    <div class="avatar-upload-controls">
                        <label for="avatar" class="form-label">Change Profile Photo</label>
                        <?php html_file('avatar', 'image/png, image/jpeg, image/webp'); ?>
                        <span class="field-hint">Max file size: 2MB. Supported formats: JPG, PNG, WEBP.</span>
                        <?php err('avatar'); ?>
                    </div>
                </div>

                <hr class="divider">

                <!-- Name Details -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <?php html_text('first_name', 'placeholder="Enter your first name" required'); ?>
                        <?php err('first_name'); ?>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <?php html_text('last_name', 'placeholder="Enter your last name" required'); ?>
                        <?php err('last_name'); ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <?php html_select('gender', $genderOptions, null); ?>
                        <?php err('gender'); ?>
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth *</label>
                        <?php
                        $max = date('Y-m-d', strtotime('-13 years'));
                        $min = date('Y-m-d', strtotime('-120 years'));

                        html_date('date_of_birth', 'class="form-control" min="' . $min . '" max="' . $max . '" required');
                        err('date_of_birth');
                        ?>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <?php html_phone('phone_number', 'placeholder="Enter your phone number"'); ?>
                        <?php err('phone_number'); ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address_line1">Address Line 1</label>
                    <?php html_text('address_line1', 'placeholder="Enter street address"'); ?>
                    <?php err('address_line1'); ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="state">State *</label>
                        <?php html_datalist_data('state', 'Select state', 'state', 'required'); ?>
                        <?php err('state'); ?>
                    </div>
                    <div class="form-group">
                        <label for="city">City *</label>
                        <?php html_datalist_data('city', 'Select city', 'city', 'required disabled'); ?>
                        <?php err('city'); ?>
                    </div>
                    <div class="form-group">
                        <label for="postcode">Postal Code *</label>
                        <?php html_datalist_data('postcode', 'Select postcode', 'postcode', 'pattern="[0-9]{5}" inputmode="numeric" required disabled'); ?>
                        <?php err('postcode'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- MARK: Security -->
        <div class="card profile-card">
            <div class="card-header">
                <h3>Security Settings</h3>
            </div>

            <div class="card-body">
                <?php if (!empty($user['password_hash'])): ?>
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="password-wrapper">
                            <?php html_password('current_password', 'placeholder="Leave blank to keep current password"'); ?>
                            <span class="toggle-password" role="button" tabindex="0" title="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <?php err('current_password'); ?>
                    </div>
                <?php else: ?>
                    <div class="alert-box alert-notice" style="display:block; margin-bottom: 16px;">
                        Your account currently signs in with Google only. Set a password below to also enable email/password login — no current password is required.
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password"><?php echo !empty($user['password_hash']) ? 'New Password' : 'Password'; ?></label>
                        <div class="password-wrapper">
                            <?php html_password('new_password', 'placeholder="Enter new password"'); ?>
                            <span class="toggle-password" role="button" tabindex="0" title="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <?php err('new_password'); ?>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><?php echo !empty($user['password_hash']) ? 'Confirm New Password' : 'Confirm Password'; ?></label>
                        <div class="password-wrapper">
                            <?php html_password('confirm_password', 'placeholder="Confirm new password"'); ?>
                            <span class="toggle-password" role="button" tabindex="0" title="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <?php err('confirm_password'); ?>
                    </div>
                </div>

                <ul class="password-requirements" id="password-requirements">
                    <li id="req-length"><i class="fas fa-circle"></i> At least 8 characters long</li>
                    <li id="req-casing"><i class="fas fa-circle"></i> Includes uppercase and lowercase letters</li>
                    <li id="req-number"><i class="fas fa-circle"></i> Includes at least one number</li>
                    <li id="req-symbol"><i class="fas fa-circle"></i> Includes at least one symbol</li>
                    <li id="req-match"><i class="fas fa-circle"></i> Passwords match</li>
                </ul>
            </div>
        </div>

        <div class="form-actions-stack">
            <?php
            html_cloudflare($cloudflareToken);
            html_button('submit', 'Save Changes', 'class="btn-primary btn-block" id="btn-profile-submit"');
            ?>
            <a href="<?php echo encode(get_dashboard_url()); ?>" class="btn btn-outline btn-block">
                <i class="fas fa-arrow-left"></i> Back to Account Dashboard
            </a>
        </div>
    </form>

    <div class="card profile-card" style="margin-top: 24px;">
        <div class="card-header">
            <h3 style="color: #dc2626;">Danger Zone</h3>
        </div>
        <div class="card-body">
            <p class="field-hint">Deleting your account is permanent and cannot be undone.</p>

            <form id="deleteAccountForm">
                <?php echo csrf_field(); ?>

                <?php if (!empty($user['password_hash'])): ?>
                    <div class="form-group">
                        <label for="delete_password">Confirm Your Password</label>
                        <?php html_password('delete_password', 'placeholder="Enter your password to confirm" required'); ?>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="delete_password">Type Your Email Address to Confirm</label>
                        <?php html_text('delete_password', 'placeholder="' . encode($user['email']) . '" required autocomplete="off"'); ?>
                        <span class="field-hint">
                            Your account uses Google Sign-In and has no password set.
                            Type <strong><?php echo encode($user['email']); ?></strong> to confirm deletion.
                        </span>
                    </div>
                <?php endif; ?>

                <?php html_button('submit', 'Delete My Account', 'class="btn-danger btn-block" style="width:100%;"'); ?>
            </form>
        </div>
    </div>
</div>

<?php include_foot(); ?>