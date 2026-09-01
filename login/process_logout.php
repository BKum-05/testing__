<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';
require_post();

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrfToken)) {
    temp('danger', 'Invalid or expired session. Please try again.');
    redirect(get_dashboard_url());
}

perform_logout();

temp('flash_success', 'You have been logged out successfully.');
redirect('login.php');