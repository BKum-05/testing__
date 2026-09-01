<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

function render_email(string $template, array $data = []): string
{
    extract($data);

    include __DIR__ . '/../templates/emails/' . $template . '.php';

    ob_start();
    include __DIR__ . '/../templates/emails/_layout.php';
    return ob_get_clean();
}