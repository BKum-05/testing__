<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/_auth.php';

if (empty($_SESSION['pending_user'])) {
    redirect('register.php');
}

$pendingUser = $_SESSION['pending_user'];

include_head("Verify OTP - Online Shopping System");
?>

<div class="container">
    <h2>Enter Verification Code</h2>
    <p class="subtitle">
        We sent a 6-digit verification code to
        <strong><?= htmlspecialchars($pendingUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>.
    </p>

    <div id="otp-alert" class="alert-box alert-danger" style="display: none;"></div>

    <form method="POST" action="process_verify_otp.php" id="verify-otp-form">
        <?php
        echo csrf_field();        
        ?>
        <input type="hidden" name="otp" id="otp-full" value="">

        <div class="form-group">
            <label>Verification Code *</label>
            <div class="otp-container" id="otp-inputs">
                <?php
                html_text('otp_1', "pattern='[0-9]*' inputmode='numeric' autocomplete='one-time-code' class='otp-box' required autofocus");
                html_text('otp_2', "pattern='[0-9]*' inputmode='numeric' class='otp-box' required");
                html_text('otp_3', "pattern='[0-9]*' inputmode='numeric' class='otp-box' required");
                html_text('otp_4', "pattern='[0-9]*' inputmode='numeric' class='otp-box' required");
                html_text('otp_5', "pattern='[0-9]*' inputmode='numeric' class='otp-box' required");
                html_text('otp_6', "pattern='[0-9]*' inputmode='numeric' class='otp-box' required");
                ?>
            </div>
            <?php if (function_exists('err')) err('otp'); ?>
        </div>

        <div style="display: flex; justify-content: center; margin-top: 24px;">
            <?php html_button('submit', 'Verify Code', 'class="btn btn-primary" id="btn-verify"') ?>
        </div>
    </form>

    <div class="form-options" style="justify-content: center; margin-top: 20px;">
        <span class="field-hint">
            Didn't receive code?
            <button type="button" id="resend-link" class="forgot-password-link" style="background:none;border:none;padding:0;cursor:pointer;">Resend OTP</button>
            <span id="resend-cooldown" style="display:none; font-weight: 600; font-size: 0.95rem; color: var(--primary-color, #2563eb); margin-left: 6px;"></span>
        </span>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('otp-inputs');
        const hiddenOtp = document.getElementById('otp-full');
        const form = document.getElementById('verify-otp-form');
        if (!container || !hiddenOtp || !form) return;

        const boxes = container.querySelectorAll('.otp-box');
        if (!boxes.length) return;

        function syncOtp() {
            hiddenOtp.value = Array.from(boxes).map(b => b.value).join('');
        }

        function fillOtp(code) {
            const digits = code.replace(/[^0-9]/g, '').slice(0, 6).split('');
            boxes.forEach((box, i) => {
                box.value = digits[i] || '';
            });
            const focusIndex = Math.min(digits.length, boxes.length - 1);
            boxes[focusIndex].focus();
            syncOtp();
        }

        boxes.forEach((box, index) => {
            box.addEventListener('input', (e) => {
                const rawVal = e.target.value.replace(/[^0-9]/g, '');

                if (rawVal.length > 1) {
                    fillOtp(rawVal);
                    return;
                }

                box.value = rawVal;

                if (rawVal && index < boxes.length - 1) {
                    boxes[index + 1].focus();
                }
                syncOtp();
            });

            box.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !box.value && index > 0) {
                    boxes[index - 1].focus();
                }
            });

            box.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = (e.clipboardData || window.clipboardData).getData('text');
                if (pasteData) {
                    fillOtp(pasteData);
                }
            });
        });

        form.addEventListener('submit', (e) => {
            syncOtp();
            if (hiddenOtp.value.length !== 6) {
                e.preventDefault();
                const alertBox = document.getElementById('otp-alert');
                if (alertBox) {
                    alertBox.textContent = 'Please enter all 6 digits of your verification code.';
                    alertBox.style.display = 'block';
                }
            }
        });
    });
</script>

<?php include_foot(); ?>
