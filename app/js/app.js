/**
 * Account & Identity Management System
 * Client-side script handling forms, password strength, location data, and accessibility.
 */

// Global state for location data
window.locationData = window.locationData || {};

// Helper to safely reset Turnstile captcha
function safeResetTurnstile() {
    if (typeof window.turnstile !== 'undefined' && window.turnstile && typeof window.turnstile.reset === 'function') {
        try {
            window.turnstile.reset();
        } catch (e) {
            console.warn('Turnstile reset warning:', e);
        }
    }
}

// Display alert notifications
function showAlert(message, type) {
    var $alertBox = $('#alertBox');
    if (!$alertBox.length) {
        // Look for alternate alert boxes
        $alertBox = $('#otp-alert');
    }
    if (!$alertBox.length) return;

    $alertBox
        .removeClass('alert-success alert-danger alert-warning alert-info')
        .addClass(type === 'success' ? 'alert-success' : 'alert-danger')
        .text(message)
        .attr('role', 'status')
        .attr('aria-live', 'polite')
        .show();

    if ($alertBox.length && $alertBox[0].scrollIntoView) {
        $alertBox[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    if (type !== 'success') {
        $alertBox.attr('tabindex', '-1').trigger('focus');
    }
}

// Google OAuth Sign-in Handler
function handleGoogleSignIn(response) {
    if (!response || !response.credential) {
        showAlert('Google Sign-In failed to return credentials.');
        return;
    }

    $.ajax({
        url: 'process_google_auth.php',
        type: 'POST',
        data: {
            token: response.credential,
            csrf_token: $('input[name="csrf_token"]').first().val() || ''
        },
        dataType: 'json'
    })
    .done(function (res) {
        if (res && res.success) {
            window.location.href = res.redirect_url || (window.BASE_URL ? window.BASE_URL + '/member/profile.php' : '../member/profile.php');
        } else {
            showAlert('Google Sign-In failed: ' + ((res && res.message) ? res.message : 'Unknown error'));
        }
    })
    .fail(function (xhr) {
        var res = xhr.responseJSON;
        showAlert(res && res.message ? res.message : 'Google sign-in server request failed.');
    });
}

// Normalize Malaysia location dataset
function normalizeLocationData(response) {
    var normalized = {};
    if (!response || !Array.isArray(response.state)) {
        return normalized;
    }

    $.each(response.state, function (_, stateEntry) {
        if (!stateEntry || !stateEntry.name || !Array.isArray(stateEntry.city)) {
            return;
        }

        normalized[stateEntry.name] = {};

        $.each(stateEntry.city, function (_, cityEntry) {
            if (!cityEntry || !cityEntry.name || !Array.isArray(cityEntry.postcode)) {
                return;
            }
            normalized[stateEntry.name][cityEntry.name] = cityEntry.postcode;
        });
    });

    return normalized;
}

// ============================================================================
// DOM Ready Execution
// ============================================================================
$(function () {
    // ------------------------------------------------------------------------
    // 1. Password Visibility Toggle
    // ------------------------------------------------------------------------
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

    // ------------------------------------------------------------------------
    // 2. Remember Me Tooltip
    // ------------------------------------------------------------------------
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

    // ------------------------------------------------------------------------
    // 3. Location Cascading (State -> City -> Postcode)
    // ------------------------------------------------------------------------
    var $state = $('#state');
    var $city = $('#city');
    var $postcode = $('#postcode');

    if ($state.length && $city.length && $postcode.length) {
        var initialState = $state.data('current-state') || $state.attr('data-current-state') || $state.val() || '';
        var initialCity = $city.data('current-city') || $city.attr('data-current-city') || $city.val() || '';
        var initialPostcode = $postcode.data('current-postcode') || $postcode.attr('data-current-postcode') || $postcode.val() || '';

        function updateDatalist($input, optionsArray, placeholder) {
            var listId = $input.attr('list');
            if (!listId) return;
            var $datalist = $('#' + listId);

            if ($datalist.length) {
                $datalist.empty();
                $.each(optionsArray, function (_, value) {
                    $datalist.append($('<option>', { value: value }));
                });
            }

            if (placeholder) {
                $input.attr('placeholder', placeholder);
            }
        }

        function populateStates() {
            var stateNames = Object.keys(window.locationData);
            updateDatalist($state, stateNames, 'Select State');

            if (initialState) {
                $state.val(initialState);
                if (window.locationData[initialState]) {
                    populateCities(true);
                }
            }
        }

        function populateCities(isInitialLoad) {
            var selectedState = $state.val();
            var selectedCity = isInitialLoad ? initialCity : '';

            updateDatalist($city, [], 'Select City');
            updateDatalist($postcode, [], 'Select Postcode');

            if (!isInitialLoad) {
                $city.val('');
                $postcode.val('');
            }

            if (!selectedState || !window.locationData[selectedState]) {
                $city.prop('disabled', true);
                $postcode.prop('disabled', true);
                return;
            }

            var cityNames = Object.keys(window.locationData[selectedState]);
            updateDatalist($city, cityNames, 'Select City');
            $city.prop('disabled', false);

            if (selectedCity && window.locationData[selectedState][selectedCity]) {
                $city.val(selectedCity);
                populatePostcodes(isInitialLoad);
            }
        }

        function populatePostcodes(isInitialLoad) {
            var selectedState = $state.val();
            var selectedCity = $city.val();
            var selectedPostcode = isInitialLoad ? initialPostcode : '';

            updateDatalist($postcode, [], 'Select Postcode');
            if (!isInitialLoad) {
                $postcode.val('');
            }

            if (!selectedState || !selectedCity || !window.locationData[selectedState] || !window.locationData[selectedState][selectedCity]) {
                $postcode.prop('disabled', true);
                return;
            }

            var postcodes = window.locationData[selectedState][selectedCity];
            updateDatalist($postcode, postcodes, 'Select Postcode');
            $postcode.prop('disabled', false);

            if (selectedPostcode) {
                $postcode.val(selectedPostcode);
            }
        }

        function loadLocationData() {
            var baseUrl = (window.BASE_URL || '').replace(/\/+$/, '');
            var jsonUrl = baseUrl ? (baseUrl + '/malaysia.json') : 'malaysia.json';

            $.ajax({
                url: jsonUrl,
                type: 'GET',
                dataType: 'json'
            })
            .done(function (response) {
                window.locationData = normalizeLocationData(response);
                populateStates();
            })
            .fail(function () {
                // Fallback to root relative
                $.getJSON('malaysia.json', function (response) {
                    window.locationData = normalizeLocationData(response);
                    populateStates();
                });
            });
        }

        $state.on('input change', function () {
            initialCity = '';
            initialPostcode = '';
            populateCities(false);
        });

        $city.on('input change', function () {
            initialPostcode = '';
            populatePostcodes(false);
        });

        loadLocationData();
    }

    // ------------------------------------------------------------------------
    // 4. Numeric Sanitize Inputs (Phone & Postcode)
    // ------------------------------------------------------------------------
    var $phoneNumber = $('#phone_number');
    if ($phoneNumber.length) {
        $phoneNumber.on('input', function () {
            this.value = this.value.replace(/\D/g, '');
        });
    }

    if ($postcode.length) {
        $postcode.on('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 5);
        });
    }

    // ------------------------------------------------------------------------
    // 5. Login Form Processing & Client Lockout Handling
    // ------------------------------------------------------------------------
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
            // If Turnstile widget exists on page, verify token
            if ($('.cf-turnstile').length && !turnstileToken) {
                showAlert('Please complete the security check.');
                return;
            }

            var enteredEmail = $loginEmail.val();
            var originalText = $loginBtn.text();
            $loginBtn.prop('disabled', true).text('Signing in…');

            $.ajax({
                url: 'process_login.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json'
            })
            .done(function (res) {
                if (res && res.password_reset_required) {
                    window.location.href = res.redirect_url || 'set_password.php';
                    return;
                }
                if (res && res.success) {
                    try { localStorage.removeItem(LOCKOUT_STORAGE_KEY); } catch (e) {}
                    window.location.href = res.redirect_url || (window.BASE_URL ? window.BASE_URL + '/member/profile.php' : '../member/profile.php');
                    return;
                }

                showAlert((res && res.message) ? res.message : 'Login failed.');
                safeResetTurnstile();

                if (res && res.locked && res.seconds_remaining) {
                    startLoginLockout(res.seconds_remaining, enteredEmail);
                } else {
                    $loginBtn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON;
                showAlert(res && res.message ? res.message : 'Server error occurred during login.');
                safeResetTurnstile();

                if (res && res.locked && res.seconds_remaining) {
                    startLoginLockout(res.seconds_remaining, enteredEmail);
                } else {
                    $loginBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    // ------------------------------------------------------------------------
    // 6. Registration Form Processing
    // ------------------------------------------------------------------------
    var $regForm = $('#regForm');
    if ($regForm.length) {
        $regForm.on('submit', function (e) {
            e.preventDefault();
            $('#alertBox').slideUp();

            var turnstileToken = $('[name="cf-turnstile-response"]').val();
            if ($('.cf-turnstile').length && !turnstileToken) {
                showAlert('Please complete the security check.');
                return;
            }

            var $btn = $(this).find('button[type="submit"]');
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Creating account…');

            $.ajax({
                url: 'process_register.php',
                type: 'POST',
                data: $(this).serialize() + '&ajax=1',
                dataType: 'json'
            })
            .done(function (res) {
                if (res && res.success) {
                    showAlert(res.message || 'Verification code sent! Redirecting...', 'success');
                    setTimeout(function () {
                        window.location.href = res.redirect || 'verify_otp.php';
                    }, 1000);
                    return;
                }

                showAlert((res && res.message) ? res.message : 'Please check your inputs.');
                safeResetTurnstile();
                $btn.prop('disabled', false).text(originalText);
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON;
                showAlert(res && res.message ? res.message : 'Server communication error.');
                safeResetTurnstile();
                $btn.prop('disabled', false).text(originalText);
            });
        });
    }

    // ------------------------------------------------------------------------
    // 7. Profile Edit Form & Avatar Preview
    // ------------------------------------------------------------------------
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
                showAlert('Please select a valid image file (JPG, PNG, WEBP, or GIF).');
                img.src = img.dataset.src;
                e.target.value = '';
                return;
            }

            if (file.size > MAX_AVATAR_BYTES) {
                showAlert('Image exceeds the 2MB limit. Please choose a smaller file.');
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
                url: 'process_profile.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json'
            })
            .done(function (res) {
                if (res && res.success) {
                    showAlert(res.message || 'Profile updated successfully!', 'success');
                    if (res.avatar_url) $('#avatarPreview').attr('src', res.avatar_url);
                    $('#current_password, #new_password, #confirm_password').val('');
                    safeResetTurnstile();
                    return;
                }
                showAlert('Update failed: ' + ((res && res.message) ? res.message : 'Please check your inputs.'));
                safeResetTurnstile();
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON;
                showAlert(res && res.message ? res.message : 'Server error occurred during profile save.');
                safeResetTurnstile();
            })
            .always(function () {
                $btn.prop('disabled', false).text(originalText);
            });
        });
    }

    // ------------------------------------------------------------------------
    // 8. Delete Account Form
    // ------------------------------------------------------------------------
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
                url: 'process_delete_account.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json'
            })
            .done(function (res) {
                if (res && res.success) {
                    showAlert(res.message, 'success');
                    setTimeout(function () {
                        window.location.href = res.redirect || 'index.php';
                    }, 1000);
                    return;
                }
                showAlert((res && res.message) ? res.message : 'Failed to delete account.');
                $btn.prop('disabled', false).text(originalText);
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON;
                showAlert(res && res.message ? res.message : 'Server error occurred.');
                $btn.prop('disabled', false).text(originalText);
            });
        });
    }

    // ------------------------------------------------------------------------
    // 9. Admin Member Creation Form
    // ------------------------------------------------------------------------
    var $memberCreateForm = $('#memberCreateForm');
    if ($memberCreateForm.length) {
        $memberCreateForm.on('submit', function (e) {
            e.preventDefault();
            var $btn = $('#btn-member-create');
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Creating…');

            $.ajax({
                url: 'process_member_create.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json'
            })
            .done(function (res) {
                if (res && res.success) {
                    showAlert(res.message, 'success');
                    setTimeout(function () {
                        window.location.href = res.redirect || 'member_list.php';
                    }, 800);
                    return;
                }
                showAlert((res && res.message) ? res.message : 'Failed to create account.');
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON;
                showAlert(res && res.message ? res.message : 'Server error occurred.');
            })
            .always(function () {
                $btn.prop('disabled', false).text(originalText);
            });
        });
    }

    // ------------------------------------------------------------------------
    // 10. Password Strength & Live Rules Indicator
    // ------------------------------------------------------------------------
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

    // ------------------------------------------------------------------------
    // 11. Set Password Form (New Member Onboarding)
    // ------------------------------------------------------------------------
    var $setPasswordForm = $('#set-password-form');
    if ($setPasswordForm.length) {
        $setPasswordForm.on('submit', function (e) {
            e.preventDefault();
            $('#alertBox').slideUp();

            var $btn = $(this).find('button[type="submit"]');
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Setting up…');

            $.ajax({
                url: 'process_set_password.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json'
            })
            .done(function (res) {
                if (res && res.success) {
                    window.location.href = res.redirect || (window.BASE_URL ? window.BASE_URL + '/member/profile.php' : '../member/profile.php');
                    return;
                }
                showAlert((res && res.message) ? res.message : 'Failed to set password.');
                $btn.prop('disabled', false).text(originalText);
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON;
                showAlert(res && res.message ? res.message : 'Server error occurred.');
                $btn.prop('disabled', false).text(originalText);
            });
        });
    }

    // ------------------------------------------------------------------------
    // 12. Forgot Password Request
    // ------------------------------------------------------------------------
    var $forgotPasswordForm = $('#forgotPasswordForm');
    if ($forgotPasswordForm.length) {
        $forgotPasswordForm.on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $alert = $('#alertBox');

            $.ajax({
                url: 'process_forgot_password.php',
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

    // ------------------------------------------------------------------------
    // 13. Resend OTP with Cooldown
    // ------------------------------------------------------------------------
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
                url: 'resend_otp.php',
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

    // ------------------------------------------------------------------------
    // 14. Admin Member Bulk Actions & Checkbox Handlers
    // ------------------------------------------------------------------------
    if ($('#selectAllMembers').length) {
        var $bulkBar = $('#bulkActionsBar');

        function updateBulkState() {
            var count = $('.member-checkbox:checked').length;
            $('#bulkSelectedCount').text(count + ' selected');

            if (count > 0) {
                $bulkBar.slideDown(150);
            } else {
                $bulkBar.slideUp(150);
            }

            var $selectable = $('.member-checkbox:not(:disabled)');
            var $selectAll = $('#selectAllMembers');
            if (count === 0) {
                $selectAll.prop('checked', false).prop('indeterminate', false);
            } else if (count === $selectable.length) {
                $selectAll.prop('checked', true).prop('indeterminate', false);
            } else {
                $selectAll.prop('checked', false).prop('indeterminate', true);
            }
        }

        $('#selectAllMembers').on('change', function () {
            $('.member-checkbox:not(:disabled)').prop('checked', $(this).is(':checked'));
            updateBulkState();
        });

        $(document).on('change', '.member-checkbox', updateBulkState);

        $('#applyBulkAction').on('click', function () {
            var action = $('input[name="bulk_action"]:checked').val();
            var ids = $('.member-checkbox:checked').map(function () { return $(this).val(); }).get();

            if (!action) { showAlert('Please choose an action.'); return; }
            if (ids.length === 0) { showAlert('Please select at least one member.'); return; }

            var endpoint, statusValue;
            if (action === 'delete') {
                endpoint = 'process_member_delete.php';
                if (!confirm('Permanently delete ' + ids.length + ' account(s)? This cannot be undone.')) return;
            } else {
                endpoint = 'process_member_status.php';
                statusValue = action === 'activate' ? 'active' : 'suspended';
                if (!confirm('Set ' + ids.length + ' account(s) to "' + statusValue + '"?')) return;
            }

            var payload = { csrf_token: $('input[name="csrf_token"]').first().val(), member_ids: ids };
            if (statusValue) payload.status = statusValue;

            $.ajax({ url: endpoint, type: 'POST', data: payload, dataType: 'json' })
                .done(function (res) {
                    showAlert(res.message, res.success ? 'success' : undefined);
                    if (res.success) setTimeout(function () { location.reload(); }, 800);
                })
                .fail(function () { showAlert('Server error.'); });
        });
    }

    // ------------------------------------------------------------------------
    // 15. Accessibility Toolbar Widget
    // ------------------------------------------------------------------------
    var $container = $('#a11y-container');
    if ($container.length) {
        var $triggerBtn = $('#a11y-trigger-btn');
        var $menu = $('#a11y-menu');
        var $body = $('body');
        var $appContent = $('#app-content');
        var STORAGE_KEY = 'oss_a11y_settings';

        var fontStep = 0;
        var MAX_FONT_STEP = 3;
        var MIN_FONT_STEP = -2;

        var isDragging = false;
        var startX = 0, startY = 0;
        var initialLeft = 0, initialTop = 0;
        var hasMoved = false;

        var savedSide = 'left';
        var savedTop = null;
        try {
            savedSide = localStorage.getItem('oss_a11y_side') || 'left';
            savedTop = localStorage.getItem('oss_a11y_top_pos');
        } catch (e) {}

        applySidePosition(savedSide);
        if (savedTop !== null) {
            $container.css('top', savedTop + 'px');
        }

        function applySidePosition(side) {
            $container.removeClass('a11y-pos-left a11y-pos-right').addClass('a11y-pos-' + side);
            if (side === 'left') {
                $container.css({ left: '0px', right: 'auto' });
            } else {
                $container.css({ left: 'auto', right: '0px' });
            }
        }

        $triggerBtn.on('mousedown touchstart', function (e) {
            isDragging = true;
            hasMoved = false;

            var pageX = e.type === 'touchstart' ? e.originalEvent.touches[0].pageX : e.pageX;
            var pageY = e.type === 'touchstart' ? e.originalEvent.touches[0].pageY : e.pageY;

            startX = pageX;
            startY = pageY;

            var containerOffset = $container.offset();
            if (containerOffset) {
                initialLeft = containerOffset.left;
                initialTop = containerOffset.top - $(window).scrollTop();
                $container.css({
                    left: initialLeft + 'px',
                    right: 'auto',
                    top: initialTop + 'px'
                });
            }

            $triggerBtn.css('cursor', 'grabbing');
        });

        $(document).on('mousemove touchmove', function (e) {
            if (!isDragging) return;

            var pageX = e.type === 'touchmove' ? e.originalEvent.touches[0].pageX : e.pageX;
            var pageY = e.type === 'touchmove' ? e.originalEvent.touches[0].pageY : e.pageY;

            var deltaX = pageX - startX;
            var deltaY = pageY - startY;

            if (Math.abs(deltaX) > 5 || Math.abs(deltaY) > 5) {
                hasMoved = true;
            }

            var newLeft = initialLeft + deltaX;
            var newTop = initialTop + deltaY;

            var windowWidth = $(window).width() || 1000;
            var windowHeight = $(window).height() || 800;
            var btnWidth = $triggerBtn.outerWidth() || 48;
            var btnHeight = $triggerBtn.outerHeight() || 48;

            if (newLeft < 0) newLeft = 0;
            if (newLeft > windowWidth - btnWidth) newLeft = windowWidth - btnWidth;

            if (newTop < 10) newTop = 10;
            if (newTop > windowHeight - btnHeight - 10) newTop = windowHeight - btnHeight - 10;

            $container.css({
                left: newLeft + 'px',
                top: newTop + 'px'
            });
        });

        $(document).on('mouseup touchend', function () {
            if (!isDragging) return;
            isDragging = false;
            $triggerBtn.css('cursor', 'pointer');

            if (hasMoved) {
                var windowWidth = $(window).width() || 1000;
                var currentLeft = parseInt($container.css('left'), 10) || 0;
                var btnWidth = $triggerBtn.outerWidth() || 48;
                var centerX = currentLeft + (btnWidth / 2);

                var targetSide = (centerX < windowWidth / 2) ? 'left' : 'right';
                applySidePosition(targetSide);

                try {
                    localStorage.setItem('oss_a11y_side', targetSide);
                    localStorage.setItem('oss_a11y_top_pos', parseInt($container.css('top'), 10));
                } catch (e) {}
            }
        });

        $triggerBtn.on('click', function (e) {
            e.preventDefault();
            if (hasMoved) {
                hasMoved = false;
                return;
            }
            var isOpen = $menu.toggleClass('a11y-menu-open').hasClass('a11y-menu-open');
            $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#a11y-container').length) {
                $menu.removeClass('a11y-menu-open');
                $triggerBtn.attr('aria-expanded', 'false');
            }
        });

        function adjustFontSize(direction) {
            var nextStep = fontStep + direction;
            if (nextStep > MAX_FONT_STEP || nextStep < MIN_FONT_STEP) return;

            for (var i = MIN_FONT_STEP; i <= MAX_FONT_STEP; i++) {
                if (i !== 0) {
                    $('html, body').removeClass('app-font-step-' + i);
                }
            }

            fontStep = nextStep;
            if (fontStep !== 0) {
                $('html, body').addClass('app-font-step-' + fontStep);
            }
        }

        function toggleColorSchema(targetClass) {
            var FILTER_CLASSES = ['app-grayscale', 'app-negative-contrast'];
            var schemaClasses = ['app-grayscale', 'app-high-contrast', 'app-negative-contrast', 'app-light-bg'];

            schemaClasses.forEach(function (cls) {
                if (cls !== targetClass) {
                    $body.removeClass(cls);
                    $appContent.removeClass(cls);
                }
            });

            var $target = FILTER_CLASSES.indexOf(targetClass) !== -1 ? $appContent : $body;
            $target.toggleClass(targetClass);
        }

        function resetSettings() {
            var classesToRemove = Array.from($body[0].classList)
                .concat(Array.from($('html')[0].classList))
                .concat(Array.from($appContent[0].classList))
                .filter(function (c) { return c.startsWith('app-'); });

            classesToRemove.forEach(function (c) {
                $('html, body').removeClass(c);
                $appContent.removeClass(c);
            });

            fontStep = 0;
            try {
                localStorage.removeItem(STORAGE_KEY);
                localStorage.removeItem('oss_a11y_top_pos');
                localStorage.removeItem('oss_a11y_side');
            } catch (e) {}

            applySidePosition('left');
            $container.css('top', '140px');
            syncUIState();
        }

        function saveSettings() {
            try {
                var activeClasses = Array.from($body[0].classList)
                    .concat(Array.from($appContent[0].classList))
                    .filter(function (c) { return c.startsWith('app-'); });

                var settings = {
                    classes: activeClasses,
                    fontStep: fontStep
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
            } catch (e) {}
        }

        function loadSettings() {
            try {
                var stored = localStorage.getItem(STORAGE_KEY);
                if (!stored) return;

                var settings = JSON.parse(stored);
                if (Array.isArray(settings.classes)) {
                    settings.classes.forEach(function (c) {
                        if (c === 'app-grayscale' || c === 'app-negative-contrast') {
                            $appContent.addClass(c);
                        } else {
                            $('html, body').addClass(c);
                        }
                    });
                }
                if (typeof settings.fontStep === 'number') {
                    fontStep = settings.fontStep;
                    if (fontStep !== 0) {
                        $('html, body').addClass('app-font-step-' + fontStep);
                    }
                }
                syncUIState();
            } catch (err) {}
        }

        function syncUIState() {
            $('.a11y-btn').each(function () {
                var action = $(this).data('a11y-action');
                var active = false;

                if (action === 'grayscale' && $appContent.hasClass('app-grayscale')) active = true;
                if (action === 'high-contrast' && $body.hasClass('app-high-contrast')) active = true;
                if (action === 'negative-contrast' && $appContent.hasClass('app-negative-contrast')) active = true;
                if (action === 'light-bg' && $body.hasClass('app-light-bg')) active = true;
                if (action === 'underline-links' && $body.hasClass('app-underline-links')) active = true;
                if (action === 'readable-font' && $body.hasClass('app-readable-font')) active = true;

                $(this).toggleClass('is-active', active);
            });
        }

        $container.on('click', '.a11y-btn', function (e) {
            e.preventDefault();
            var action = $(this).data('a11y-action');

            switch (action) {
                case 'font-increase':
                    adjustFontSize(1);
                    break;
                case 'font-decrease':
                    adjustFontSize(-1);
                    break;
                case 'grayscale':
                    toggleColorSchema('app-grayscale');
                    break;
                case 'high-contrast':
                    toggleColorSchema('app-high-contrast');
                    break;
                case 'negative-contrast':
                    toggleColorSchema('app-negative-contrast');
                    break;
                case 'light-bg':
                    toggleColorSchema('app-light-bg');
                    break;
                case 'underline-links':
                    $body.toggleClass('app-underline-links');
                    break;
                case 'readable-font':
                    $body.toggleClass('app-readable-font');
                    break;
                case 'reset':
                    resetSettings();
                    return;
            }

            saveSettings();
            syncUIState();
        });

        loadSettings();
    }
});
