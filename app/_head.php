<!DOCTYPE html>
<html lang="en">
    <head><!-- head tag - provide info to browser -->
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?=  $_title ?? 'Untitled'  ?></title>
        <link rel="shortcut icon" href="<?= url('app/images/favicon.png') ?>">
        <link rel="stylesheet" href="<?= url('app/css/app.css') ?>">
        <link rel="stylesheet" href="<?= url('app/css/account.css') ?>">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.0/css/all.min.css">
        <script>
            window.BASE_URL = <?= json_encode(BASE_URL) ?>;
        </script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="<?= url('app/js/app.js') ?>"></script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <script src="https://accounts.google.com/gsi/client" async defer></script>

    </head>
    <body>
        <div id="app-content"><!-- (for accessibility) -->
        <header>
            <h1><a href="/">Fashion shop</a></h1>
        </header>

        <nav>
            <a href="/">Index</a>
            <a href="page/demo1.php">Demo 1</a>
            <a href="page/demo2.php">Demo 2</a>
        </nav>

        <main>
       <h1><?= $_title ?? $page_title ?? "Admin Dashboard" ?></h1>
       
