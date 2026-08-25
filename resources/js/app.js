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
    const customer = root.querySelector('[data-pos-customer]');
    const discountType = root.querySelector('[data-pos-discount-type]');
    const discountValue = root.querySelector('[data-pos-discount-value]');
    const paymentMethod = root.querySelector('[data-pos-payment-method]');
    const paymentAmount = root.querySelector('[data-pos-payment-amount]');
    const checkoutButton = root.querySelector('[data-pos-checkout]');
    const message = root.querySelector('[data-pos-message]');
    let checkoutTotal = 0;
    let isSubmitting = false;

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
                    <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-lg font-bold text-brand-700">${escapeHtml(product.name.charAt(0))}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">${product.tracks_inventory ? `${product.quantity} ${product.unit}` : product.type}</span>
                </span>
                <span class="mt-4">
                    <span class="block font-bold text-slate-900">${escapeHtml(product.name)}</span>
                    <span class="mt-1 flex items-center justify-between gap-2 text-sm text-slate-500">
                        <span>${escapeHtml(product.category)}</span>
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
                        <p class="font-bold text-slate-900">${escapeHtml(item.name)}</p>
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

        const lineSubtotals = items.map((item) => roundMoney(item.price * item.cartQuantity));
        const subtotal = roundMoney(lineSubtotals.reduce((sum, value) => sum + value, 0));
        const requestedDiscount = Number(discountValue?.value || 0);
        const discount = roundMoney(discountType?.value === 'percentage'
            ? subtotal * Math.min(requestedDiscount, 100) / 100
            : discountType?.value === 'fixed' ? Math.min(requestedDiscount, subtotal) : 0);
        let remainingDiscount = discount;
        const tax = roundMoney(items.reduce((sum, item, index) => {
            const lineDiscount = index === items.length - 1
                ? remainingDiscount
                : roundMoney(discount * lineSubtotals[index] / subtotal);
            remainingDiscount = roundMoney(remainingDiscount - lineDiscount);

            return sum + roundMoney((lineSubtotals[index] - lineDiscount) * item.tax_rate / 100);
        }, 0));
        const itemCount = items.reduce((sum, item) => sum + item.cartQuantity, 0);
        subtotalElement.textContent = currency.format(subtotal);
        taxElement.textContent = currency.format(tax);
        checkoutTotal = roundMoney(Math.max(0, subtotal - discount + tax));
        totalElement.textContent = currency.format(checkoutTotal);
        itemCountElement.textContent = itemCount;
        checkoutButton.disabled = items.length === 0 || isSubmitting;
        if (paymentAmount && document.activeElement !== paymentAmount) {
            paymentAmount.value = checkoutTotal.toFixed(2);
        }

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
    discountType?.addEventListener('change', renderCart);
    discountValue?.addEventListener('input', renderCart);
    clearButton.addEventListener('click', () => {
        cart.clear();
        renderCart();
    });
    checkoutButton?.addEventListener('click', checkout);

    async function checkout() {
        if (cart.size === 0 || isSubmitting) {
            return;
        }

        const selectedMethod = paymentMethod.selectedOptions[0];
        const enteredAmount = Number(paymentAmount.value || 0);
        const isCash = selectedMethod?.dataset.category === 'cash';
        const appliedAmount = Math.min(enteredAmount, checkoutTotal);
        const payments = appliedAmount > 0 ? [{
            payment_method_id: paymentMethod.value,
            amount: appliedAmount.toFixed(2),
            amount_tendered: isCash ? enteredAmount.toFixed(2) : null,
        }] : [];
        const payload = {
            shift_id: root.dataset.shiftId,
            customer_id: customer.value || null,
            items: [...cart.values()].map((item) => ({ product_id: item.id, quantity: String(item.cartQuantity) })),
            payments,
            discount_type: discountType.value,
            discount_value: String(discountValue.value || 0),
        };

        isSubmitting = true;
        renderCart();
        showMessage('جارٍ حفظ الفاتورة...', 'pending');

        try {
            const response = await fetch(root.dataset.checkoutUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': root.dataset.csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const body = await response.json();
            if (! response.ok) {
                const validationMessage = body.errors ? Object.values(body.errors).flat()[0] : null;
                throw new Error(validationMessage || body.message || 'تعذر حفظ الفاتورة.');
            }

            [...cart.values()].forEach((item) => {
                const product = products.find((candidate) => candidate.id === item.id);
                if (product?.tracks_inventory) {
                    product.quantity -= item.cartQuantity;
                }
            });
            cart.clear();
            discountType.value = 'none';
            discountValue.value = '0';
            showMessage(`تم حفظ الفاتورة ${body.data.invoice_number} بنجاح.`, 'success');
            renderProducts();
        } catch (error) {
            showMessage(error.message, 'error');
        } finally {
            isSubmitting = false;
            renderCart();
        }
    }

    function showMessage(text, type) {
        message.textContent = text;
        message.className = `mt-4 rounded-xl px-3 py-2 text-sm font-bold ${type === 'success' ? 'bg-emerald-50 text-emerald-800' : type === 'error' ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-800'}`;
    }

    renderProducts();
    renderCart();
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function roundMoney(value) {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}
