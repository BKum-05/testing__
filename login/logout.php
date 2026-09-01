<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

if(empty($_SESSION['user_id'])) {
    redirect('login.php');
}

$cancelUrl = $_SERVER['HTTP_REFERER'];

include_head("Confirm Logout - Online Shopping System");
?>

<div class="logout-page-container">
    <div class="logout-card">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h2>Confirm Log Out</h2>
        <p>Are you sure you want to sign out of this device?</p>
        
        <form action="process_logout.php" method="POST" class="logout-form">
            <?php echo csrf_field(); ?>
            <div class="logout-actions">
                <?php html_button('button', 'Cancel', 'class="btn-secondary" onclick="window.location.href=\'' . htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8') . '\';"'); ?>
                <?php html_button('submit', 'Log Out', 'class="btn-danger"'); ?>
            </div>
        </form>
    </div>
</div>

<?php include_foot(); ?>