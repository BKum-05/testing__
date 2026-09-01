<?php
$page_title = "Add New Product";
include '../app/_base.php';


$_err = [
    'category' => '',
    'name' => '',
    'brand' => '',
    'gender' => '',
    'material' => '',
    'seasonal' => '',
    'variant' => '',
    'desc' => ''
];


$old = [
    'category_id' => post('category_id'),
    'product_name' => post('product_name'),
    'brand' => post('brand'),
    'gender' => post('gender'),
    'material' => post('material'),
    'seasonal' => post('seasonal'),
    'status' => post('status', 1),
    'description' => post('description'),
];


if(is_post()){

    
    $is_accessory = false;
    $cat_stmt = $_db->prepare("SELECT category_name FROM category WHERE category_id = ?");
    $cat_stmt->execute([$old['category_id']]);
    $catRow = $cat_stmt->fetch();
    if ($catRow && $catRow['category_name'] === "Accessories"){
        $is_accessory = true;
    }

    
    if(empty($old['category_id'])) $_err['category'] = "Please select category";
    if(trim($old['product_name']) === '') $_err['name'] = "Product name required";

    if(!$is_accessory && trim($old['brand']) === '') $_err['brand'] = "Brand required";
    if(!$is_accessory && empty($old['gender'])) $_err['gender'] = "Gender required";
    if(!$is_accessory && trim($old['material']) === '') $_err['material'] = "Material required";
    if(!$is_accessory && empty($old['seasonal'])) $_err['seasonal'] = "Season required";

    if(trim($old['description']) === '') $_err['desc'] = "Description required";

    
    $raw_variants_json = post('variants');
    $variants = json_decode($raw_variants_json,true);
    if(empty($variants) || !is_array($variants) || count($variants) === 0){
        $_err['variant'] = "At least one variant group required";
    }


    $has_error = count(array_filter($_err)) > 0;
    if(!$has_error){
       

        
        /*
        $p_stmt = $_db->prepare("INSERT INTO product(category_id,product_name,brand,gender,material,seasonal,status,description) VALUES(?,?,?,?,?,?,?,?)");
        $p_stmt->execute([
            $old['category_id'],
            $old['product_name'],
            $old['brand'],
            $old['gender'],
            $old['material'],
            $old['seasonal'],
            $old['status'],
            $old['description']
        ]);
        $new_pid = $_db->lastInsertId();

        foreach($variants as $v){
            foreach($v['list'] as $item){
                $var_stmt = $_db->prepare("INSERT INTO product_variant(product_id,color,size,price,stock) VALUES(?,?,?,?,?)");
                $var_stmt->execute([$new_pid,$v['color'],$item['size'],$item['price'],$item['stock']]);
            }
        }
        redirect("product_list.php");
        */
    }
}

include '../app/_head.php';
?>

<style>

.form-wrap{
    max-width: 1100px;
    margin:24px auto;
    padding:0 16px;
}
.page-heading{
    margin-bottom:28px;
}
.two-col-layout{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap:24px;
    margin-bottom:32px;
}
.form-card{
    background:#fff;
    padding:24px;
    border-radius:12px;
    box-shadow: 0 1px 6px #00000018;
}
.form-card h3{
    margin-top:0;
    margin-bottom:20px;
    padding-bottom:12px;
    border-bottom:1px solid #eee;
}
.form-item{
    margin-bottom:18px;
}
.form-item label{
    display:block;
    margin-bottom:6px;
    font-weight:500;
}
.required{
    color:#c82423;
}
.form-item input,
.form-item select,
.form-item textarea{
    width:100%;
    box-sizing: border-box;
    padding:10px;
    border:1px solid #ccc;
    border-radius:6px;
}
.error-text{
    color:#c82423;
    font-size:14px;
    margin-top:4px;
}


.variant-card{
    background:#fff;
    padding:24px;
    border-radius:12px;
    box-shadow: 0 1px 6px #00000018;
    margin-bottom:24px;
}
.variant-card h3{
    margin-top:0;
    margin-bottom:20px;
}

.color-group{
    border:1px solid #ddd;
    border-radius:10px;
    padding:20px;
    margin-bottom:16px;
}

.color-group-header {
    display: flex;
    gap: 22px;
    align-items: flex-start;
    flex-wrap: wrap;
    margin-bottom:18px;
}
.color-input-box {
    flex: 1 1 240px;
    min-width:240px;
}
.color-input-box label{
    display:block;
    margin-bottom:6px;
    font-weight:500;
}
.image-upload-box{
    flex-shrink:0;
}
.image-upload-box .upload{
    width:120px;
    height:120px;
    border:2px dashed #cccccc;
    border-radius:8px;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    cursor:pointer;
}
.image-upload-box img{
    max-width:100%;
    max-height:100%;
    object-fit:contain;
}

.size-checkbox-wrap{
    display:flex;
    gap:14px;
    flex-wrap:wrap;
    margin:12px 0 16px 0;
}

.variant-table{
    width:100%;
    border-collapse: collapse;
    margin-bottom:16px;
}
.variant-table td,
.variant-table th {
    border: 1px solid #ddd;
    padding: 10px;
}
.variant-table input{
    width:100%;
    box-sizing:border-box;
    padding:8px;
    border:1px solid #ccc;
    border-radius:4px;
}

.btn-remove{
    background:#777;
    color:white;
    border:none;
    padding:10px 18px;
    border-radius:6px;
    cursor:pointer;
}
.btn-add-group{
    background:#59634e;
    color:white;
    border:none;
    padding:12px 24px;
    border-radius:8px;
    font-size:16px;
    cursor:pointer;
}
.bottom-btn-area{
    margin-top:30px;
    display:flex;
    gap:14px;
}
.btn-submit{
    background:#351F16;
    color:#fff;
    border:none;
    padding:12px 28px;
    border-radius:8px;
    font-size:16px;
}
.submit-button{
    display:flex;
    gap:14px;
}
</style>

<div class="form-wrap">
    <div class="page-heading">
        <h1>Add New Product</h1>
    </div>

    <form method="post" id="mainForm">
        <div class="two-col-layout">
            <div class="form-card">
                <h3>Basic Information</h3>

                <div class="form-item">
                    <label>Category <span class="required">*</span></label>
                    <select name="category_id" id="catSelect">
                        <option value="">-- Select Category --</option>
                        <?php
                        $catQ = $_db->query("SELECT category_id,category_name FROM category ORDER BY category_name");
                        $catAll = $catQ->fetchAll();
                        foreach ($catAll as $c){
                            $sel = $old['category_id'] == $c['category_id'] ? 'selected' : '';
                            echo "<option value='{$c['category_id']}' {$sel}>{$c['category_name']}</option>";
                        }
                        ?>
                    </select>
                    <?php if($_err['category']): ?><div class="error-text"><?= $_err['category'] ?></div><?php endif; ?>
                </div>

                <div class="form-item">
                    <label>Product Name <span class="required">*</span></label>
                    <input type="text" name="product_name" value="<?= htmlspecialchars($old['product_name']) ?>">
                    <?php if($_err['name']): ?><div class="error-text"><?= $_err['name'] ?></div><?php endif; ?>
                </div>

                <div class="form-item" id="wrapBrand">
                    <label>Brand <span class="required">*</span></label>
                    <input type="text" name="brand" value="<?= htmlspecialchars($old['brand']) ?>">
                    <?php if($_err['brand']): ?><div class="error-text"><?= $_err['brand'] ?></div><?php endif; ?>
                </div>

                <div class="form-item" id="wrapGender">
                    <label>Gender <span class="required">*</span></label>
                    <select name="gender">
                        <option value="">--Select--</option>
                        <?php foreach(['Men','Women','Unisex'] as $g): ?>
                            <option <?= $old['gender']==$g?'selected':'' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if($_err['gender']): ?><div class="error-text"><?= $_err['gender'] ?></div><?php endif; ?>
                </div>

            </div>

            <div class="form-card">
                <h3>Other Details</h3>

                <div class="form-item" id="wrapMaterial">
                    <label>Material <span class="required">*</span></label>
                    <input type="text" name="material" value="<?= htmlspecialchars($old['material']) ?>">
                    <?php if($_err['material']): ?><div class="error-text"><?= $_err['material'] ?></div><?php endif; ?>
                </div>

                <div class="form-item" id="wrapSeasonal">
                    <label>Season <span class="required">*</span></label>
                    <select name="seasonal">
                        <option value="">--Select Season--</option>
                        <?php foreach(['Summer','Winter','Spring','Autumn'] as $s): ?>
                            <option <?= $old['seasonal']==$s?'selected':'' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if($_err['seasonal']): ?><div class="error-text"><?= $_err['seasonal'] ?></div><?php endif; ?>
                </div>

                <div class="form-item">
                    <label>Status</label>
                    <select name="status">
                        <option value="1" <?= $old['status']==1?'selected':'' ?>>Active</option>
                        <option value="0" <?= $old['status']==0?'selected':'' ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-item">
                    <label>Description <span class="required">*</span></label>
                    <textarea name="description" rows="5"><?= htmlspecialchars($old['description']) ?></textarea>
                    <?php if($_err['desc']): ?><div class="error-text"><?= $_err['desc'] ?></div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ========== Size & Color Variants ========== -->
        <div class="variant-card">
            <h3>Size & Color Variants <span class="required">*</span></h3>
            <?php if($_err['variant']): ?><div class="error-text"><?= $_err['variant'] ?></div><?php endif; ?>

            <div id="variantContainer">
              
            </div>

            <button type="button" class="btn-add-group" id="btnAddColorGroup">+ Add New Color Group</button>
            <input type="hidden" name="variants" id="inputVariantJson">
        </div>

        <div class="submit-button">
            <a href="product_list.php"><button type="button" class="btn-remove">Cancel</button></a>
            <button type="submit" class="btn-submit">Save Product</button>
        </div>
    </form>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

const sizeList = ["S","M","L","XL","2XL","3XL"];
const container = document.querySelector("#variantContainer");
const btnAdd = document.querySelector("#btnAddColorGroup");
const inputJson = document.querySelector("#inputVariantJson");


function createColorGroup(){
    const g = document.createElement("div");
    g.className = "color-group";

    
    let cbHtml = "";
    for(let s of sizeList){
        cbHtml += `<label><input type="checkbox" class="cb-size" data-size="${s}"> ${s}</label>`;
    }

    g.innerHTML = `
        <div class="color-group-header">
            <div class="color-input-box">
                <label>Color <span class="required">*</span></label>
                <input type="text" class="color-input" placeholder="e.g Black / White">
            </div>
            <div class="image-upload-box">
                <label>Image</label>
                <div class="upload" tabindex="0">
                    <input type="file" accept="image/*" style="display:none;">
                    <input type="hidden" class="temp-img-input" value="">
                    <img src="../app/images/photo.jpg" alt="preview">
                </div>
            </div>
            <div class="size-checkbox-wrap">
            <label>Size</label><br>
                ${cbHtml}
                <label><input type="checkbox" class="cb-one-size"> One-Size</label>
            </div>
        </div>

        <table class="variant-table">
            <thead>
                <tr>
                    <th>Size</th>
                    <th>Price(RM)</th>
                    <th>Stock</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <button type="button" class="btn-remove remove-color-group">Remove This Color Group</button>
    `;


    
    const checkboxes = g.querySelectorAll(".cb-size");
    const oneSizeCb = g.querySelector(".cb-one-size");
    const tbody = g.querySelector(".variant-table tbody");

    
    const fileInput = g.querySelector(".upload input[type=file]");
    const previewImg = g.querySelector(".upload img");
    const uploadBox = g.querySelector(".upload");
    
    uploadBox.addEventListener('click', function(){
        fileInput.click();
    });
   
    fileInput.addEventListener("change",function(e){
        const file = e.target.files[0];
        if(!file) return;
        const reader = new FileReader();
        reader.onload = ev=> previewImg.src = ev.target.result;
        reader.readAsDataURL(file);
    });


    function rebuildTable(){
        tbody.innerHTML = "";
        let checkedSizes = [];
        if(oneSizeCb.checked){
            checkedSizes = ["One‑Size"];
        }else{
            checkboxes.forEach(cb=>{
                if(cb.checked) checkedSizes.push(cb.dataset.size);
            })
        }
        
        for(let sz of checkedSizes){
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${sz}</td>
                <td><input type="number" step="0.01" min="0.01" class="price-input" placeholder="Price(RM)"></td>
                <td><input type="number" min="0" step="1" class="stock-input" placeholder="Stock"></td>
            `
            tbody.appendChild(tr);
        }
    }


    checkboxes.forEach(cb=> cb.addEventListener("change", rebuildTable));
    oneSizeCb.addEventListener("change",function(){
        if(this.checked){
            checkboxes.forEach(cb=> cb.checked = false);
        }
        rebuildTable();
    })


    
    g.querySelector(".remove-color-group").addEventListener("click",()=>{
        g.remove();
    })

    return g;
}


if(btnAdd){
    btnAdd.addEventListener("click",()=>{
        const group = createColorGroup();
        container.appendChild(group);
    })
}


const mainForm = document.querySelector("#mainForm");
if(mainForm){
    mainForm.addEventListener("submit",function(){
        const groups = container.querySelectorAll(".color-group");
        const out = [];
        groups.forEach(g=>{
            const colorVal = g.querySelector(".color-input").value.trim();
            const imgVal = g.querySelector(".temp-img-input").value;
            const rows = g.querySelectorAll(".variant-table tbody tr");
            const variantsArr = [];
            rows.forEach(tr=>{
                const sz = tr.cells[0].innerText;
                const price = tr.querySelector(".price-input").value;
                const stock = tr.querySelector(".stock-input").value;
                variantsArr.push({size:sz,price:price,stock:stock})
            })
            out.push({
                color:colorVal,
                image:imgVal,
                list:variantsArr
            })
        })
        inputJson.value = JSON.stringify(out);
    })
}


const catSelect = document.querySelector("#catSelect");

const wrapBrand = document.querySelector("#wrapBrand");
const starBrand = wrapBrand?.querySelector(".required");

const wrapGender = document.querySelector("#wrapGender");
const starGender = wrapGender?.querySelector(".required");

const wrapMaterial = document.querySelector("#wrapMaterial");
const starMaterial = wrapMaterial?.querySelector(".required");

const wrapSeasonal = document.querySelector("#wrapSeasonal");

function refreshAllFormUI(){
    const selectedText = catSelect.options[catSelect.selectedIndex].text.trim();
    const isAcc = selectedText === "Accessories";

    if(!catSelect.value){
        if(starBrand) starBrand.style.display = "inline";
        if(starGender) starGender.style.display = "inline";
        if(starMaterial) starMaterial.style.display = "inline";
        if(wrapSeasonal) wrapSeasonal.style.display = "block";
        return;
    }

    if(isAcc){
        //accessories：hidden*，hidden season
        if(starBrand) starBrand.style.display = "none";
        if(starGender) starGender.style.display = "none";
        if(starMaterial) starMaterial.style.display = "none";
        if(wrapSeasonal) wrapSeasonal.style.display = "none";
    }else{
        //shirt,dress,pants...
        if(starBrand) starBrand.style.display = "inline";
        if(starGender) starGender.style.display = "inline";
        if(starMaterial) starMaterial.style.display = "inline";
        if(wrapSeasonal) wrapSeasonal.style.display = "block";
    }
}

if(catSelect){
    catSelect.addEventListener("change", refreshAllFormUI);
    refreshAllFormUI();

    const firstGroup = createColorGroup();
    container.appendChild(firstGroup);
}

});
</script>

<?php include '../app/_foot.php'; ?>
