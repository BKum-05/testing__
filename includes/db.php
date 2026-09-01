<?php
// app/lib/db.php
$dbhost = 'localhost';
$dbname = 'fashion_shop';
$dbuser = 'root';
$dbpass = '';

try {
    $db = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connect failed : " . $e->getMessage());
}
?>
