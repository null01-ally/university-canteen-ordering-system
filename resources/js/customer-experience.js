export const initializeCustomerExperience = (cart) => {
    const money = (cents) => 'Php ' + (cents / 100).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
    let categoryObserver;
    let currentDish;
    let lastStage;
    let basketResizeFrame;
    const fitBasketToViewport = () => {
        const menu = document.querySelector('main.customer-menu');
        if (!menu || !matchMedia('(min-width: 1024px)').matches) return;
        const paddingTop = parseFloat(getComputedStyle(menu).paddingTop) || 0;
        const top = Math.max(24, menu.getBoundingClientRect().top + paddingTop);
        menu.style.setProperty('--basket-viewport-height', Math.max(0, innerHeight - top - 24) + 'px');
    };
    const scheduleBasketResize = () => {
        cancelAnimationFrame(basketResizeFrame);
        basketResizeFrame = requestAnimationFrame(fitBasketToViewport);
    };
    window.addEventListener('scroll', scheduleBasketResize, {passive: true});
    window.addEventListener('resize', scheduleBasketResize);
    let cancelCurrentToast = () => {};
    const toastStack = document.createElement('aside');
    toastStack.className = 'toast-stack';
    toastStack.setAttribute('aria-label', 'Updates');
    toastStack.setAttribute('aria-live', 'polite');
    toastStack.setAttribute('aria-relevant', 'additions');
    document.body.append(toastStack);

    const toast = (message, tone = 'success') => {
        if (!document.body.classList.contains('customer-shell')) return;
        cancelCurrentToast();
        const item = document.createElement('div');
        item.className = 'customer-toast';
        item.dataset.tone = tone;
        if (tone === 'error') item.setAttribute('role', 'alert');
        const icon = document.createElement('img');
        icon.src = (document.body.dataset.iconBase ?? '/images/icons/') + (tone === 'error' ? 'toast-error.png' : 'toast-success.png');
        icon.alt = '';
        icon.setAttribute('aria-hidden', 'true');
        icon.className = 'ui-icon object-contain';
        icon.width = 22;
        icon.height = 22;
        const label = document.createElement('span');
        label.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '×';
        close.addEventListener('click', () => { clearTimeout(timeout); item.remove(); });
        item.append(icon, label, close);
        toastStack.replaceChildren(item);
        let timeout;
        let fadeTimeout;
        cancelCurrentToast = () => { clearTimeout(timeout); clearTimeout(fadeTimeout); };
        const schedule = () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                item.classList.add('is-leaving');
                fadeTimeout = setTimeout(() => item.remove(), 180);
            }, tone === 'error' ? 9000 : 2000);
        };
        item.addEventListener('pointerenter', () => clearTimeout(timeout));
        item.addEventListener('pointerleave', schedule);
        item.addEventListener('focusin', () => clearTimeout(timeout));
        item.addEventListener('focusout', schedule);
        schedule();
    };

    const setCategory = (id) => {
        document.querySelectorAll('[data-category-target]').forEach((link) => {
            const active = link.dataset.categoryTarget === id;
            link.classList.toggle('menu-pill-active', active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
    };

    const hydrateImages = () => {
        document.querySelectorAll('[data-image-shell] img').forEach((image) => {
            if (image.dataset.loadingBound) return;
            image.dataset.loadingBound = 'true';
            const ready = () => image.closest('[data-image-shell]')?.classList.remove('is-loading');
            image.addEventListener('load', ready);
            image.addEventListener('error', () => {
                ready();
                image.hidden = !image.dataset.imageFallback;
                image.closest('[data-image-shell]')?.classList.add('is-placeholder');
            });
            if (image.complete && image.naturalWidth) ready();
        });
    };

    const hydrate = () => {
        fitBasketToViewport();
        hydrateImages();
        categoryObserver?.disconnect();
        const sections = [...document.querySelectorAll('[data-category-section]')];
        if (sections.length) {
            categoryObserver = new IntersectionObserver((entries) => {
                const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
                if (visible.length) setCategory(visible[0].target.id);
            }, {rootMargin: '-85px 0px -55% 0px', threshold: 0});
            sections.forEach((section) => categoryObserver.observe(section));
        }
        document.querySelectorAll('[data-basket-toggle]').forEach((button) => {
            const collapsed = document.body.classList.contains('basket-collapsed');
            button.setAttribute('aria-expanded', String(!collapsed));
            button.setAttribute('aria-label', collapsed ? 'Expand basket' : 'Collapse basket');
        });
        const stage = document.querySelector('[data-order-stage]');
        if (stage) {
            const key = stage.dataset.orderReference + ':' + stage.dataset.orderStage;
            if (lastStage && lastStage !== key && lastStage.split(':')[0] === stage.dataset.orderReference) toast('Order status updated — ' + stage.dataset.orderLabel);
            lastStage = key;
        }
        const status = document.querySelector('#main > [role="status"]');
        if (status && !status.dataset.toasted) {
            status.dataset.toasted = 'true';
            toast(status.textContent.trim());
        }
        const error = document.querySelector('#main > [role="alert"]');
        if (error && !error.dataset.toasted) {
            error.dataset.toasted = 'true';
            toast(error.textContent.trim(), 'error');
        }
    };

    const updateDishTotal = () => {
        const form = document.getElementById('dish-order-form');
        if (!form || !currentDish) return;
        const input = form.querySelector('[name="quantity"]');
        const current = cart.getItem(currentDish.id)?.quantity ?? 0;
        const maximum = Math.min(currentDish.maximum, cart.getItem(currentDish.id)?.maximum ?? currentDish.maximum);
        const remaining = Math.max(0, maximum - current);
        input.max = Math.max(1, remaining);
        const quantity = Math.max(1, Math.min(Number(input.value) || 1, Number(input.max)));
        input.value = quantity;
        const button = form.querySelector('[data-dish-add]');
        button.disabled = remaining === 0;
        button.textContent = remaining ? 'Add to Order — ' + money(currentDish.price * quantity) : 'Currently unavailable';
        form.querySelector('[data-dish-step="-1"]').disabled = quantity <= 1;
        form.querySelector('[data-dish-step="1"]').disabled = quantity >= remaining;
        form.querySelector('[data-dish-availability]').textContent = remaining ? (current ? current + ' already in your basket.' : '') : 'This item is sold out or already at the available quantity.';
    };

    document.addEventListener('click', (event) => {
        const basketLink = event.target.closest('[data-active-cart-link]');
        const basket = document.querySelector('.customer-cart-sidebar');
        if (basketLink && basket && !event.defaultPrevented && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && matchMedia('(min-width: 1024px)').matches) {
            event.preventDefault();
            document.body.classList.remove('basket-collapsed');
            hydrate();
            basket.setAttribute('tabindex', '-1');
            basket.focus({preventScroll: true});
            basket.scrollIntoView({behavior: reducedMotion() ? 'auto' : 'smooth', block: 'nearest'});
        }
        const category = event.target.closest('[data-category-target]');
        if (category) {
            const target = document.getElementById(category.dataset.categoryTarget);
            if (target) {
                event.preventDefault();
                target.scrollIntoView({behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start'});
                setCategory(target.id);
                history.replaceState(null, '', '#' + target.id);
                const bar = category.closest('[data-menu-categories]');
                if (bar) bar.scrollTo({left: category.offsetLeft - 24, behavior: reducedMotion() ? 'auto' : 'smooth'});
            }
        }
        const trigger = event.target.closest('[data-open-dish]');
        if (trigger) {
            const card = trigger.closest('[data-dish]');
            const dialog = document.getElementById('dish-dialog');
            currentDish = JSON.parse(card.dataset.dish);
            const form = dialog.querySelector('form');
            form.dataset.cartItem = currentDish.id;
            form.dataset.cartUrl = currentDish.url;
            form.dataset.cartPrice = currentDish.price;
            form.dataset.cartMax = currentDish.maximum;
            form.action = currentDish.url;
            dialog.querySelector('[data-dish-title]').textContent = currentDish.name;
            dialog.querySelector('[data-dish-description]').textContent = currentDish.description;
            dialog.querySelector('[data-dish-price]').textContent = money(currentDish.price);
            form.querySelector('[name="instructions"]').value = cart.getItem(currentDish.id)?.instructions ?? '';
            form.querySelector('[name="quantity"]').value = 1;
            const image = dialog.querySelector('[data-dish-photo]');
            const placeholder = dialog.querySelector('[data-dish-placeholder]');
            image.hidden = !currentDish.image;
            placeholder.hidden = Boolean(currentDish.image);
            if (currentDish.image) {
                image.closest('[data-image-shell]').classList.add('is-loading');
                delete image.dataset.fallbackApplied;
                image.src = currentDish.image;
                image.alt = currentDish.name;
            } else {
                image.removeAttribute('src');
                image.closest('[data-image-shell]').classList.remove('is-loading');
            }
            updateDishTotal();
            dialog.showModal();
        }
        const step = event.target.closest('[data-dish-step]');
        if (step) {
            const input = document.getElementById('dish-quantity');
            input.value = Number(input.value) + Number(step.dataset.dishStep);
            updateDishTotal();
        }
        const close = event.target.closest('[data-close-customer-dialog]');
        if (close) close.closest('dialog').close();
        const receipt = event.target.closest('[data-open-receipt]');
        if (receipt) document.getElementById('receipt-drawer')?.showModal();
        const toggle = event.target.closest('[data-basket-toggle]');
        if (toggle) {
            document.body.classList.toggle('basket-collapsed');
            hydrate();
        }
        if (event.target instanceof HTMLDialogElement && event.target.matches('.customer-item-dialog, .customer-receipt-dialog')) {
            const box = event.target.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) event.target.close();
        }
    });
    document.addEventListener('input', (event) => {
        if (event.target.id === 'dish-quantity') updateDishTotal();
    });
    document.addEventListener('canteen:cart-queued', (event) => {
        if (event.detail.formId === 'dish-order-form') document.getElementById('dish-dialog')?.close();
    });
    document.addEventListener('canteen:cart-saved', (event) => {
        if (event.detail.message) toast(event.detail.message);
        hydrate();
    });
    document.addEventListener('canteen:render', hydrate);
    document.addEventListener('canteen:request-error', (event) => toast(event.detail.message, 'error'));
    hydrate();

    return {hydrate, toast};
};




