<?php
$page_title = "Category List";
include '../app/_base.php';

$stmt = $_db->query("SELECT * FROM category");
$categories = $stmt->fetchAll();

// category has any product?
function has_product($cat_id) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) AS total FROM product WHERE category_id = ?");
    $stm->execute([$cat_id]);
    $row = $stm->fetch();
    return $row['total'] > 0;
}
?>

<?php include '../app/_head.php'; ?>  

<style>

.category-grid-container {
    width: 100%;
    border: 1px solid #333;
    margin-bottom: 15px;
}

.grid-header-row {
    display: grid;

    grid-template-columns: 60px 1fr 1.5fr 120px 140px 100px;
    background-color: #eee;
    font-weight: bold;
}

.grid-data-row {
    display: grid;
    grid-template-columns: 60px 1fr 1.5fr 120px 140px 100px;
    border-top: 1px solid #333;
}

.grid-cell {
    padding: 8px;
    border-right: 1px solid #333;
    display: flex;
    align-items: center;
    justify-content: center;
}

.grid-cell:last-child {
    border-right: none;
}

.cell-center {
    justify-content: center;
}
</style>

<!-- Grid  -->
<div class="category-grid-container">
    <div class="grid-header-row">
        <div class="grid-cell cell-center">ID</div>
        <div class="grid-cell cell-center">Category Name</div>
        <div class="grid-cell cell-center">Description</div>
        <div class="grid-cell cell-center">Edit Category</div>
        <div class="grid-cell cell-center">View Product</div>
        <div class="grid-cell cell-center">Delete</div>
    </div>

    <!-- loop data -->
    <?php foreach ($categories as $cat): ?>
    <div class="grid-data-row">
        <div class="grid-cell">
            <?= htmlspecialchars($cat['category_id']) ?>
        </div>
        <div class="grid-cell">
            <?= htmlspecialchars($cat['category_name']) ?>
        </div>
        <div class="grid-cell">
            <?= htmlspecialchars($cat['description']) ?>
        </div>
        <div class="grid-cell cell-center">
            <button data-get="category_update.php?id=<?= $cat['category_id'] ?>">Edit</button>
        </div>
        <div class="grid-cell cell-center">
            <button data-get="product_list.php?filter_cat=<?= $cat['category_id'] ?>">View Products</button>
        </div>
        <div class="grid-cell cell-center">
            <?php if (has_product($cat['category_id'])): ?>
                <button disabled title="Cannot delete: This category still has products">Delete</button>
            <?php else: ?>
                <button onclick="if(confirm('Are you sure to delete this category?')) location.href='category_delete.php?id=<?= $cat['category_id'] ?>'">Delete</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>


<div style="margin-top:10px;text-align:center;">
    <button onclick="location.href='category_add.php'">Add New Category</button>
</div>

<?php include '../app/_foot.php'; ?>