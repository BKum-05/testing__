<?php
$page_title = "Delete Category";
require '../app/_base.php';

// get category id need to delete
$category_id = req('id');

// Verify if the category exists
$stm = $_db->prepare("SELECT * FROM category WHERE category_id = ?");
$stm->execute([$category_id]);
$cat = $stm->fetch();
if (!$cat) {
    temp('error', 'Category not found');
    redirect('category_list.php');
}

// any product exist in category
$stm = $_db->prepare("SELECT COUNT(*) AS count FROM product WHERE category_id = ?");
$stm->execute([$category_id]);
$product_count = $stm->fetch()->count;

if ($product_count > 0) {
    temp('error', 'Delete failed! This category still has related products, please move or delete all products first.');
    redirect('category_list.php');
}

//no product ,then delete
$stm = $_db->prepare("DELETE FROM category WHERE category_id = ?");
$stm->execute([$category_id]);

// delete susccess
temp('info', 'Category deleted successfully');
redirect('category_list.php');
?>
