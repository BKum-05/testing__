<?php
$page_title = "Edit Category";
require '../app/_base.php';


if (is_get()) {
    $category_id = req('id');
    $stm = $_db->prepare("SELECT * FROM category WHERE category_id=?");
    $stm->execute([$category_id]);
    $cat = $stm->fetch();
    if(!$cat) redirect('/admin/category_list.php');

    $category_name = $cat['category_name'];
    $description = $cat['description'];

}


if (is_post()) {
    $category_id   = req('id');
    $category_name = req('category_name');
    $description = req('description');

    
    if ($category_name == '') {
        $_err['category_name'] = 'Category name is required';
    } else if (strlen($category_name) > 50) {
        $_err['category_name'] = 'Max length 50 characters';
    }

    
    if (strlen($description) > 200) {
        $_err['description'] = 'Max length 200 characters';
    }

    //no error then update
    if (!$_err) {
        
        $stm = $_db->prepare("UPDATE category SET category_name=?, description=? WHERE category_id=?");
        $stm->execute([$category_name, $description, $category_id]);
        temp('info', 'Category updated successfully!');
        redirect('category_list.php');
    }
}

$_title = 'Edit Category';
include '../app/_head.php';
?>

<form method="post" style="display:flex; flex-direction:column; gap:12px;">

    
    <input type="hidden" name="id" value="<?= $category_id ?>">

    <span>Category ID: <?= $category_id ?></span>


   <label for="category_name">Category Name</label>
    <input type="text" id="category_name" name="category_name" maxlength="50" value="<?= old('category_name') ?: $category_name ?>">
    <?= err('category_name') ?>


    <label for="description">Description</label>
    
    <textarea name="description" maxlength="200" rows="4">
    <?= old('description') ?: $description ?>
    </textarea>
    <?= err('description') ?>

    <div class=submit-button>
        <button type="submit">Save Changes</button>
        <button type="reset">Reset</button>
        <a href="category_list.php">Back to List</a>
    </div>
</form>

<?php
include '../app/_foot.php';
?>
