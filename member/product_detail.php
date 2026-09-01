<?php
$page_title = "Product Detail";
include '../app/_base.php';


$product_id = req('id');
if (empty($product_id)) {
    redirect('product_list.php');
}


$stm = $_db->prepare("
    SELECT p.*, c.category_name
    FROM product p
    JOIN category c ON p.category_id = c.category_id
    WHERE p.product_id = ? AND p.status = 1
");
$stm->execute([$product_id]);
$product = $stm->fetch();


if (!$product) {
    redirect('product_list.php');
}


$var_stm = $_db->prepare("
    SELECT * FROM product_variant
    WHERE product_id = ?
    ORDER BY variant_id
");
$var_stm->execute([$product_id]);
$variants = $var_stm->fetchAll();


function show_val($text)
{
    $t = trim((string)$text);
    return $t === '' ? '-' : htmlspecialchars($t);
}


$variant_json = json_encode($variants);

include '../app/_head.php';
?>
<style>
    body {
        background: #F4F4F4;
    }

    .detail-wrap {
        max-width: 1400px;
        margin: 40px auto;
        padding: 0 24px;
    }

    .back-link {
        margin-bottom: 24px;
    }

    .back-link a {
        text-decoration: none;
        color: #351F16;
        padding: 8px 14px;
        border: 1px solid #DDD3CB;
        border-radius: 6px;
    }

    .back-link a:hover {
        background: #DDD3CB;
    }


    .detail-row {
        display: flex;
        gap: 48px;
        background: #ffffff;
        padding: 36px;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(53, 31, 22, 0.08);
    }

    .col-left {
        flex: 1 1 400px;
        max-width: 550px;
    }

    .col-right {
        flex: 1.2 1 420px;
    }


    .carousel-wrap {
        position: relative;
        width: 100%;

        aspect-ratio: 1 / 1;
        overflow: hidden;
        border-radius: 12px;
        border: 1px solid #eee;
    }

    .carousel-track {
        display: flex;
        height: 100%;
        transition: transform 0.4s ease;
    }

    .carousel-slide {
        flex: 0 0 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f7f7f7;
    }

    .carousel-slide img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .carousel-noimg {
        color: #888;
        font-size: 16px;
    }

    .carousel-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #ffffffdd;
        border: none;
        font-size: 20px;
        cursor: pointer;
        z-index: 2;
    }

    .arrow-prev {
        left: 12px;
    }

    .arrow-next {
        right: 12px;
    }

    .carousel-page-text {
        position: absolute;
        bottom: 12px;
        right: 12px;
        background: #00000066;
        color: #fff;
        font-size: 13px;
        padding: 3px 10px;
        border-radius: 6px;
    }


    .prod-title {
        font-size: 26px;
        margin: 0 0 20px;
        color: #351F16;
    }

    .meta-list {
        margin-bottom: 28px;
    }

    .meta-item {
        display: flex;
        padding: 6px 0;
    }

    .meta-label {
        width: 110px;
        font-weight: bold;
        color: #555;
    }


    .select-variant-area {
        padding: 24px;
        background: #F8F6F4;
        border-radius: 12px;
    }

    .select-variant-area h3 {
        margin-top: 0;
        font-size: 18px;
        color: #351F16;
    }

    .row-select {
        margin: 16px 0;
    }

    .row-select label {
        display: inline-block;
        width: 90px;
        font-weight: bold;
    }

    .row-select select {
        padding: 10px 12px;
        width: 100%;
        max-width: 280px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }

    .show-price-stock {
        margin: 20px 0;
        font-size: 18px;
    }

    .show-price-stock div {
        margin: 6px 0;
    }

    .btn-group {
        margin-top: 28px;
        display: flex;
        gap: 16px;
    }

    .btn-group button {
        flex: 1;
        padding: 14px 32px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
    }

    .btn-cart {
        background: #DDD3CB;
        color: #351F16;
    }

    .btn-buy {
        background: #351F16;
        color: #ffffff;
    }

    button:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }


    @media(max-width:960px) {
        .detail-row {
            flex-direction: column;
        }

        .col-left {
            max-width: 100%;
        }
    }
</style>

<div class="detail-wrap">
    <div class="back-link">
        <a href="product_list.php">← Back to Product List</a>
    </div>

    <div class="detail-row">

        <div class="col-left">
            <div class="carousel-wrap">
                <button class="carousel-arrow arrow-prev" id="btnPrev">&lt;</button>
                <div class="carousel-track" id="carouselTrack"></div>
                <button class="carousel-arrow arrow-next" id="btnNext">&gt;</button>
                <div class="carousel-page-text" id="pageText">1 / 1</div>
            </div>
        </div>


        <div class="col-right">
            <h2 class="prod-title"><?= show_val($product['product_name']) ?></h2>

            <div class="meta-list">
                <div class="meta-item">
                    <div class="meta-label">Category:</div>
                    <div><?= show_val($product['category_name']) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Brand:</div>
                    <div><?= show_val($product['brand']) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Gender:</div>
                    <div><?= show_val($product['gender']) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Season:</div>
                    <div><?= show_val($product['seasonal']) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Material:</div>
                    <div><?= show_val($product['material']) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Description:</div>
                    <div><?= show_val($product['description']) ?></div>
                </div>
            </div>

            <div class="select-variant-area">
                <h3>Select Size & Color</h3>
                <div class="row-select">
                    <label>Size:</label>
                    <select id="selSize">
                        <option value="">-- Please select size --</option>
                    </select>
                </div>
                <div class="row-select">
                    <label>Color:</label>
                    <select id="selColor">
                        <option value="">-- Please select color --</option>
                    </select>
                </div>

                <div class="show-price-stock">
                    <div>Price: <span id="dispPrice">-</span></div>
                    <div>Stock: <span id="dispStock">-</span></div>
                </div>

                <div class="btn-group">
                    <form action="cart.php" method="POST" id="cartForm">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="variant_id" id="variant_id">

                        <button type="submit" id="btnAddCart" class="btn-cart" disabled>
                            Add To Cart
                        </button>
                    </form>
                    <button id="btnBuyNow" class="btn-buy" disabled>Buy Now</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const variantList = <?= $variant_json ?>;
    const selSize = document.getElementById('selSize');
    const selColor = document.getElementById('selColor');
    const dispPrice = document.getElementById('dispPrice');
    const dispStock = document.getElementById('dispStock');
    const btnAddCart = document.getElementById('btnAddCart');
    const btnBuyNow = document.getElementById('btnBuyNow');


    const carouselTrack = document.getElementById('carouselTrack');
    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    const pageText = document.getElementById('pageText');
    const variantInput = document.getElementById('variant_id');

    let currentSlideIndex = 0;


    function buildCarousel() {
        carouselTrack.innerHTML = '';
        variantList.forEach((v, idx) => {
            const slide = document.createElement('div');
            slide.className = 'carousel-slide';
            if (v.image_filename) {
                slide.innerHTML = `<img src="../app/uploads/product/${v.image_filename}" alt="">`;
            } else {
                slide.innerHTML = `<div class="carousel-noimg">No Image</div>`;
            }
            carouselTrack.appendChild(slide);
        });
        updateCarouselUI();
    }


    function updateCarouselUI() {
        const total = variantList.length;
        if (total === 0) return;
        if (currentSlideIndex < 0) currentSlideIndex = total - 1;
        if (currentSlideIndex >= total) currentSlideIndex = 0;
        carouselTrack.style.transform = `translateX(-${currentSlideIndex *100}%)`;
        pageText.innerText = `${currentSlideIndex+1} / ${total}`;
    }


    btnPrev.addEventListener('click', () => {
        currentSlideIndex--;
        updateCarouselUI();
    });
    btnNext.addEventListener('click', () => {
        currentSlideIndex++;
        updateCarouselUI();
    });


    function jumpCarouselByVariant(s, c) {
        const target = variantList.find(item => item.size === s && item.color === c);
        if (!target) return;
        const idx = variantList.findIndex(v => v.variant_id === target.variant_id);
        if (idx !== -1) {
            currentSlideIndex = idx;
            updateCarouselUI();
        }
    }


    let sizeSet = new Set();
    variantList.forEach(v => sizeSet.add(v.size));
    sizeSet.forEach(s => {
        let opt = document.createElement('option');
        opt.value = s;
        opt.innerText = s;
        selSize.appendChild(opt);
    });


    function refreshColor() {
        selColor.innerHTML = `<option value="">-- Please select color --</option>`;
        let chosenSize = selSize.value;
        if (!chosenSize) return;

        let filterBySize = variantList.filter(item => item.size === chosenSize);
        let colorSet = new Set();
        filterBySize.forEach(v => colorSet.add(v.color));
        colorSet.forEach(c => {
            let opt = document.createElement('option');
            opt.value = c;
            opt.innerText = c;
            selColor.appendChild(opt);
        });
        selColor.value = '';
        updateDisplay();
    }


    function updateDisplay() {
        let s = selSize.value;
        let c = selColor.value;
        if (!s || !c) {
            dispPrice.innerText = '-';
            dispStock.innerText = '-';
            variantInput.value = '';

            btnAddCart.disabled = true;
            btnBuyNow.disabled = true;
            return;
        }

        let target = variantList.find(item => item.size === s && item.color === c);

        if (target) {
            dispPrice.innerText = `RM ${parseFloat(target.price).toFixed(2)}`;
            dispStock.innerText = target.stock;

            variantInput.value = target.variant_id;

            btnAddCart.disabled = false;
            btnBuyNow.disabled = false;

            jumpCarouselByVariant(s, c);
        } else {
            dispPrice.innerText = '-';
            dispStock.innerText = '-';
            variantInput.value = '';
            btnAddCart.disabled = true;
            btnBuyNow.disabled = true;
        }
    }

    selSize.addEventListener('change', refreshColor);
    selColor.addEventListener('change', updateDisplay);


    buildCarousel();
</script>

<?php include '../app/_foot.php'; ?>