<?php
declare(strict_types=1);
require_once __DIR__ . '/app/_auth.php';

if (!empty($_SESSION['user_id'])) {
    redirect(get_dashboard_url());
} else {
    redirect('login/login.php');
}
