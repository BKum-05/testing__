const sideCart =
    document.getElementById('sideCart');

const sideCartOverlay =
    document.getElementById('sideCartOverlay');

const closeSideCart =
    document.getElementById('closeSideCart');

document.addEventListener(
    'click',
    function (event) {

        const openButton =
            event.target.closest('.open-side-cart');

        if (openButton) {
            openSideCart();
        }
    }
);

function openSideCart() {

    if (!sideCart || !sideCartOverlay) {
        return;
    }

    sideCart.classList.add('open');

    sideCartOverlay.classList.add('show');

    document.body.classList.add(
        'side-cart-open'
    );
}


function closeCart() {

    if (!sideCart || !sideCartOverlay) {
        return;
    }

    sideCart.classList.remove('open');

    sideCartOverlay.classList.remove('show');

    document.body.classList.remove(
        'side-cart-open'
    );
}

if (closeSideCart) {

    closeSideCart.addEventListener(
        'click',
        closeCart
    );
}

if (sideCartOverlay) {

    sideCartOverlay.addEventListener(
        'click',
        closeCart
    );
}

// ESC closes cart
document.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Escape') {
            closeCart();
        }

    }
)

const params =
    new URLSearchParams(
        window.location.search
    );

if (params.get('cart') === 'open') {

    openSideCart();

    params.delete('cart');

    const newUrl =
        window.location.pathname +
        (
            params.toString()
                ? '?' + params.toString()
                : ''
        );

    window.history.replaceState(
        {},
        '',
        newUrl
    );
}