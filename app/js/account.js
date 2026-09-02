(function () {
    function appUrl(path) {
        var normalized = String(path || '').replace(/^\/+/, '');
        var base = (window.BASE_URL || '').replace(/\/+$/, '');
        return (base ? (base + '/') : '/') + normalized;
    }

    function safeResetTurnstile() {
        if (typeof window.turnstile !== 'undefined' && window.turnstile && typeof window.turnstile.reset === 'function') {
            try {
                window.turnstile.reset();
            } catch (e) {
                console.warn('Turnstile reset warning:', e);
            }
        }
    }

    function handleGoogleSignIn(response) {
        if (!response || !response.credential) {
            if (typeof window.showAlert === 'function') {
                window.showAlert('Google Sign-In failed to return credentials.');
            }
            return;
        }

        $.ajax({
            url: appUrl('login/process_google_auth.php'),
            type: 'POST',
            data: {
                token: response.credential,
                csrf_token: $('input[name="csrf_token"]').first().val() || ''
            },
            dataType: 'json'
        })
        .done(function (res) {
            if (res && res.success) {
                window.location.href = res.redirect_url || appUrl('member/profile.php');
            } else if (typeof window.showAlert === 'function') {
                window.showAlert('Google Sign-In failed: ' + ((res && res.message) ? res.message : 'Unknown error'));
            }
        })
        .fail(function (xhr) {
            var res = xhr.responseJSON;
            if (typeof window.showAlert === 'function') {
                window.showAlert(res && res.message ? res.message : 'Google sign-in server request failed.');
            }
        });
    }

    window.handleGoogleSignIn = handleGoogleSignIn;

    $(function () {
        $(document).on('click', '.toggle-password, #togglePassword', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var $input = $btn.siblings('input');
            if (!$input.length) {
                $input = $btn.parent().find('input');
            }
            if (!$input.length) return;

            var isPassword = $input.attr('type') === 'password';
            $input.attr('type', isPassword ? 'text' : 'password');

            var $icon = $btn.find('i');
            if ($icon.length) {
                $icon.toggleClass('fa-eye fa-eye-slash');
            }
        });

        var $rememberInfo = $('.remember-info');
        if ($rememberInfo.length) {
            $rememberInfo.on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).toggleClass('show');
            });

            $(document).on('click', function () {
                $('.remember-info').removeClass('show');
            });
        }

        var $loginForm = $('#loginForm');
        if ($loginForm.length) {
            var $loginBtn = $loginForm.find('button[type="submit"]');
            var $loginEmail = $('#email');
            var loginLockTimer = null;
            var LOCKOUT_STORAGE_KEY = 'login_lockout';

            function startLoginLockout(seconds, email) {
                clearInterval(loginLockTimer);
                var remaining = seconds;

                if (email) {
                    try {
                        localStorage.setItem(LOCKOUT_STORAGE_KEY, JSON.stringify({
                            email: email.toLowerCase(),
                            lockoutUntil: Date.now() + (seconds * 1000)
                        }));
                    } catch (e) {}
                }

                function tick() {
                    if (remaining <= 0) {
                        clearInterval(loginLockTimer);
                        $loginBtn.prop('disabled', false).text('Sign In');
                        try { localStorage.removeItem(LOCKOUT_STORAGE_KEY); } catch (e) {}
                        return;
                    }
                    var mins = Math.floor(remaining / 60);
                    var secs = remaining % 60;
                    var label = mins > 0 ? (mins + 'm ' + secs + 's') : (secs + 's');
                    $loginBtn.prop('disabled', true).text('Locked (' + label + ')');
                    remaining -= 1;
                }

                tick();
                loginLockTimer = setInterval(tick, 1000);
            }

            function checkStoredLockout() {
                var raw = null;
                try { raw = localStorage.getItem(LOCKOUT_STORAGE_KEY); } catch (e) {}
                if (!raw) return;

                try {
                    var stored = JSON.parse(raw);
                    var currentEmail = ($loginEmail.val() || '').toLowerCase();
                    var remainingMs = stored.lockoutUntil - Date.now();

                    if (remainingMs <= 0) {
                        localStorage.removeItem(LOCKOUT_STORAGE_KEY);
                        return;
                    }

                    if (currentEmail === stored.email) {
                        startLoginLockout(Math.ceil(remainingMs / 1000));
                    }
                } catch (err) {
                    try { localStorage.removeItem(LOCKOUT_STORAGE_KEY); } catch (e) {}
                }
            }

            checkStoredLockout();
            $loginEmail.on('input blur', checkStoredLockout);

            $loginForm.on('submit', function (e) {
                e.preventDefault();
                $('#alertBox').slideUp();

                var turnstileToken = $('[name="cf-turnstile-response"]').val();
                if ($('.cf-turnstile').length && !turnstileToken) {
                    if (typeof window.showAlert === 'function') {
                        window.showAlert('Please complete the security check.');
                    }
                    return;
                }

                var enteredEmail = $loginEmail.val();
                var originalText = $loginBtn.text();
                $loginBtn.prop('disabled', true).text('Signing in…');

                $.ajax({
                    url: appUrl('login/process_login.php'),
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json'
                })
                .done(function (res) {
                    if (res && res.password_reset_required) {
                        window.location.href = res.redirect_url || appUrl('login/set_password.php');
                        return;
                    }
                    if (res && res.success) {
                        try { localStorage.removeItem(LOCKOUT_STORAGE_KEY); } catch (e) {}
                        window.location.href = res.redirect_url || appUrl('member/profile.php');
                        return;
                    }

                    if (typeof window.showAlert === 'function') {
                        window.showAlert((res && res.message) ? res.message : 'Login failed.');
                    }
                    safeResetTurnstile();

                    if (res && res.locked && res.seconds_remaining) {
                        startLoginLockout(res.seconds_remaining, enteredEmail);
                    } else {
                        $loginBtn.prop('disabled', false).text(originalText);
                    }
                })
                .fail(function (xhr) {
                    var res = xhr.responseJSON;
                    if (typeof window.showAlert === 'function') {
                        window.showAlert(res && res.message ? res.message : 'Server error occurred during login.');
                    }
                    safeResetTurnstile();

                    if (res && res.locked && res.seconds_remaining) {
                        startLoginLockout(res.seconds_remaining, enteredEmail);
                    } else {
                        $loginBtn.prop('disabled', false).text(originalText);
                    }
                });
            });
        }

        var $regForm = $('#regForm');
        if ($regForm.length) {
            $regForm.on('submit', function (e) {
                e.preventDefault();
                $('#alertBox').slideUp();

                var turnstileToken = $('[name="cf-turnstile-response"]').val();
                if ($('.cf-turnstile').length && !turnstileToken) {
                    if (typeof window.showAlert === 'function') {
                        window.showAlert('Please complete the security check.');
                    }
                    return;
                }

                var $btn = $(this).find('button[type="submit"]');
                var originalText = $btn.text();
                $btn.prop('disabled', true).text('Creating account…');

                $.ajax({
                    url: appUrl('login/process_register.php'),
                    type: 'POST',
                    data: $(this).serialize() + '&ajax=1',
                    dataType: 'json'
                })
                .done(function (res) {
                    if (res && res.success) {
                        if (typeof window.showAlert === 'function') {
                            window.showAlert(res.message || 'Verification code sent! Redirecting...', 'success');
                        }
                        setTimeout(function () {
                            window.location.href = res.redirect || appUrl('login/verify_otp.php');
                        }, 1000);
                        return;
                    }

                    if (typeof window.showAlert === 'function') {
                        window.showAlert((res && res.message) ? res.message : 'Please check your inputs.');
                    }
                    safeResetTurnstile();
                    $btn.prop('disabled', false).text(originalText);
                })
                .fail(function (xhr) {
                    var res = xhr.responseJSON;
                    if (typeof window.showAlert === 'function') {
                        window.showAlert(res && res.message ? res.message : 'Server communication error.');
                    }
                    safeResetTurnstile();
                    $btn.prop('disabled', false).text(originalText);
                });
            });
        }

        var $profileForm = $('#profileForm');
        if ($profileForm.length) {
            var MAX_AVATAR_BYTES = 2 * 1024 * 1024;

            $('#avatar').on('change', function (e) {
                var file = e.target.files && e.target.files[0];
                var $img = $('#avatarPreview');
                if (!$img.length) return;

                var img = $img[0];
                if (!img.dataset.src) {
                    img.dataset.src = img.src;
                }

                if (!file) return;

                if (!file.type || !file.type.startsWith('image/')) {
                    if (typeof window.showAlert === 'function') {
                        window.showAlert('Please select a valid image file (JPG, PNG, WEBP, or GIF).');
                    }
                    img.src = img.dataset.src;
                    e.target.value = '';
                    return;
                }

                if (file.size > MAX_AVATAR_BYTES) {
                    if (typeof window.showAlert === 'function') {
                        window.showAlert('Image exceeds the 2MB limit. Please choose a smaller file.');
                    }
                    img.src = img.dataset.src;
                    e.target.value = '';
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (ev) {
                    img.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            });

            $profileForm.on('submit', function (e) {
                e.preventDefault();
                $('#alertBox').slideUp();

                var $btn = $('#btn-profile-submit');
                var originalText = $btn.text();
                $btn.prop('disabled', true).text('Saving…');

                var formData = new FormData(this);

                $.ajax({
                    url: appUrl('member/process_profile.php'),
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json'
                })
                .done(function (res) {
                    if (res && res.success) {
                        if (typeof window.showAlert === 'function') {
                            window.showAlert(res.message || 'Profile updated successfully!', 'success');
                        }
                        if (res.avatar_url) $('#avatarPreview').attr('src', res.avatar_url);
                        $('#current_password, #new_password, #confirm_password').val('');
                        safeResetTurnstile();
                        return;
                    }
                    if (typeof window.showAlert === 'function') {
                        window.showAlert('Update failed: ' + ((res && res.message) ? res.message : 'Please check your inputs.'));
                    }
                    safeResetTurnstile();
                })
                .fail(function (xhr) {
                    var res = xhr.responseJSON;
                    if (typeof window.showAlert === 'function') {
                        window.showAlert(res && res.message ? res.message : 'Server error occurred during profile save.');
                    }
                    safeResetTurnstile();
                })
                .always(function () {
                    $btn.prop('disabled', false).text(originalText);
                });
            });
        }

        var $deleteAccountForm = $('#deleteAccountForm');
        if ($deleteAccountForm.length) {
            $deleteAccountForm.on('submit', function (e) {
                e.preventDefault();
                if (!confirm('This will permanently delete your account. This action cannot be undone. Continue?')) {
                    return;
                }

                var $btn = $(this).find('button[type="submit"]');
                var originalText = $btn.text();
                $btn.prop('disabled', true).text('Deleting…');

                $.ajax({
                    url: appUrl('member/process_delete_account.php'),
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json'
                })
                .done(function (res) {
                    if (res && res.success) {
                        if (typeof window.showAlert === 'function') {
                            window.showAlert(res.message, 'success');
                        }
                        setTimeout(function () {
                            window.location.href = res.redirect || appUrl('index.php');
                        }, 1000);
                        return;
                    }
                    if (typeof window.showAlert === 'function') {
                        window.showAlert((res && res.message) ? res.message : 'Failed to delete account.');
                    }
                    $btn.prop('disabled', false).text(originalText);
                })
                .fail(function (xhr) {
                    var res = xhr.responseJSON;
                    if (typeof window.showAlert === 'function') {
                        window.showAlert(res && res.message ? res.message : 'Server error occurred.');
                    }
                    $btn.prop('disabled', false).text(originalText);
                });
            });
        }

        var $newPassword = $('#new_password');
        var $confirmPassword = $('#confirm_password');

        if ($newPassword.length || $confirmPassword.length) {
            function checkRequirement($el, isValid) {
                if (!$el.length) return;
                var $icon = $el.find('i');
                if (isValid) {
                    $el.addClass('valid');
                    if ($icon.length) {
                        $icon.removeClass('fa-circle').addClass('fa-check-circle');
                    }
                } else {
                    $el.removeClass('valid');
                    if ($icon.length) {
                        $icon.removeClass('fa-check-circle').addClass('fa-circle');
                    }
                }
            }

            function validatePassword() {
                var pass = $newPassword.val() || '';
                var confirmPass = $confirmPassword.val() || '';

                checkRequirement($('#req-length'), pass.length >= 8);
                checkRequirement($('#req-casing'), /[a-z]/.test(pass) && /[A-Z]/.test(pass));
                checkRequirement($('#req-number'), /\d/.test(pass));
                checkRequirement($('#req-symbol'), /[^a-zA-Z0-9]/.test(pass));

                var isMatch = pass.length > 0 && pass === confirmPass;
                checkRequirement($('#req-match'), isMatch);
            }

            $newPassword.on('input', validatePassword);
            $confirmPassword.on('input', validatePassword);
        }

        var $setPasswordForm = $('#set-password-form');
        if ($setPasswordForm.length) {
            $setPasswordForm.on('submit', function (e) {
                e.preventDefault();
                $('#alertBox').slideUp();

                var $btn = $(this).find('button[type="submit"]');
                var originalText = $btn.text();
                $btn.prop('disabled', true).text('Setting up…');

                $.ajax({
                    url: appUrl('login/process_set_password.php'),
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json'
                })
                .done(function (res) {
                    if (res && res.success) {
                        window.location.href = res.redirect || appUrl('member/profile.php');
                        return;
                    }
                    if (typeof window.showAlert === 'function') {
                        window.showAlert((res && res.message) ? res.message : 'Failed to set password.');
                    }
                    $btn.prop('disabled', false).text(originalText);
                })
                .fail(function (xhr) {
                    var res = xhr.responseJSON;
                    if (typeof window.showAlert === 'function') {
                        window.showAlert(res && res.message ? res.message : 'Server error occurred.');
                    }
                    $btn.prop('disabled', false).text(originalText);
                });
            });
        }

        var $forgotPasswordForm = $('#forgotPasswordForm');
        if ($forgotPasswordForm.length) {
            $forgotPasswordForm.on('submit', function (e) {
                e.preventDefault();
                var $form = $(this);
                var $alert = $('#alertBox');

                $.ajax({
                    url: appUrl('login/process_forgot_password.php'),
                    type: 'POST',
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function (response) {
                        $alert.removeClass('alert-danger').addClass('alert-success').text(response.message).show();
                        if (response.success) {
                            $form[0].reset();
                        }
                    },
                    error: function (xhr) {
                        var res = xhr.responseJSON;
                        var msg = (res && res.message) ? res.message : 'An error occurred. Please try again.';
                        $alert.removeClass('alert-success').addClass('alert-danger').text(msg).show();
                    }
                });
            });
        }

        var $resendBtn = $('#resend-link');
        if ($resendBtn.length) {
            var $cooldownLabel = $('#resend-cooldown');
            var cooldownTimer = null;

            function startCooldown(seconds) {
                var remaining = seconds;
                $resendBtn.prop('disabled', true).hide();
                $cooldownLabel.show();

                clearInterval(cooldownTimer);
                cooldownTimer = setInterval(function () {
                    $cooldownLabel.text('Resend available in ' + remaining + 's');
                    remaining -= 1;

                    if (remaining < 0) {
                        clearInterval(cooldownTimer);
                        $resendBtn.prop('disabled', false).show();
                        $cooldownLabel.hide();
                    }
                }, 1000);
            }

            $resendBtn.on('click', function (e) {
                e.preventDefault();
                var csrfToken = $('input[name="csrf_token"]').val() || '';

                $.ajax({
                    url: appUrl('login/resend_otp.php'),
                    type: 'POST',
                    data: { csrf_token: csrfToken },
                    dataType: 'json'
                })
                .done(function (res) {
                    var alertBox = document.getElementById('otp-alert');
                    if (res && res.success) {
                        if (alertBox) {
                            alertBox.className = 'alert-box alert-success';
                            alertBox.textContent = res.message;
                            alertBox.style.display = 'block';
                        }
                        startCooldown(res.seconds_remaining || 60);
                    } else {
                        if (alertBox) {
                            alertBox.className = 'alert-box alert-danger';
                            alertBox.textContent = (res && res.message) ? res.message : 'Failed to resend OTP.';
                            alertBox.style.display = 'block';
                        }
                        if (res && res.seconds_remaining) {
                            startCooldown(res.seconds_remaining);
                        }
                    }
                })
                .fail(function (xhr) {
                    var res = xhr.responseJSON;
                    var alertBox = document.getElementById('otp-alert');
                    if (alertBox) {
                        alertBox.className = 'alert-box alert-danger';
                        alertBox.textContent = res && res.message ? res.message : 'Failed to resend code.';
                        alertBox.style.display = 'block';
                    }
                });
            });
        }
    });
})();
