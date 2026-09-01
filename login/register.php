<?php
require_once __DIR__ . '/../app/_auth.php';

if (!empty($_SESSION['user_id'])) {
    redirect(get_dashboard_url());
}

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
    'male'              => 'Male',
    'female'            => 'Female',
    'other'             => 'Other',
    'prefer_not_to_say' => 'Prefer not to say',
];

include_head("Register - Online Shopping System");
?>


<div class="container">
    <h2>Create Your Account</h2>
    <p class="subtitle">Enter your details below. We'll send a verification code to confirm your email address.</p>

    <?php if ($msg = temp('success')): ?>
        <div class="alert-box alert-success"><?php echo encode($msg); ?></div>
    <?php endif; ?>

    <div id="alertBox" class="alert-box" style="display:none;" role="status" aria-live="polite"></div>

    <!-- Google OAuth Container -->
    <div class="oauth-box">
        <?php html_google($googleClientId, 'handleGoogleSignIn', false, 'signup_with'); ?>
    </div>

    <div class="divider"><span>OR</span></div>

    <form id="regForm" action="process_register.php" method="POST">
        <?php echo csrf_field(); ?>
        <div class="form-row">
            <div class="form-group">
                <label for="email">Email Address *</label>
                <?php html_email('email', 'placeholder="john.doe@example.com" inputmode="email" required'); ?>
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
                <?php html_phone('phone_number', 'placeholder="0123456789" maxlength="15" inputmode="tel"'); ?>
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

        <?php
        // Cloudflare Turnstile CAPTCHA
        html_cloudflare($cloudflareToken);
        html_button('submit', 'Register', 'class="btn-primary"');
        ?>
    </form>

    <p class="subtitle" style="margin-top: 15px;">Already have an account? <a href="login.php">Login here</a></p>
</div>

<?php include_foot(); ?>