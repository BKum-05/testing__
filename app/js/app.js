// category_list edit button
$(function(){
    $("[data-get]").click(function(){
        let targetUrl = $(this).data("get");
        window.location.href = targetUrl;
    })
})

// image preview
document.addEventListener('change', function(e) {
    if (e.target.type === 'file' && e.target.accept.includes('image')) {
        const file = e.target.files[0];
        if (!file) return;
        const label = e.target.closest('.upload');
        const img = label.querySelector('img');
        const reader = new FileReader();
        reader.onload = function(ev) {
            img.src = ev.target.result;
        }
        reader.readAsDataURL(file);
    }
})
const categorySelect = document.getElementById('category');
if(!categorySelect){
    
}else{
  
    const clothCategories = ['tshirt','dress','pants','jacket'];

    function updateFormByCategory() {
        const selectedCat = categorySelect.value;
        const isCloth = clothCategories.includes(selectedCat);

      
        document.querySelector('.brand-group').style.display = isCloth ? 'block' : 'none';
        document.getElementById('brand').disabled = !isCloth;
        document.getElementById('brand').value = '';

        document.querySelector('.season-group').style.display = isCloth ? 'block' : 'none';
        document.getElementById('season').disabled = !isCloth;
        document.getElementById('season').value = '';

      
        const allSizeWraps = document.querySelectorAll('.size-field-wrap');
        const allSizeStars = document.querySelectorAll('.size-req-mark');
        const allSizeInputs = document.querySelectorAll('.size-input');
        const allPriceInputs = document.querySelectorAll('.price-input');

        if(isCloth){
           
            allSizeWraps.forEach(el => el.style.display = 'block');
            allSizeStars.forEach(el => el.style.display = 'inline');
            allSizeInputs.forEach(el => el.disabled = false);

            
            allPriceInputs.forEach(input => {
                input.oninput = function(){
                    const newPrice = this.value;
                    allPriceInputs.forEach(p => p.value = newPrice);
                }
            })
        }else{
           
            allSizeWraps.forEach(el => el.style.display = 'none');
            allSizeStars.forEach(el => el.style.display = 'none');
            allSizeInputs.forEach(el => {
                el.disabled = true;
                el.value = '';
            });
      
            allPriceInputs.forEach(input => input.oninput = null);
        }
    }

  
    const addVariantBtn = document.getElementById('add-variant-btn');
    if(addVariantBtn){
        addVariantBtn.addEventListener('click', function(){
            setTimeout(updateFormByCategory, 10);
        })
    }

  
    categorySelect.addEventListener('change', updateFormByCategory);
    updateFormByCategory();
}

// ------------------------------------------
// MARK: Accessibility, bk
// ------------------------------------------
$(function () {
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

    var savedSide = localStorage.getItem('oss_a11y_side') || 'left';
    var savedTop = localStorage.getItem('oss_a11y_top_pos');

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
      initialLeft = containerOffset.left;
      initialTop = containerOffset.top - $(window).scrollTop();

      $container.css({
        left: initialLeft + 'px',
        right: 'auto',
        top: initialTop + 'px'
      });

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

      var windowWidth = $(window).width();
      var windowHeight = $(window).height();
      var btnWidth = $triggerBtn.outerWidth();
      var btnHeight = $triggerBtn.outerHeight();

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
        var windowWidth = $(window).width();
        var currentLeft = parseInt($container.css('left'), 10) || 0;
        var btnWidth = $triggerBtn.outerWidth();
        var centerX = currentLeft + (btnWidth / 2);

        var targetSide = (centerX < windowWidth / 2) ? 'left' : 'right';

        applySidePosition(targetSide);

        localStorage.setItem('oss_a11y_side', targetSide);
        localStorage.setItem('oss_a11y_top_pos', parseInt($container.css('top'), 10));
      }
    });

    $triggerBtn.on('click', function (e) {
      e.preventDefault();

      if (hasMoved) {
        hasMoved = false;
        return;
      }

      var isOpen = $menu.toggleClass('a11y-menu-open').hasClass('a11y-menu-open');
      $(this).attr('aria-expanded', isOpen);
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

      // Clean up all existing font step classes first
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
      localStorage.removeItem(STORAGE_KEY);
      localStorage.removeItem('oss_a11y_top_pos');
      localStorage.removeItem('oss_a11y_side');

      applySidePosition('left');
      $container.css('top', '140px');
      syncUIState();
    }

    function saveSettings() {
      var activeClasses = Array.from($body[0].classList)
        .concat(Array.from($appContent[0].classList))
        .filter(function (c) { return c.startsWith('app-'); });

      var settings = {
        classes: activeClasses,
        fontStep: fontStep
      };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    }

    function loadSettings() {
      var stored = localStorage.getItem(STORAGE_KEY);
      if (!stored) return;

      try {
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
      } catch (err) {
        console.error('Error reading accessibility settings:', err);
      }
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

$(window).on('load', function () {
  if ($(".page-content").length) {
    gsap.from(".page-content", {
      opacity: 0,
      y: 30,
      duration: 0.8,
      ease: "power2.out"
    });
  }
});

// ==========================================
// MARK: Global Variables & Utilities
// ==========================================
var locationData = {};

function buildOption(value, text, selected) {
  return $('<option>', {
    value: value,
    text: text,
    selected: !!selected
  });
}

function resetSelect($select, placeholder, disabled) {
  $select.empty().append(buildOption('', placeholder, true));
  $select.prop('disabled', disabled);
}

function getInitialValue($element, key) {
  var value = $element.data(key) || $element.attr('data-' + key) || $element.val();
  return value ? String(value) : '';
}

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

function generateTemporaryPassword() {
  var pool = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*?';
  var required = [
    'abcdefghijklmnopqrstuvwxyz',
    'ABCDEFGHJKLMNPQRSTUVWXYZ',
    '23456789',
    '!@#$%&*?'
  ];
  var passwordCharacters = [];

  $.each(required, function (_, characters) {
    passwordCharacters.push(characters.charAt(Math.floor(Math.random() * characters.length)));
  });

  while (passwordCharacters.length < 8) {
    passwordCharacters.push(pool.charAt(Math.floor(Math.random() * pool.length)));
  }

  return passwordCharacters.sort(function () {
    return Math.random() - 0.5;
  }).join('');
}

function getPasswordIssues(password) {
  var issues = [];

  if (!password || password.length < 8) {
    issues.push('At least 8 characters');
  }
  if (!/[A-Z]/.test(password)) {
    issues.push('One uppercase letter');
  }
  if (!/[a-z]/.test(password)) {
    issues.push('One lowercase letter');
  }
  if (!/[0-9]/.test(password)) {
    issues.push('One number');
  }
  if (!/[^A-Za-z0-9]/.test(password)) {
    issues.push('One symbol');
  }

  return issues;
}

function showAlert(message, type) {
  var $alertBox = $('#alertBox');
  if (!$alertBox.length) return;

  $alertBox
    .removeClass('alert-success alert-danger')
    .addClass(type === 'success' ? 'alert-success' : 'alert-danger')
    .text(message)
    .attr('role', 'status')
    .attr('aria-live', 'polite')
    .css('display', 'block');

  $alertBox.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
  if (type !== 'success') {
    $alertBox.attr('tabindex', '-1').trigger('focus');
  }
}

// ==========================================
// MARK: Google OAuth
// ==========================================
function handleGoogleSignIn(response) {
  $.ajax({
    url: 'process_google_auth.php',
    type: 'POST',
    data: {
      token: response.credential,
      csrf_token: $('input[name="csrf_token"]').first().val()
    },
    dataType: 'json'
  })
    .done(function (res) {
      if (res.success) {
        window.location.href = res.redirect_url || 'profile.php';
      } else {
        showAlert('Google Sign-In failed: ' + (res.message || 'Unknown error'));
      }
    })
    .fail(function (xhr) {
      var res = xhr.responseJSON;
      showAlert(res && res.message ? res.message : 'Google sign-in server request failed.');
    });
}

// ==========================================
// MARK: Main DOM Ready Initialization
// ==========================================
$(function () {
  // ------------------------------------------
  // Password Toggle Handler
  // ------------------------------------------
  $(document).on('click', '.toggle-password, #togglePassword', function () {
    var $input = $(this).siblings('input');
    if (!$input.length) {
      $input = $(this).parent().find('input');
    }

    var isPassword = $input.attr('type') === 'password';
    $input.attr('type', isPassword ? 'text' : 'password');

    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
  });

  // ------------------------------------------
  // Location Cascade for Datalists
  // ------------------------------------------
  var $state = $('#state');
  var $city = $('#city');
  var $postcode = $('#postcode');

  if ($state.length && $city.length && $postcode.length) {
    var initialState = getInitialValue($state, 'current-state');
    var initialCity = getInitialValue($city, 'current-city');
    var initialPostcode = getInitialValue($postcode, 'current-postcode');

    function updateDatalist($input, optionsArray, placeholder) {
      var listId = $input.attr('list');
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
      var stateNames = Object.keys(locationData);
      updateDatalist($state, stateNames, 'Select State');

      if (initialState) {
        $state.val(initialState);
        if (locationData[initialState]) {
          populateCities(true);
        }
      }
    }

    function populateCities(isInitialLoad) {
      var selectedState = $state.val();
      var selectedCity = isInitialLoad ? initialCity : '';

      updateDatalist($city, [], 'Select City');
      updateDatalist($postcode, [], 'Select Postcode');

      $city.val('');
      $postcode.val('');

      if (!selectedState || !locationData[selectedState]) {
        $city.prop('disabled', true);
        $postcode.prop('disabled', true);
        return;
      }

      var cityNames = Object.keys(locationData[selectedState]);
      updateDatalist($city, cityNames, 'Select City');
      $city.prop('disabled', false);

      if (selectedCity && locationData[selectedState][selectedCity]) {
        $city.val(selectedCity);
        populatePostcodes(isInitialLoad);
      }
    }

    function populatePostcodes(isInitialLoad) {
      var selectedState = $state.val();
      var selectedCity = $city.val();
      var selectedPostcode = isInitialLoad ? initialPostcode : '';

      updateDatalist($postcode, [], 'Select Postcode');
      $postcode.val('');

      if (!selectedState || !selectedCity || !locationData[selectedState] || !locationData[selectedState][selectedCity]) {
        $postcode.prop('disabled', true);
        return;
      }

      var postcodes = locationData[selectedState][selectedCity];
      updateDatalist($postcode, postcodes, 'Select Postcode');
      $postcode.prop('disabled', false);

      if (selectedPostcode) {
        $postcode.val(selectedPostcode);
      }
    }

    function loadLocationData() {
      $.ajax({
        url: (window.BASE_URL || '') + '/malaysia.json',
        type: 'GET',
        dataType: 'json'
      })
        .done(function (response) {
          locationData = typeof normalizeLocationData === 'function' ? normalizeLocationData(response) : response;
          populateStates();
        })
        .fail(function () {
          console.error('Unable to load location data from malaysia.json.');
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

  // ------------------------------------------
  // Login Form
  // ------------------------------------------
  if ($('#loginForm').length) {
    var $loginForm = $('#loginForm');
    var $loginBtn = $loginForm.find('button[type="submit"]');
    var $loginEmail = $('#email');
    var loginLockTimer = null;
    var LOCKOUT_STORAGE_KEY = 'login_lockout';

    function startLoginLockout(seconds, email) {
      clearInterval(loginLockTimer);
      var remaining = seconds;

      if (email) {
        localStorage.setItem(LOCKOUT_STORAGE_KEY, JSON.stringify({
          email: email.toLowerCase(),
          lockoutUntil: Date.now() + seconds * 1000
        }));
      }

      function tick() {
        if (remaining <= 0) {
          clearInterval(loginLockTimer);
          $loginBtn.prop('disabled', false).text('Sign In');
          localStorage.removeItem(LOCKOUT_STORAGE_KEY);
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
      var raw = localStorage.getItem(LOCKOUT_STORAGE_KEY);
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
        localStorage.removeItem(LOCKOUT_STORAGE_KEY);
      }
    }

    // Resume countdown on page load
    checkStoredLockout();
    $loginEmail.on('input blur', checkStoredLockout);

    $loginForm.on('submit', function (e) {
      e.preventDefault();
      $('#alertBox').slideUp();

      var turnstileToken = $('[name="cf-turnstile-response"]').val();
      if (!turnstileToken) {
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
          if (res.password_reset_required) {
            window.location.href = res.redirect_url || 'set_password.php';
            return;
          }
          if (res.success) {
            localStorage.removeItem(LOCKOUT_STORAGE_KEY);
            window.location.href = res.redirect_url || 'profile.php';
            return;
          }

          showAlert(res.message || 'Login failed.');
          if (typeof turnstile !== 'undefined') turnstile.reset();

          if (res.locked && res.seconds_remaining) {
            startLoginLockout(res.seconds_remaining, enteredEmail);
          } else {
            $loginBtn.prop('disabled', false).text(originalText);
          }
        })
        .fail(function (xhr) {
          var res = xhr.responseJSON;
          showAlert(res && res.message ? res.message : 'Server error occurred during login.');
          if (typeof turnstile !== 'undefined') turnstile.reset();

          if (res && res.locked && res.seconds_remaining) {
            startLoginLockout(res.seconds_remaining, enteredEmail);
          } else {
            $loginBtn.prop('disabled', false).text(originalText);
          }
        });
    });
  }

  // ------------------------------------------
  // MARK: Info Tooltip
  // ------------------------------------------
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

  // ------------------------------------------
  // MARK: Registration Form
  // ------------------------------------------
  if ($('#regForm').length) {
    $('#regForm').on('submit', function (e) {
      e.preventDefault();
      $('#alertBox').slideUp();

      var turnstileToken = $('[name="cf-turnstile-response"]').val();
      if (!turnstileToken) {
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
          if (res.success) {
            showAlert(res.message || 'Verification code sent! Redirecting...', 'success');
            setTimeout(function () {
              window.location.href = res.redirect || 'verify_otp.php';
            }, 1000);
            return;
          }

          showAlert(res.message || 'Please check your inputs.');
          if (typeof turnstile !== 'undefined') turnstile.reset();
          $btn.prop('disabled', false).text(originalText);
        })
        .fail(function (xhr) {
          var res = xhr.responseJSON;
          showAlert(res && res.message ? res.message : 'Server communication error.');
          if (typeof turnstile !== 'undefined') turnstile.reset();
          $btn.prop('disabled', false).text(originalText);
        });
    });
  }

  var $phoneNumber = $('#phone_number');
  if ($phoneNumber.length) {
    $phoneNumber.on('input', function () {
      this.value = this.value.replace(/\D/g, '');
    });
  }

  var $postcode = $('#postcode');
  if ($postcode.length) {
    $postcode.on('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 5);
    });
  }


  // ------------------------------------------
  // MARK: Profile Form
  // ------------------------------------------
  if ($('#profileForm').length) {
    const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

    $('#avatar').on('change', function (e) {
      const file = e.target.files[0];
      const $img = $('#avatarPreview');
      if (!$img.length) return;

      const img = $img[0];
      img.dataset.src ??= img.src;

      if (!file) return;

      if (!file.type.startsWith('image/')) {
        showAlert('Please select an image file.');
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

      img.src = URL.createObjectURL(file);
    });

    $('#profileForm').on('submit', function (e) {
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
          if (res.success) {
            showAlert(res.message || 'Profile updated successfully!', 'success');
            if (res.avatar_url) $('#avatarPreview').attr('src', res.avatar_url);
            $('#current_password, #new_password, #confirm_password').val('');
            if (typeof turnstile !== 'undefined') turnstile.reset();
            return;
          }
          showAlert('Update failed: ' + (res.message || 'Please check your inputs.'));
          if (typeof turnstile !== 'undefined') turnstile.reset();
        })
        .fail(function (xhr) {
          var res = xhr.responseJSON;
          showAlert(res && res.message ? res.message : 'Server error occurred during profile save.');
          if (typeof turnstile !== 'undefined') turnstile.reset();
        })
        .always(function () {
          $btn.prop('disabled', false).text(originalText);
        });
    });
  }


  if ($('#deleteAccountForm').length) {
    $('#deleteAccountForm').on('submit', function (e) {
      e.preventDefault();
      if (!confirm('This will permanently delete your account. This cannot be undone. Continue?')) return;

      var $btn = $(this).find('button[type="submit"]');
      var originalText = $btn.text();
      $btn.prop('disabled', true).text('Deleting…');

      $.ajax({ url: 'process_delete_account.php', type: 'POST', data: $(this).serialize(), dataType: 'json' })
        .done(function (res) {
          if (res.success) {
            showAlert(res.message, 'success');
            setTimeout(function () { window.location.href = res.redirect; }, 1000);
            return;
          }
          showAlert(res.message || 'Failed to delete account.');
          $btn.prop('disabled', false).text(originalText);
        })
        .fail(function (xhr) {
          var res = xhr.responseJSON;
          showAlert(res && res.message ? res.message : 'Server error.');
          $btn.prop('disabled', false).text(originalText);
        });
    });
  }


  if ($('#memberCreateForm').length) {
    $('#memberCreateForm').on('submit', function (e) {
      e.preventDefault();
      var $btn = $('#btn-member-create');
      var originalText = $btn.text();
      $btn.prop('disabled', true).text('Creating…');

      $.ajax({ url: 'process_member_create.php', type: 'POST', data: $(this).serialize(), dataType: 'json' })
        .done(function (res) {
          if (res.success) {
            showAlert(res.message, 'success');
            setTimeout(function () { window.location.href = res.redirect; }, 800);
            return;
          }
          showAlert(res.message || 'Failed to create account.');
        })
        .fail(function (xhr) {
          var res = xhr.responseJSON;
          showAlert(res && res.message ? res.message : 'Server error.');
        })
        .always(function () { $btn.prop('disabled', false).text(originalText); });
    });
  }


  // ------------------------------------------
  // MARK: Password Validation
  // ------------------------------------------
  function initPasswordValidation(newPwSelector, confirmPwSelector) {
    var $newPw = $(newPwSelector);
    var $confirmPw = $(confirmPwSelector);
    if (!$newPw.length) return;

    function evaluate() {
      var val = $newPw.val() || '';
      var confirmVal = $confirmPw.val() || '';

      toggleReq('#req-length', val.length >= 8);
      toggleReq('#req-casing', /[a-z]/.test(val) && /[A-Z]/.test(val));
      toggleReq('#req-number', /[0-9]/.test(val));
      toggleReq('#req-symbol', /[\W_]/.test(val));
      toggleReq('#req-match', val.length > 0 && val === confirmVal);
    }

    function toggleReq(selector, isValid) {
      var $li = $(selector);
      $li.toggleClass('valid', isValid);
      $li.find('i').remove();
      $li.prepend('<i class="fas ' + (isValid ? 'fa-check-circle' : 'fa-circle') + '"></i>');
    }

    $newPw.on('input', evaluate);
    $confirmPw.on('input', evaluate);
  }

  $(function () {
    initPasswordValidation('#new_password', '#confirm_password');
  });

  // ------------------------------------------
  // MARK: Reset Password Form & Live Validation
  // ------------------------------------------
  if ($('#resetPasswordForm').length) {
    $('#resetPasswordForm').on('submit', function (e) {
      e.preventDefault();

      var currentPassword = $('#current_password').val();
      var newPassword = $('#new_password').val();
      var confirmPassword = $('#confirm_password').val();
      var passwordIssues = getPasswordIssues(newPassword);

      if (!currentPassword || !newPassword || !confirmPassword) {
        showAlert('Please complete all password fields.');
        return;
      }

      if (newPassword !== confirmPassword) {
        showAlert('New passwords do not match.');
        return;
      }

      if (passwordIssues.length) {
        showAlert('Password requirements not met: ' + passwordIssues.join(', '));
        return;
      }

      $.ajax({
        url: 'process_password_reset.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json'
      })
        .done(function (res) {
          if (res.success) {
            window.location.href = 'profile.php';
            return;
          }

          showAlert('Password reset failed: ' + res.message);
        })
        .fail(function () {
          showAlert('Server error occurred during password reset.');
        });
    });
  }

  var $newPassword = $('#new_password');
  var $confirmPassword = $('#confirm_password');

  if ($newPassword.length || $confirmPassword.length) {
    function checkRequirement($el, isValid) {
      var $icon = $el.find('i');
      if (isValid) {
        $el.addClass('valid');
        $icon.removeClass('fa-circle').addClass('fa-check-circle');
      } else {
        $el.removeClass('valid');
        $icon.removeClass('fa-check-circle').addClass('fa-circle');
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


  if ($('#set-password-form').length) {
    $('#set-password-form').on('submit', function (e) {
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
          if (res.success) {
            window.location.href = res.redirect || 'profile.php';
            return;
          }
          showAlert(res.message || 'Failed to set password.');
          $btn.prop('disabled', false).text(originalText);
        })
        .fail(function (xhr) {
          var res = xhr.responseJSON;
          showAlert(res && res.message ? res.message : 'Server error occurred.');
          $btn.prop('disabled', false).text(originalText);
        });
    });
  }


  // Add to js/app.js
if ($('#adminResetPasswordForm').length) {
  $('#adminResetPasswordForm').on('submit', function (e) {
    e.preventDefault();
    var $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true).text('Sending...');

    $.ajax({
      url: 'process_member_reset_password.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json'
    })
      .done(function (res) {
        showAlert(res.message, res.success ? 'success' : 'danger');
      })
      .fail(function () {
        showAlert('Server error occurred during password reset request.', 'danger');
      })
      .always(function () {
        $btn.prop('disabled', false).text('Send Password Reset Email');
      });
  });
}

  // ------------------------------------------
  // MARK: Resend OTP
  // ------------------------------------------
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

    $resendBtn.on('click', function () {
      var csrfToken = $('input[name="csrf_token"]').val();

      $.ajax({
        url: 'resend_otp.php',
        type: 'POST',
        data: { csrf_token: csrfToken },
        dataType: 'json'
      })
        .done(function (res) {
          var alertBox = document.getElementById('otp-alert');
          if (res.success) {
            alertBox.className = 'alert-box alert-success';
            alertBox.textContent = res.message;
            alertBox.style.display = 'block';
            startCooldown(res.seconds_remaining || 60);
          } else {
            alertBox.className = 'alert-box alert-danger';
            alertBox.textContent = res.message;
            alertBox.style.display = 'block';
            if (res.seconds_remaining) {
              startCooldown(res.seconds_remaining);
            }
          }
        })
        .fail(function (xhr) {
          var res = xhr.responseJSON;
          var alertBox = document.getElementById('otp-alert');
          alertBox.className = 'alert-box alert-danger';
          alertBox.textContent = res && res.message ? res.message : 'Failed to resend code.';
          alertBox.style.display = 'block';
        });
    });
  }

  // ------------------------------------------
  // MARK: Forgot Password Form
  // ------------------------------------------
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


  // ------------------------------------------
  // MARK: Member List Actions
  // ------------------------------------------
  $(document).ready(function () {
    function updateBulkCount() {
      $('#bulkSelectedCount').text($('.member-checkbox:checked').length + ' selected');
    }


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
  });
});
