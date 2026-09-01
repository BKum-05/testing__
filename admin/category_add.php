<?php
$page_title = "Add New Category";
include '../app/_base.php';

// Distinguish GET and POST request
if (is_post()) {
    // get user input
    $category_name = req('category_name');
    $description = req('description');

    // validation name
    if ($category_name == '') {
    $_err['category_name'] = 'Category name cannot be empty.';
    } elseif (strlen($category_name) > 50) {
    $_err['category_name'] = 'Max length 50 characters';
    } elseif (!preg_match('/^[A-Za-z\s\x{4e00}-\x{9fa5}]+$/u', $category_name)) {
    $_err['category_name'] = 'Category name only allow letters, Chinese and space, no numbers or special symbols.';
    }

    //validation description
    if (strlen($description) > 200) {
        $_err['description'] = 'Max length 200 characters';
    }


    // perform insert operation if no validation errors

    if (!$_err) {
        $stm = $_db->prepare("INSERT INTO category (category_name, description) VALUES (?,?)");
        $stm->execute([$category_name, $description]);
        temp('info', 'New category added successfully!');
        redirect('category_list.php');
    }
}

$_title = 'Add New Category';
include '../app/_head.php';
?>

<form method="post">
    <label for="category_name">Category Name</label>
    <?= html_text('category_name', 'maxlength="50"') ?>
    <?= err('category_name') ?>

    <label for="description">Description</label>
    <textarea name="description" maxlength="200"><?= old('description') ?></textarea>
    <?= err('description') ?>

    <div class=submit-button>
        <button type="submit">Save</button>
        <button type="reset">Reset</button>
    <button onclick="location.href='category_list.php'">Back</button>
    </div>
</form>

<?php include '../app/_foot.php'; ?>
