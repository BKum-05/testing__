<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$page_title = "Product";
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

/**
 * 
 * @param array $variantList 
 * @return string
 */
function get_size_range($variantList){
    $sizes = [];
    foreach ($variantList as $v){
        $sizes[] = trim($v['size']);
    }
    $sizes = array_unique($sizes);
    $sizes = array_filter($sizes);

   
    if(count($sizes) ===1 && reset($sizes)==='One Size'){
        return "One Size";
    }
    return implode("‑",$sizes);
}

/**
 * 
 * @param array $variantList
 * @return float
 */
function get_min_price($variantList){
    $prices = [];
    foreach ($variantList as $v){
        $prices[] = $v['price'];
    }
    return min($prices);
}


function is_all_price_same($variantList){
    $prices = [];
    foreach ($variantList as $v){
        $prices[] = $v['price'];
    }
    return count(array_unique($prices)) === 1;
}



$where = [];
$params = [];

$where[] = "product.status = ?";
$params[] = 1;


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


.member-product-grid{
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap:32px 24px;
    margin:24px 0;
}
.member-card{
    background:#ffffff;
    border-radius:10px;
    overflow:hidden;
    cursor:pointer;
    transition: 0.2s ease;
}
.member-card:hover{
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
.member-card-img-wrap{
    aspect-ratio: 1 / 1.2;
    background:#F4F4F4;
}
.member-card-img-wrap img{
    width:100%;
    height:100%;
    object-fit:cover;
}
.member-card-body{
    padding:14px;
}
.card-tagline{
    font-size:15px;
    color:#666666;
    margin:0 0 6px;
}
.card-title{
    font-size:20px;
    font-weight:500;
    margin:0 0 10px;
    color:#351F16;
}
.card-price{
    font-size:24px;
    font-weight:bold;
    color:#351F16;
}

.empty-tip{
    text-align:center;
    padding:40px;
    color:#777;
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
</div>


<div>
<?php if (empty($products)): ?>
    <div class="empty-tip">No products found matching your filter</div>
<?php else: ?>
<div class="member-product-grid">
<?php foreach ($products as $prod):
    $vars = $prod['variants'];
    $sizeText = get_size_range($vars);
    $minPrice = get_min_price($vars);
    $samePrice = is_all_price_same($vars);

   
    if(!empty($vars[0]['image_filename'])){
        $imgSrc = "../app/uploads/product/".htmlspecialchars($vars[0]['image_filename']);
    }else{
        
        $imgSrc = "../app/uploads/product/placeholder.jpg";
    }
?>
<div class="member-card" onclick="location.href='product_detail.php?id=<?= $prod['product_id'] ?>'">
    <div class="member-card-img-wrap">
        <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($prod['product_name']) ?>">
    </div>
    <div class="member-card-body">
        <p class="card-tagline">
            <?= htmlspecialchars(show_val($prod['gender'])) ?>, <?= $sizeText ?>
        </p>
        <h3 class="card-title"><?= htmlspecialchars($prod['product_name']) ?></h3>
        <div class="card-price">
            <?php if($samePrice):?>
                RM <?= number_format($minPrice,2) ?>
            <?php else:?>
                From RM <?= number_format($minPrice,2) ?>
            <?php endif;?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>


<div class="pager-info">
    <?= $pager->count ?> out of <?= $pager->item_count ?> records | Page <?= $pager->page ?> / <?= $pager->page_count ?>
</div>
<nav class="pager">
<?= $pager->html($extra_query, true) ?>
</nav>

<?php include '../app/_foot.php'; ?>
