<?php
$page_title = "Edit Product";
require '../app/_base.php';
$_err = [];
$product = null;
$variants = [];


if (is_get()) {
    $product_id = req('id');
    $filter_cat = req('filter_cat');
    $stm = $_db->prepare("SELECT * FROM product WHERE product_id = ?");
    $stm->execute([$product_id]);
    $product = $stm->fetch();
    if (!$product) redirect('product_list.php');

    $var_stm = $_db->prepare("SELECT * FROM product_variant WHERE product_id = ? ORDER BY variant_id");
    $var_stm->execute([$product_id]);
    $variants = $var_stm->fetchAll();
}


if (is_post()) {
    $product_id = req('id');
    $filter_cat = req('filter_cat');
    $category_id = req('category_id');
    $product_name = trim(req('product_name'));
    $brand = trim(req('brand'));
    $gender = req('gender');
    $material = trim(req('material'));
    $seasonal = trim(req('seasonal'));
    $status = req('status');
    $description = trim(req('description'));
    $post_variants = req('variant') ?? [];

   
    if (empty($category_id)) $_err['category'] = "Please select a valid product category";

    if (empty($product_name)) {
        $_err['name'] = "Product name cannot be empty";
    } elseif (strlen($product_name) < 3 || strlen($product_name) > 80) {
        $_err['name'] = "Product name length 3‑80 characters only";
    } elseif (preg_match('/[!@#$%^&*()_+=<>?\/{}~]/', $product_name)) {
        $_err['name'] = "Only letters, numbers, space and hyphen allowed";
    }

    if (empty($brand)) $_err['brand'] = "Brand name cannot be empty";
    elseif (strlen($brand) > 80) $_err['brand'] = "Brand maximum 80 characters";

    if (empty($gender)) $_err['gender'] = "Please select target gender";
    if (strlen($material) > 100) $_err['material'] = "Material max 100 characters";
    if (empty($seasonal)) $_err['seasonal'] = "Please select suitable season";
    if (strlen($description) > 500) $_err['desc'] = "Description cannot exceed 500 characters";

   
    $has_var_error = false;
    if (empty($post_variants)) {
        $_err['variant'] = "At least one size & color variant must be added";
        $has_var_error = true;
    } else {
        foreach ($post_variants as $idx => $v) {
            $vs = trim($v['size']);
            $vc = trim($v['color']);
            $vp = trim($v['price']);
            $vst = trim($v['stock']);
            if (empty($vs) || empty($vc)) {
                $_err['variant'] = "Row " . ($idx+1) . ": Size & Color cannot empty";
                $has_var_error = true; break;
            }
            if (!is_numeric($vp) || (float)$vp <= 0) {
                $_err['variant'] = "Row " . ($idx+1) . ": Price must be valid positive number";
                $has_var_error = true; break;
            }
            $dec = explode('.', $vp);
            if (isset($dec[1]) && strlen($dec[1]) > 2) {
                $_err['variant'] = "Row " . ($idx+1) . ": Price max 2 decimal places";
                $has_var_error = true; break;
            }
            if (!ctype_digit($vst) || (int)$vst < 0) {
                $_err['variant'] = "Row " . ($idx+1) . ": Stock must be non‑negative integer";
                $has_var_error = true; break;
            }
        }
    }

    $has_error = count($_err) > 0 || $has_var_error;

    if (!$has_error) {
        
        $update_sql = "UPDATE product
            SET category_id=?, product_name=?, brand=?, gender=?, material=?, seasonal=?, status=?, description=?
            WHERE product_id = ?";
        $stmt = $_db->prepare($update_sql);
        $stmt->execute([
            $category_id, $product_name, $brand, $gender, $material, $seasonal, $status, $description, $product_id
        ]);

        $_db->prepare("DELETE FROM product_variant WHERE product_id = ?")->execute([$product_id]);

        $insert_var = $_db->prepare("INSERT INTO product_variant (product_id,size,color,price,stock,image_filename) VALUES (?,?,?,?,?,?)");
        foreach ($post_variants as $v) {
          
            $insert_var->execute([
                $product_id,
                trim($v['size']),
                trim($v['color']),
                trim($v['price']),
                trim($v['stock']),
                ""
            ]);
        }

        temp('info', 'Product updated successfully! Note: Variant images cleared, re‑add in create page.');
        $jumpUrl = "product_list.php";
        if (!empty($filter_cat)) $jumpUrl .= "?filter_cat=" . $filter_cat;
        redirect($jumpUrl);
    }
}

$cat_stmt = $_db->query("SELECT category_id,category_name FROM category ORDER BY category_name");
$category_list = $cat_stmt->fetchAll();

$_title = "Edit Product";
include '../app/_head.php';
?>
<style>
.variant-block{border:1px solid #ccc;padding:12px;margin:8px 0;border-radius:6px;}
.err-text{color:red;font-size:14px;margin-top:4px;display:block;}
</style>
<h2>Edit Fashion Product</h2>
<div style="color:orange;background:#fff3cd;padding:8px;border-radius:4px;margin-bottom:12px;">
⚠️ Note: Edit will clear variant images. To set variant images, create new product.
</div>

<form method="POST" style="display:flex; flex-direction:column; gap:14px; max-width:750px;" id="editForm">
    <input type="hidden" name="id" value="<?= $product['product_id'] ?>">
    <input type="hidden" name="filter_cat" value="<?= encode($filter_cat ?? '') ?>">

    <div><span style="color:#666;">Product ID: <?= $product['product_id'] ?></span></div>

    <div>
        <label>Category:</label><br>
        <select name="category_id" style="width:100%; padding:6px; margin-top:4px;">
            <option value="">-- Select Category --</option>
            <?php foreach ($category_list as $cat): ?>
                <?php
                $sel = '';
                $post_cat = old('category_id');
                if( ($post_cat && $post_cat == $cat['category_id']) || (!$post_cat && $product['category_id'] == $cat['category_id']) ){
                    $sel = 'selected';
                }
                ?>
                <option value="<?= $cat['category_id'] ?>" <?= $sel ?>><?= $cat['category_name'] ?></option>
            <?php endforeach; ?>
        </select>
        <?php if(!empty($_err['category'])): ?><span class="err-text"><?= $_err['category'] ?></span><?php endif; ?>
    </div>

    <div>
        <label>Product Name:</label><br>
        <input type="text" name="product_name" maxlength="80" style="width:100%; padding:6px; margin-top:4px;"
               value="<?= old('product_name') ?: $product['product_name'] ?>">
        <?php if(!empty($_err['name'])): ?><span class="err-text"><?= $_err['name'] ?></span><?php endif; ?>
    </div>

    <div>
        <label>Brand:</label><br>
        <input type="text" name="brand" maxlength="80" style="width:100%; padding:6px; margin-top:4px;"
               value="<?= old('brand') ?: $product['brand'] ?>">
        <?php if(!empty($_err['brand'])): ?><span class="err-text"><?= $_err['brand'] ?></span><?php endif; ?>
    </div>

    <div>
        <label>Target Gender:</label><br>
        <select name="gender" style="width:100%; padding:6px; margin-top:4px;">
            <option value="">-- Select Gender --</option>
            <?php
            $g_list = ['Men','Women','Unisex'];
            foreach($g_list as $g){
                $sel = '';
                $post_g = old('gender');
                if( ($post_g && $post_g == $g) || (!$post_g && $product['gender'] == $g) ) $sel = 'selected';
                echo "<option value='$g' $sel>$g</option>";
            }
            ?>
        </select>
        <?php if(!empty($_err['gender'])): ?><span class="err-text"><?= $_err['gender'] ?></span><?php endif; ?>
    </div>

    <div>
        <label>Material:</label><br>
        <input type="text" name="material" maxlength="100" style="width:100%; padding:6px; margin-top:4px;" placeholder="Cotton, Polyester"
               value="<?= old('material') ?: $product['material'] ?>">
        <?php if(!empty($_err['material'])): ?><span class="err-text"><?= $_err['material'] ?></span><?php endif; ?>
    </div>

    
    <div>
        <label>Suitable Season:</label><br>
        <?php
        $season_arr = ['Summer','Winter','Spring','Autumn','All‑Season'];
        $db_season = old('seasonal') ?? $product['seasonal'];
        ?>
        <select name="seasonal" style="width:100%; padding:6px; margin-top:4px;">
            <option value="">--Select Season--</option>
            <?php foreach($season_arr as $s):?>
                <option value="<?=$s?>" <?= $db_season == $s ? 'selected':'' ?>><?=$s?></option>
            <?php endforeach;?>
        </select>
        <?php if(!empty($_err['seasonal'])): ?><span class="err-text"><?= $_err['seasonal'] ?></span><?php endif; ?>
    </div>

    <div>
        <label>Status:</label><br>
        <select name="status" style="width:100%; padding:6px; margin-top:4px;">
            <?php
            $db_st = old('status') ?? $product['status'];
            $sa = $db_st == 1 ? 'selected' : '';
            $si = $db_st == 0 ? 'selected' : '';
            ?>
            <option value="1" <?= $sa ?>>Active (On Sale)</option>
            <option value="0" <?= $si ?>>Inactive (Off Sale)</option>
        </select>
    </div>

    <div>
        <label>Description (Max 500 characters):</label><br>
        <textarea name="description" rows="4" maxlength="500" style="width:100%; padding:6px; margin-top:4px;"><?= old('description') ?: $product['description'] ?></textarea>
        <?php if(!empty($_err['desc'])): ?><span class="err-text"><?= $_err['desc'] ?></span><?php endif; ?>
    </div>

    <div style="margin-top:10px;">
        <h3>Size & Color Variants</h3>
        <?php if(!empty($_err['variant'])): ?><span class="err-text"><?= $_err['variant'] ?></span><?php endif; ?>
        <div id="variantContainer">
            <?php
            $render_vars = !empty(old('variant')) ? old('variant') : $variants;
            foreach ($render_vars as $idx => $v):
            ?>
            <div class="variant-block" data-row="<?= $idx ?>">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px;">
                    <div>
                        <label>Size</label>
                        <input type="text" name="variant[<?= $idx ?>][size]" value="<?= encode($v['size']) ?>" style="width:100%; padding:5px;" placeholder="S/M/L/XL">
                    </div>
                    <div>
                        <label>Color</label>
                        <input type="text" name="variant[<?= $idx ?>][color]" value="<?= encode($v['color']) ?>" style="width:100%; padding:5px;" placeholder="Black/White">
                    </div>
                    <div>
                        <label>Price(RM)</label>
                        <input type="number" step="0.01" min="0.01" name="variant[<?= $idx ?>][price]" value="<?= encode($v['price']) ?>" style="width:100%; padding:5px;">
                    </div>
                    <div>
                        <label>Stock</label>
                        <input type="number" min="0" step="1" name="variant[<?= $idx ?>][stock]" value="<?= encode($v['stock']) ?>" style="width:100%; padding:5px;">
                    </div>
                </div>
                <button type="button" onclick="removeRow(this)" style="margin-top:8px; color:red;">Remove This Variant</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" onclick="addVariantRow()" style="margin-top:8px;">+ Add More Variant</button>
    </div>

    <div class="submit-button" style="margin-top:10px;">
        <button type="submit">Save Changes</button>
        <button type="reset">Reset</button>
        <?php
        $backUrl = "product_list.php";
        if (!empty($filter_cat)) $backUrl .= "?filter_cat=" . $filter_cat;
        ?>
        <a href="<?= $backUrl ?>"><button type="button">Back to List</button></a>
    </div>
</form>

<script>
let rowIndex = <?= count($render_vars) ?>;
function addVariantRow(){
    const container = document.getElementById('variantContainer');
    const block = document.createElement('div');
    block.className = 'variant-block';
    block.dataset.row = rowIndex;
    block.innerHTML = `
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px;">
            <div>
                <label>Size</label>
                <input type="text" name="variant[${rowIndex}][size]" style="width:100%; padding:5px;" placeholder="S/M/L/XL">
            </div>
            <div>
                <label>Color</label>
                <input type="text" name="variant[${rowIndex}][color]" style="width:100%; padding:5px;" placeholder="Black/White">
            </div>
            <div>
                <label>Price(RM)</label>
                <input type="number" step="0.01" min="0.01" name="variant[${rowIndex}][price]" style="width:100%; padding:5px;">
            </div>
            <div>
                <label>Stock</label>
                <input type="number" min="0" step="1" name="variant[${rowIndex}][stock]" style="width:100%; padding:5px;">
            </div>
        </div>
        <button type="button" onclick="removeRow(this)" style="margin-top:8px; color:red;">Remove This Variant</button>
    `;
    container.appendChild(block);
    rowIndex++;
}
function removeRow(btn){
    const block = btn.closest('.variant-block');
    const container = document.getElementById('variantContainer');
    if(container.children.length > 1){
        block.remove();
    }else{
        alert("At least one variant row must remain");
    }
}
</script>

<?php include '../app/_foot.php'; ?>
