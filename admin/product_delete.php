<?php
$page_title = "Delete Product";
require '../app/_base.php';


$product_id = req('id');
$filter_cat = req('filter_cat');


$stm = $_db->prepare("SELECT * FROM product WHERE product_id = ?");
$stm->execute([$product_id]);
$prod = $stm->fetch();


if (!$prod) {
    temp('error', 'Product not found');
    $jump = "product_list.php";
    if (!empty($filter_cat)) $jump .= "?filter_cat=" . $filter_cat;
    redirect($jump);
}


if (!empty($prod['image_filename'])) {
    $imgPath = "upload/" . $prod['image_filename'];
    if (file_exists($imgPath)) {
        unlink($imgPath);
    }
}


$deleteStmt = $_db->prepare("DELETE FROM product WHERE product_id = ?");
$deleteStmt->execute([$product_id]);


temp('info', 'Product deleted successfully');
$backUrl = "product_list.php";
if (!empty($filter_cat)) {
    $backUrl .= "?filter_cat=" . $filter_cat;
}
redirect($backUrl);
?>