document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            sidebar?.classList.toggle('translate-x-full');
            backdrop?.classList.toggle('hidden');
        });
    });

    backdrop?.addEventListener('click', () => {
        sidebar?.classList.add('translate-x-full');
        backdrop.classList.add('hidden');
    });

    initializePos();
});

function initializePos() {
    const root = document.querySelector('[data-pos-root]');

    if (! root) {
        return;
    }

    const products = JSON.parse(root.dataset.products || '[]');
    const cart = new Map();
    const search = root.querySelector('[data-pos-search]');
    const category = root.querySelector('[data-pos-category]');
    const productGrid = root.querySelector('[data-product-grid]');
    const cartLines = root.querySelector('[data-cart-lines]');
    const emptyCart = root.querySelector('[data-empty-cart]');
    const subtotalElement = root.querySelector('[data-cart-subtotal]');
    const taxElement = root.querySelector('[data-cart-tax]');
    const totalElement = root.querySelector('[data-cart-total]');
    const itemCountElement = root.querySelector('[data-cart-count]');
    const clearButton = root.querySelector('[data-cart-clear]');

    const currency = new Intl.NumberFormat('ar', { style: 'currency', currency: 'USD' });

    function visibleProducts() {
        const term = search.value.trim().toLowerCase();
        const categoryId = category.value;

        return products.filter((product) => {
            const matchesTerm = ! term || [product.name, product.sku, product.barcode]
                .filter(Boolean)
                .some((value) => value.toLowerCase().includes(term));
            const matchesCategory = ! categoryId || product.category_id === categoryId;

            return matchesTerm && matchesCategory;
        });
    }

    function renderProducts() {
        const filtered = visibleProducts();
        productGrid.innerHTML = filtered.map((product) => `
            <button type="button" data-add-product="${product.id}" class="group flex min-h-36 flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 text-right shadow-sm hover:-translate-y-0.5 hover:border-brand-500 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50" ${product.tracks_inventory && product.quantity <= 0 ? 'disabled' : ''}>
                <span class="flex items-start justify-between gap-3">
                    <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-lg font-bold text-brand-700">${product.name.charAt(0)}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">${product.tracks_inventory ? `${product.quantity} ${product.unit}` : product.type}</span>
                </span>
                <span class="mt-4">
                    <span class="block font-bold text-slate-900">${product.name}</span>
                    <span class="mt-1 flex items-center justify-between gap-2 text-sm text-slate-500">
                        <span>${product.category}</span>
                        <span class="font-bold text-brand-700">${currency.format(product.price)}</span>
                    </span>
                </span>
            </button>
        `).join('') || '<div class="col-span-full rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">لا توجد منتجات مطابقة.</div>';

        productGrid.querySelectorAll('[data-add-product]').forEach((button) => {
            button.addEventListener('click', () => addProduct(button.dataset.addProduct));
        });
    }

    function addProduct(productId) {
        const product = products.find((item) => item.id === productId);
        const existing = cart.get(productId);
        const nextQuantity = (existing?.cartQuantity || 0) + 1;

        if (! product || (product.tracks_inventory && nextQuantity > product.quantity)) {
            return;
        }

        cart.set(productId, { ...product, cartQuantity: nextQuantity });
        renderCart();
    }

    function changeQuantity(productId, delta) {
        const item = cart.get(productId);

        if (! item) {
            return;
        }

        const nextQuantity = item.cartQuantity + delta;

        if (nextQuantity <= 0) {
            cart.delete(productId);
        } else if (! item.tracks_inventory || nextQuantity <= item.quantity) {
            cart.set(productId, { ...item, cartQuantity: nextQuantity });
        }

        renderCart();
    }

    function renderCart() {
        const items = [...cart.values()];
        emptyCart.classList.toggle('hidden', items.length > 0);
        cartLines.innerHTML = items.map((item) => `
            <div class="rounded-xl border border-slate-200 p-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-bold text-slate-900">${item.name}</p>
                        <p class="mt-1 text-sm text-slate-500">${currency.format(item.price)} للوحدة</p>
                    </div>
                    <button type="button" data-remove-product="${item.id}" class="text-sm text-red-600 hover:text-red-700">حذف</button>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3">
                    <div class="flex items-center rounded-lg border border-slate-200">
                        <button type="button" data-quantity="${item.id}" data-delta="1" class="px-3 py-1.5 font-bold text-brand-700">+</button>
                        <span class="min-w-8 text-center text-sm font-bold">${item.cartQuantity}</span>
                        <button type="button" data-quantity="${item.id}" data-delta="-1" class="px-3 py-1.5 font-bold text-slate-600">−</button>
                    </div>
                    <span class="font-bold">${currency.format(item.price * item.cartQuantity)}</span>
                </div>
            </div>
        `).join('');

        const subtotal = items.reduce((sum, item) => sum + (item.price * item.cartQuantity), 0);
        const tax = items.reduce((sum, item) => sum + (item.price * item.cartQuantity * item.tax_rate / 100), 0);
        const itemCount = items.reduce((sum, item) => sum + item.cartQuantity, 0);
        subtotalElement.textContent = currency.format(subtotal);
        taxElement.textContent = currency.format(tax);
        totalElement.textContent = currency.format(subtotal + tax);
        itemCountElement.textContent = itemCount;

        cartLines.querySelectorAll('[data-quantity]').forEach((button) => {
            button.addEventListener('click', () => changeQuantity(button.dataset.quantity, Number(button.dataset.delta)));
        });

        cartLines.querySelectorAll('[data-remove-product]').forEach((button) => {
            button.addEventListener('click', () => {
                cart.delete(button.dataset.removeProduct);
                renderCart();
            });
        });
    }

    search.addEventListener('input', renderProducts);
    category.addEventListener('change', renderProducts);
    clearButton.addEventListener('click', () => {
        cart.clear();
        renderCart();
    });
    renderProducts();
    renderCart();
}
