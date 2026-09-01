<?php
$page_title = "Product List";
include '../app/_base.php';
require_once '../app/lib/SimplePager.php';


$search = req('search','');
$filter_cat = req('filter_cat');
$filter_season = req('filter_season');
$page = req('page',1);
$per_page = 8;


$cat_stmt = $_db->query("SELECT category_id, category_name FROM category ORDER BY category_name");
$all_categories = $cat_stmt->fetchAll();


$cat_options = [];
foreach ($all_categories as $c) {
    $cat_options[$c['category_id']] = $c['category_name'];
}


$season_options = [
    "Summer" => "Summer",
    "Winter" => "Winter",
    "Spring" => "Spring",
    "Autumn" => "Autumn",
    "All Season" => "All Season"
];

// Subtitle
$page_subtitle = "All Products";
if (!empty($filter_cat)) {
    $c_stmt = $_db->prepare("SELECT category_name FROM category WHERE category_id = ?");
    $c_stmt->execute([$filter_cat]);
    $current_cat = $c_stmt->fetch();
    if ($current_cat) {
        $page_subtitle = $current_cat['category_name'];
    }
}


function show_val($text){
    $t = trim((string)$text);
    return $t === '' ? '-' : htmlspecialchars($t);
}



$where = [];
$params = [];
if (!empty($filter_cat)) {
    $where[] = "category_id = ?";
    $params[] = $filter_cat;
}
if (!empty($filter_season) && $filter_season !== "All Season") {
    $where[] = "seasonal = ?";
    $params[] = $filter_season;
}

if(!empty($search)){
    $where[] = "(product_name LIKE ? OR brand LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$where_sql = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";


$count_sql = "SELECT COUNT(DISTINCT product_id) FROM product ".$where_sql;
$stm_cnt = $_db->prepare($count_sql);
$stm_cnt->execute($params);
$total_items = $stm_cnt->fetchColumn();


$page = max(1, (int)$page);
$offset = (int)(($page - 1) * $per_page);
$per_page_int = (int)$per_page;


$id_sql = "SELECT DISTINCT product_id FROM product ".$where_sql." ORDER BY product_id DESC LIMIT {$offset}, {$per_page_int}";
$id_stmt = $_db->prepare($id_sql);
$id_stmt->execute($params);
$current_pids = array_column($id_stmt->fetchAll(), 'product_id');


$pager = new SimplePager("SELECT 1", [], $per_page, $page);
$pager->item_count = $total_items;
$pager->page_count = (int)ceil($total_items / $per_page);
$pager->count = count($current_pids);


$products = [];
if(!empty($current_pids)){
    $placeholders = implode(',', array_fill(0, count($current_pids), '?'));
    $sql_full = "SELECT p.*, c.category_name, v.variant_id, v.size, v.color, v.price, v.stock, v.image_filename
        FROM product p
        JOIN category c ON p.category_id = c.category_id
        LEFT JOIN product_variant v ON p.product_id = v.product_id
        WHERE p.product_id IN ($placeholders)
        ORDER BY p.product_id, v.variant_id";
    $prod_stmt = $_db->prepare($sql_full);
    $prod_stmt->execute($current_pids);
    $raw_rows = $prod_stmt->fetchAll();

    foreach ($raw_rows as $row) {
        $pid = $row['product_id'];
        if (!isset($products[$pid])) {
            $products[$pid] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'category_name' => $row['category_name'],
                'brand' => $row['brand'],
                'gender' => $row['gender'],
                'material' => $row['material'],
                'seasonal' => $row['seasonal'],
                'status' => $row['status'],
                'description' => $row['description'],
                'variants' => []
            ];
        }
        if ($row['variant_id']) {
            $products[$pid]['variants'][] = [
                'size' => $row['size'],
                'color' => $row['color'],
                'price' => $row['price'],
                'stock' => $row['stock'],
                'image_filename' => $row['image_filename']
            ];
        }
    }
}
$products = array_values($products);

$extra_q = [];
if($search) $extra_q['search'] = $search;
if($filter_cat) $extra_q['filter_cat'] = $filter_cat;
if($filter_season) $extra_q['filter_season'] = $filter_season;
$extra_query = http_build_query($extra_q);

$_title = $page_subtitle;
include '../app/_head.php';
?>
<style>
.admin-top-bar {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin: 16px 0 24px;
    width: 100%;
    box-sizing: border-box;
}

.filter-inner {
    flex: 1 1 auto;
    min-width: 0;
}

.filter-group {
    margin: 0;
    padding: 0;
    text-align: left !important;
    display:inline;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.search-box {
    padding: 8px 12px;
    width: 220px !important;
    max-width: 220px !important;
    min-width: 220px;
    border: 1px solid #bbb;
    border-radius: 4px;
    background: #fff;
}

.select-filter {
    padding: 8px 12px;
    width: 170px !important;
    max-width: 170px !important;
    min-width: 170px;
    border: 1px solid #bbb;
    border-radius: 4px;
    background: #fff;
}

.action-btn-group {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}

.btn-reset {
    background: #bbb;
    color: #351F16;
}

.btn-reset:hover {
    background: #999;
}

.btn {
    padding: 8px 14px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background: var(--color-btn);
    color: var(--color-bg);
}

.btn-primary:hover {
    background: var(--color-accent);
}

.btn-secondary {
    background: var(--color-accent);
    color: #ffffff;
}

.btn-secondary:hover {
    background: #7c5c4f;
}

.product-card-wrap {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 22px;
    margin: 15px 0;
}
.product-card {
    background:#ffffff;
    border: 1px solid #ddd;
    border-radius: 12px;
    padding:20px;
    box-shadow:0 1px 4px rgba(53,31,22,0.08);
}
.card-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-bottom:1px solid #ddd;
    padding-bottom:10px;
    margin-bottom:12px;
}
.card-header h3{
    margin:0;
    font-size:18px;
    color:var(--color-text);
}
.info-row{
    display:flex;
    margin:6px 0;
}
.info-label{
    width:90px;
    font-weight:bold;
    color:var(--color-text);
}
.variant-box{
    margin:14px 0;
    padding:12px;
    background:var(--color-bg);
    border-radius:8px;
}
.variant-item{
    margin:10px 0;
    display:flex;
    align-items:center;
    gap:12px;
}
.variant-img{
    width:72px;
    height:72px;
    object-fit:cover;
    border:1px solid #ddd;
    border-radius:4px;
}
.card-btn-group{
    margin-top:18px;
    display:flex;
    gap:10px;
}
.card-btn-group button{
    padding:7px 14px;
    border-radius:4px;
    border:1px solid #bbb;
    cursor:pointer;
    background:var(--color-btn);
    color:var(--color-bg);
}
.card-btn-group button:hover{
    background:var(--color-accent);
}

.empty-tip{
    grid-column:1/-1;
    text-align:center;
    padding:50px;
    font-size:16px;
    color:#666;
}

.pager-info{
    margin:12px 0;
    text-align:center;
    color:var(--color-text);
}
.pager{
    margin:24px 0;
    display:flex;
    gap:6px;
    flex-wrap:wrap;
    justify-content:center;
}
.pager a{
    display:block;
    padding:7px 13px;
    border:1px solid #bbb;
    text-decoration:none;
    border-radius:4px;
    color:var(--color-text);
    background:#fff;
}
.pager a.active{
    background:var(--color-btn);
    color:var(--color-bg);
    border-color:var(--color-btn);
}

</style>


<div class="admin-top-bar">
    <div class="filter-inner">
        <form method="GET" action="product_list.php" class="filter-group">
            <input class="search-box" type="text" name="search" placeholder="Search product name / brand" value="<?=htmlspecialchars($search,ENT_QUOTES)?>">

            <select class="select-filter" name="filter_cat">
                <option value="">All Categories</option>
                <?php foreach($all_categories as $c): ?>
                <option value="<?=$c['category_id']?>" <?= $filter_cat == $c['category_id'] ? 'selected' : '' ?>>
                    <?=htmlspecialchars($c['category_name'],ENT_QUOTES)?>
                </option>
                <?php endforeach; ?>
            </select>

            <select class="select-filter" name="filter_season">
                <option value="">All Seasons</option>
                <?php foreach($season_options as $key=>$val): ?>
                <option value="<?=$key?>" <?= $filter_season == $key ? 'selected' : '' ?>><?=$val?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-secondary" type="submit">Apply Filter</button>
            <a href="product_list.php" class="btn btn-reset">Reset</a>
        </form>
    </div>

    <div class="action-btn-group">
        <a href="category_list.php" class="btn btn-secondary">Back to Category List</a>
        <a href="product_add.php" class="btn btn-primary">+ Add New Product</a>
    </div>
</div>

<div class="product-card-wrap">
    <?php if (empty($products)): ?>
        <div class="empty-tip">No products found matching your filter</div>
    <?php else: ?>
        <?php foreach ($products as $prod): ?>
        <div class="product-card">
    <div class="card-header">
        <h3><?= show_val($prod['product_name']) ?></h3>
        <?php if ($prod['status'] == 1): ?>
            <span style="color:#16a34a;font-weight:bold">Active</span>
        <?php else: ?>
            <span style="color:#dc2626;font-weight:bold">Inactive</span>
        <?php endif; ?>
    </div>

    <div class="info-row">
        <div class="info-label">ID:</div>
        <div><?= htmlspecialchars($prod['product_id'],ENT_QUOTES) ?></div>
    </div>
    <div class="info-row">
        <div class="info-label">Category:</div>
        <div><?= show_val($prod['category_name']) ?></div>
    </div>
    <div class="info-row">
        <div class="info-label">Brand:</div>
        <div><?= show_val($prod['brand']) ?></div>
    </div>
    <div class="info-row">
        <div class="info-label">Gender:</div>
        <div><?= show_val($prod['gender']) ?></div>
    </div>
    <div class="info-row">
        <div class="info-label">Season:</div>
        <div><?= show_val($prod['seasonal']) ?></div>
    </div>
    <div class="info-row">
        <div class="info-label">Material:</div>
        <div><?= show_val($prod['material']) ?></div>
    </div>
    <div class="info-row">
        <div class="info-label">Desc:</div>
        <div><?= show_val($prod['description']) ?></div>
    </div>


            <div class="variant-box">
                <strong>Size / Color / Price / Stock</strong>
                <?php if(empty($prod['variants'])): ?>
                    <div class="variant-item"><em>No variant added</em></div>
                <?php else: ?>
                    <?php foreach ($prod['variants'] as $v): ?>
                        <div class="variant-item">
                            <?php if (!empty($v['image_filename'])): ?>
                                <img class="variant-img" src="../app/uploads/product/<?= htmlspecialchars($v['image_filename'],ENT_QUOTES) ?>" alt="<?=htmlspecialchars($v['color'],ENT_QUOTES)?>">
                            <?php else: ?>
                                <div style="width:72px;height:72px;border:1px solid #ddd;display:flex;align-items:center;justify-content:center;font-size:12px;color:#888;border-radius:4px;">No Image</div>
                            <?php endif; ?>
                            <div>
                                <?=htmlspecialchars($v['size'],ENT_QUOTES)?> / <?=htmlspecialchars($v['color'],ENT_QUOTES)?><br>
                                RM <?=htmlspecialchars($v['price'],ENT_QUOTES)?> | Stock: <?= htmlspecialchars($v['stock'],ENT_QUOTES) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card-btn-group">
                <?php
                $editHref = "product_update.php?id={$prod['product_id']}";
                $q = [];
                if($search) $q[]="search=".urlencode($search);
                if($filter_cat) $q[]="filter_cat=$filter_cat";
                if($filter_season) $q[]="filter_season=$filter_season";
                if(!empty($q)) $editHref .= "&".implode("&",$q);
                ?>
                <button onclick="location.href='<?=htmlspecialchars($editHref,ENT_QUOTES)?>'">Edit</button>
                <button onclick="if(confirm('Delete this product and all variants?')) location.href='product_delete.php?id=<?=htmlspecialchars($prod['product_id'],ENT_QUOTES)?>'">Delete</button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="pager-info">
    <?= $pager->count ?> out of <?= $pager->item_count ?> records | Page <?= $pager->page ?> / <?= $pager->page_count ?>
</div>
<nav class="pager">
<?= $pager->html($extra_query, true) ?>
</nav>

<?php include '../app/_foot.php'; ?>
