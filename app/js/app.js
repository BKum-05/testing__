
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
    // 1. Location Cascading (State -> City -> Postcode)
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
            var jsonUrl = baseUrl ? (baseUrl + '/malaysia.json') : '/malaysia.json';

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
                $.getJSON('/malaysia.json', function (response) {
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
    // 5. Admin Member Bulk Actions & Checkbox Handlers
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
