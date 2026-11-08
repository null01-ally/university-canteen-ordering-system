export const createCartInteractions = (callbacks) => {
    let items = new Map();
    let fee = 0;
    let context = 'menu';
    let dirty = new Map();
    let busy = false;
    let running = false;
    let timer;
    let waiters = [];
    let addingItems = false;
    const money = (cents) => 'Php ' + (cents / 100).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const render = () => {
        const count = [...items.values()].reduce((sum, item) => sum + item.quantity, 0);
        const subtotal = [...items.values()].reduce((sum, item) => sum + item.quantity * item.unit_cents, 0);
        const text = (selector, value) => document.querySelectorAll(selector).forEach((node) => { node.textContent = value; });
        text('[data-header-cart-count]', count);
        text('[data-cart-count-items]', count + ' items');
        text('[data-cart-count-label]', count + (count === 1 ? ' item' : ' items') + ' in your order');
        text('[data-cart-subtotal-label]', 'Subtotal (' + count + ' items)');
        text('[data-cart-subtotal]', money(subtotal));
        text('[data-cart-total]', money(subtotal + fee));
        text('[data-cart-checkout-label]', 'Checkout · ' + money(subtotal + fee));
        document.querySelectorAll('[data-basket-checkout]').forEach((link) => {
            const allowed = count > 0 && link.dataset.serviceAvailable === 'true' && [...items.values()].every(item => item.quantity <= item.maximum);
            link.setAttribute('aria-disabled', String(!allowed));
            if (allowed) {
                link.href = link.dataset.checkoutUrl;
                link.removeAttribute('tabindex');
            } else {
                link.removeAttribute('href');
                link.tabIndex = -1;
            }
        });
        document.querySelectorAll('.experience-basket-bar').forEach((bar) => { bar.hidden = count === 0; });
        document.querySelectorAll('[data-cart-item]').forEach((node) => {
            const item = items.get(Number(node.dataset.cartItem));
            const quantity = item?.quantity ?? 0;
            const maximum = item?.maximum ?? Number(node.dataset.cartMax);
            node.querySelectorAll('[data-cart-quantity]').forEach((label) => { label.textContent = quantity; });
            node.querySelectorAll('[data-cart-line-total]').forEach((label) => { label.textContent = money(quantity * (item?.unit_cents ?? Number(node.dataset.cartPrice))); });
            node.querySelectorAll('button[name="step"], button.menu-add').forEach((button) => {
                if (button.name === 'step') button.disabled = button.value === '-1' ? quantity === 0 : quantity >= maximum;
                else if (button.classList.contains('menu-add')) button.disabled = quantity >= maximum;
            });
        });
        document.querySelectorAll('.customer-cart-sidebar, .customer-cart-lines, .menu-cart-dock').forEach((node) => {
            if (busy) node.setAttribute('aria-busy', 'true');
            else node.removeAttribute('aria-busy');
        });
    };

    const resetFromPage = () => {
        const state = document.querySelector('[data-cart-state]');
        if (!state) return;
        const data = JSON.parse(state.textContent);
        items = new Map(data.items.map((item) => [item.id, item]));
        fee = data.fee_cents;
        context = state.dataset.cartContext;
        render();
    };
    resetFromPage();

    const settle = (success) => {
        busy = false;
        callbacks.onFinish();
        render();
        waiters.splice(0).forEach((resolve) => resolve(success));
        if (success) document.dispatchEvent(new CustomEvent('canteen:cart-saved', {detail: {message: addingItems ? 'Item added to basket' : null}}));
        addingItems = false;
    };

    const flush = async () => {
        if (running) return;
        running = true;
        let latest;
        try {
            while (dirty.size) {
                const [id, metadata] = dirty.entries().next().value;
                dirty.delete(id);
                const target = items.get(id)?.quantity ?? 0;
                const instructions = items.get(id)?.instructions ?? null;
                const response = await fetch(metadata.url, {
                    method: 'PUT',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': metadata.token},
                    body: JSON.stringify({quantity: target, instructions, context}),
                    cache: 'no-store',
                    signal: AbortSignal.timeout(10000),
                });
                if (response.redirected && new URL(response.url).pathname === '/login') {
                    window.location.assign(response.url);
                    dirty.clear();
                    return;
                }
                if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Refresh the page and sign in again.');
                const payload = await response.json();
                if (!response.ok) throw new Error(Object.values(payload.errors ?? {}).flat().join(' ') || payload.message || 'Your cart could not be saved.');
                const desired = items.get(id)?.quantity ?? 0;
                if (desired !== target || (items.get(id)?.instructions ?? null) !== instructions) dirty.set(id, metadata);
                const confirmed = new Map(payload.cart.items.map((item) => [item.id, item]));
                for (const [itemId, item] of items) {
                    if (dirty.has(itemId)) {
                        const saved = confirmed.get(itemId);
                        if (saved) Object.assign(item, {unit_cents: saved.unit_cents, maximum: saved.maximum});
                        confirmed.set(itemId, item);
                    }
                }
                items = confirmed;
                fee = payload.cart.fee_cents;
                latest = payload;
                render();
            }
            if (latest?.page) {
                callbacks.replacePage(new DOMParser().parseFromString(latest.page, 'text/html'));
            } else if (latest?.sidebar) {
                const active = document.activeElement;
                const activeItem = active.closest('[data-cart-item]')?.dataset.cartItem;
                const activeStep = active.name === 'step' ? active.value : null;
                const sidebar = new DOMParser().parseFromString(latest.sidebar, 'text/html').querySelector('.customer-cart-sidebar');
                document.querySelector('.customer-cart-sidebar')?.replaceWith(sidebar);
                document.querySelector('[data-cart-state]').textContent = JSON.stringify(latest.cart);
                if (activeItem && activeStep) {
                    document.querySelector('.customer-cart-sidebar [data-cart-item="' + activeItem + '"] button[value="' + activeStep + '"]')?.focus({preventScroll: true});
                }
            }
            settle(true);
        } catch (error) {
            dirty.clear();
            try {
                const response = await fetch(window.location.href, {cache: 'no-store', signal: AbortSignal.timeout(10000)});
                const page = await callbacks.readPage(response);
                if (page) callbacks.replacePage(page);
            } catch {
                // An uncertain write is never retried automatically.
            }
            callbacks.onError(error.name === 'TimeoutError' || error.name === 'TypeError'
                ? 'The cart update could not be confirmed. Refresh to check your quantities before continuing.'
                : error.message);
            settle(false);
        } finally {
            running = false;
            if (busy) settle(false);
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form.matches('[data-cart-control]')) return;
        event.preventDefault();
        if (!busy && callbacks.isMutationPending()) return;
        const node = form.closest('[data-cart-item]');
        const id = Number(node.dataset.cartItem);
        const current = items.get(id) ?? {id, quantity: 0, unit_cents: Number(node.dataset.cartPrice), maximum: Number(node.dataset.cartMax)};
        const deleting = form.querySelector('[name="_method"]')?.value === 'DELETE';
        const step = event.submitter?.name === 'step' ? Number(event.submitter.value) : Number(form.querySelector('[name="quantity"]')?.value ?? 1);
        const quantity = deleting ? 0 : Math.max(0, Math.min(999, current.quantity + step));
        if (quantity === current.quantity) return;
        if (quantity > current.quantity && quantity > current.maximum) return;
        const instructions = form.querySelector('[name="instructions"]')?.value.trim() ?? current.instructions ?? null;
        items.set(id, {...current, quantity, instructions});
        addingItems ||= current.quantity === 0 && event.submitter?.name !== 'step' && !deleting;
        dirty.set(id, {url: node.dataset.cartUrl, token: form.querySelector('[name="_token"]').value});
        if (!busy) {
            busy = true;
            callbacks.onStart();
        }
        render();
        clearTimeout(timer);
        timer = setTimeout(flush, 70);
        document.dispatchEvent(new CustomEvent('canteen:cart-queued', {detail: {formId: form.id}}));
    });

    document.addEventListener('click', async (event) => {
        const link = event.target.closest('a[href]');
        if (!busy || !link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank') return;
        const url = new URL(link.href);
        if (url.origin !== window.location.origin || (url.pathname === window.location.pathname && url.hash)) return;
        event.preventDefault();
        const saved = await new Promise((resolve) => waiters.push(resolve));
        if (saved) window.location.assign(link.href);
    });

    return {resetFromPage, getItem: (id) => items.get(Number(id)), whenIdle: () => busy ? new Promise((resolve) => waiters.push(resolve)) : Promise.resolve(true)};
};


