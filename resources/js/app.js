import {createCartInteractions} from './cart';
import {initializeCustomerExperience} from './customer-experience';

const syncPasswordConfirmation = () => {
    const confirmation = document.querySelector('[data-password-confirmation]');
    if (confirmation) confirmation.hidden = !document.querySelector('.customer-profile [name=password]')?.value;
};
syncPasswordConfirmation();

let stallPreviewUrl;
document.addEventListener('change', (event) => {
    if (event.target.matches('[data-auto-submit]')) event.target.form.requestSubmit();
    if (event.target.matches('[data-stall-photo], [name="remove_photo"]')) {
        const form = event.target.form;
        const input = form?.querySelector('[data-stall-photo]');
        const preview = form?.querySelector('[data-stall-photo-preview]');
        const placeholder = form?.querySelector('[data-stall-photo-placeholder]');
        if (!input || !preview || !placeholder) return;
        const remove = form.querySelector('[name="remove_photo"]');
        if (event.target === input && input.files.length && remove) remove.checked = false;
        if (event.target === remove && remove.checked) input.value = '';
        const file = input.files[0];
        if (file && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return;
        if (stallPreviewUrl) URL.revokeObjectURL(stallPreviewUrl);
        stallPreviewUrl = file ? URL.createObjectURL(file) : null;
        const source = remove?.checked ? '' : (stallPreviewUrl || preview.dataset.originalSrc);
        if (source) preview.src = source;
        else preview.removeAttribute('src');
        preview.classList.toggle('hidden', !source);
        placeholder.classList.toggle('hidden', Boolean(source));
        placeholder.classList.toggle('flex', !source);
    }
});

let mutationPending = false;
let cartInteractions;
let uiRevision = 0;
let searchRequest;
let searchTimer;
const checkoutDraft = {};
const visibleField = (name) => [...document.querySelectorAll('[name="' + name + '"]')].find((field) => field.getClientRects().length > 0);

const openRequestedDialog = () => {
    const dialog = document.querySelector('[data-open-edit-dialog]');
    if (dialog instanceof HTMLDialogElement && !dialog.open) dialog.showModal();
};

const showRequestError = (message) => {
    const main = document.getElementById('main');
    let alert = main?.querySelector('[data-request-error]');
    if (!alert && main) {
        alert = document.createElement('p');
        alert.dataset.requestError = '';
        alert.setAttribute('role', 'alert');
        alert.className = 'mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-950';
        main.prepend(alert);
    }
    if (alert) alert.textContent = message;
    document.dispatchEvent(new CustomEvent('canteen:request-error', {detail: {message}}));
};

const readPage = async (response) => {
    if (new URL(response.url).pathname === '/login') {
        window.location.assign(response.url);
        return null;
    }
    if (!response.ok) throw new Error('The request could not be completed. Refresh the page to check its current state.');

    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    if (!page.getElementById('main')) throw new Error('The page could not be updated. Refresh to check its current state.');
    return page;
};

const replaceMain = (page) => {
    const main = document.getElementById('main');
    const next = page.getElementById('main');
    if (main.innerHTML !== next.innerHTML) main.innerHTML = next.innerHTML;
    const header = document.querySelector('[data-customer-header]');
    const nextHeader = page.querySelector('[data-customer-header]');
    if (header && nextHeader && header.innerHTML !== nextHeader.innerHTML && !header.querySelector('details[open]')) header.innerHTML = nextHeader.innerHTML;
    document.title = page.title;
    openRequestedDialog();
    syncPasswordConfirmation();
    cartInteractions?.resetFromPage();
    document.dispatchEvent(new Event('canteen:render'));
};

cartInteractions = createCartInteractions({
    isMutationPending: () => mutationPending,
    onStart: () => {
        mutationPending = true;
        ++uiRevision;
        searchRequest?.abort();
        document.querySelector('[data-menu-results]')?.removeAttribute('aria-busy');
        clearTimeout(searchTimer);
    },
    onFinish: () => {
        mutationPending = false;
        const input = visibleField('search');
        if (input?.form?.matches('[data-live-menu-search]') && input.value !== (new URL(window.location.href).searchParams.get('search') ?? '')) {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => searchMenu(input.form), 0);
        }
    },
    replacePage: replaceMain,
    readPage,
    onError: showRequestError,
});
initializeCustomerExperience(cartInteractions);

const printRequestedReport = () => {
    if (document.querySelector('[data-print-report-page]')) window.print();
};
if (document.readyState === 'complete') {
    printRequestedReport();
} else {
    window.addEventListener('load', printRequestedReport, {once: true});
}

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-print-report]')) window.print();
    const trigger = event.target.closest('[data-edit-dialog]');
    if (trigger) {
        const dialog = document.getElementById(trigger.dataset.editDialog);
        if (dialog instanceof HTMLDialogElement) {
            event.preventDefault();
            dialog.showModal();
        }
    }

    const close = event.target.closest('[data-close-edit-dialog]');
    if (close) close.closest('dialog').close();

    const passwordToggle = event.target.closest('[data-password-toggle]');
    if (passwordToggle) {
        const input = document.getElementById(passwordToggle.getAttribute('aria-controls'));
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', String(visible));
        passwordToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    }

});

openRequestedDialog();

const searchMenu = async (form) => {
    if (mutationPending) return;
    searchRequest?.abort();
    const controller = new AbortController();
    searchRequest = controller;
    const revision = ++uiRevision;
    const url = new URL(form.action);
    url.search = new URLSearchParams(new FormData(form)).toString();
    const results = document.querySelector('[data-menu-results]');
    results?.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(url, {signal: controller.signal, cache: 'no-store'});
        const page = await readPage(response);
        if (!page || revision !== uiRevision) return;
        for (const selector of ['[data-menu-results]', '[data-menu-categories]']) {
            const current = document.querySelector(selector);
            const next = page.querySelector(selector);
            if (current && next) current.replaceWith(next);
        }
        history.replaceState(null, '', response.url);
        document.dispatchEvent(new Event('canteen:render'));
        const category = document.querySelector('[data-menu-categories] [aria-current="page"]');
        if (category) {
            const categoryId = new URL(category.href).searchParams.get('category');
            const field = form.querySelector('[name="category"]');
            if (field) field.value = categoryId ?? '';
        }
    } catch (error) {
        if (error.name !== 'AbortError') showRequestError('Search is unavailable. Try again or refresh the menu.');
    } finally {
        if (revision === uiRevision) document.querySelector('[data-menu-results]')?.removeAttribute('aria-busy');
    }
};

document.addEventListener('input', (event) => {
    if (event.target.matches('.customer-profile [name=password]')) syncPasswordConfirmation();
    const form = event.target.closest('[data-live-menu-search]');
    if (!form) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => searchMenu(form), 300);
});

document.addEventListener('submit', async (event) => {
    if (event.defaultPrevented) return;
    const form = event.target;
    if (form.matches('[data-live-menu-search]')) {
        event.preventDefault();
        clearTimeout(searchTimer);
        await searchMenu(form);
        return;
    }
    if (!form.matches('[data-async-form]')) return;
    event.preventDefault();
    if (mutationPending) {
        const saved = await cartInteractions.whenIdle();
        if (!saved || mutationPending) return;
    }
    mutationPending = true;
    ++uiRevision;
    searchRequest?.abort();
    document.querySelector('[data-menu-results]')?.removeAttribute('aria-busy');
    clearTimeout(searchTimer);
    const data = new FormData(form);
    if (event.submitter?.name) data.append(event.submitter.name, event.submitter.value);
    const switchingAtCheckout = data.get('return_to') === 'checkout';
    if (switchingAtCheckout) {
        for (const name of ['contact_name', 'contact_phone', 'delivery_location', 'notes']) {
            const field = visibleField(name);
            if (field) checkoutDraft[name] = field.value;
        }
    }
    const button = event.submitter;
    if (button) button.disabled = true;
    document.getElementById('main')?.setAttribute('aria-busy', 'true');

    try {
        let response = await fetch(form.action, {method: form.getAttribute('method') || 'post', body: data, headers: {'Accept': 'text/html'}, cache: 'no-store'});
        let page = await readPage(response);
        if (!page) return;
        if (form.hasAttribute('data-refresh-current')) {
            const validation = page.querySelector('#main > [role="alert"]');
            if (validation) {
                showRequestError(validation.textContent.trim());
                return;
            }
            response = await fetch(window.location.href, {cache: 'no-store'});
            page = await readPage(response);
            if (!page) return;
        }
        replaceMain(page);
        history.replaceState(null, '', response.url);
        if (switchingAtCheckout) {
            for (const [name, value] of Object.entries(checkoutDraft)) {
                const field = visibleField(name);
                if (field) field.value = value;
            }
        }
    } catch {
        showRequestError('The update could not be confirmed. Refresh to check the current state before trying again.');
    } finally {
        mutationPending = false;
        if (button?.isConnected) button.disabled = false;
        document.getElementById('main')?.removeAttribute('aria-busy');
    }
});

const refreshCustomerPage = async () => {
    try {
        const main = document.querySelector('main[data-live-page="true"]');
        const editing = main?.querySelector('dialog[open], details[open]');
        const focused = main?.contains(document.activeElement) && document.activeElement.matches('input, textarea, select, button, a, summary');
        if (main && !mutationPending && !document.hidden && !editing && !focused) {
            const revision = uiRevision;
            const response = await fetch(window.location.href, {cache: 'no-store', headers: {'X-Canteen-Poll': '1'}, signal: AbortSignal.timeout(6000)});
            const page = await readPage(response);
            const nowFocused = main.contains(document.activeElement) && document.activeElement.matches('input, textarea, select, button, a, summary');
            if (page && revision === uiRevision && !mutationPending && !nowFocused && !main.querySelector('dialog[open], details[open]')) replaceMain(page);
        }
    } catch {
        // A temporary polling failure leaves the current page usable.
    } finally {
        setTimeout(refreshCustomerPage, 8000);
    }
};

if (document.querySelector('main[data-live-page="true"]')) setTimeout(refreshCustomerPage, 8000);

const queue = document.querySelector('[data-live-queue]');

if (queue) {
    const indicator = document.querySelector('[data-queue-indicator]');
    const soundButton = document.querySelector('[data-queue-sound]');
    const fragment = new URL(window.location.href);
    fragment.searchParams.set('fragment', '1');
    const newestOrder = () => BigInt(queue.querySelector('[data-newest-order-id]')?.dataset.newestOrderId ?? '0');
    let latest = newestOrder();
    let audio;
    let soundEnabled = false;

    if (soundButton && typeof AudioContext !== 'undefined') {
        soundButton.hidden = false;
        soundButton.addEventListener('click', async () => {
            if (!soundEnabled) {
                try {
                    audio ??= new AudioContext();
                    await audio.resume();
                } catch {
                    soundButton.hidden = true;
                    return;
                }
            }
            soundEnabled = !soundEnabled;
            soundButton.setAttribute('aria-pressed', String(soundEnabled));
            soundButton.textContent = soundEnabled ? 'Mute order sound' : 'Enable order sound';
        });
    }

    const playOrderSound = () => {
        if (!soundEnabled || audio.state !== 'running') return;

        const tone = audio.createOscillator();
        const volume = audio.createGain();
        tone.frequency.value = 740;
        volume.gain.setValueAtTime(0.35, audio.currentTime);
        volume.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + 0.25);
        tone.connect(volume).connect(audio.destination);
        tone.start();
        tone.stop(audio.currentTime + 0.25);
    };

    const refresh = async () => {
        try {
            if (!document.hidden && !queue.querySelector('[data-kds-order][aria-busy]')) {
                const revision = queue.dataset.revision ?? '0';
                const response = await fetch(fragment, { cache: 'no-store', headers: {'X-Canteen-Poll': '1'}, signal: AbortSignal.timeout(5000) });
                if (response.redirected && new URL(response.url).pathname === '/login') { window.location.assign(response.url); return; }
                if (!response.ok || response.redirected) {
                    throw new Error('Queue refresh unavailable');
                }

                const next = await response.text();
                if (revision === (queue.dataset.revision ?? '0') && !queue.querySelector('[data-kds-order][aria-busy]') && queue.dataset.lastFragment !== next) {
                    queue.innerHTML = next;
                    queue.dataset.lastFragment = next;
                    const newest = newestOrder();
                    if (newest > latest) {
                        latest = newest;
                        playOrderSound();
                    }
                }
                indicator.textContent = 'Updates every 10 seconds';
            }
        } catch {
            indicator.textContent = 'Reconnect or refresh this page';
        } finally {
            setTimeout(refresh, 10000);
        }
    };


    setTimeout(refresh, 10000);
}









// Kitchen controls save independently, keeping other cards responsive.
document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!form.matches('[data-kds-toggle], [data-kds-order]')) return;
    event.preventDefault();
    const button = event.submitter;
    if (!button || button.disabled) return;
    const feedback = form.querySelector('[role="status"]');
    const data = new FormData(form);
    data.set(button.name, button.value);
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    if (feedback) feedback.textContent = '';
    try {
        const isToggle = form.hasAttribute('data-kds-toggle');
        if (!isToggle) { const q = document.querySelector('[data-live-queue]'); q.dataset.revision = String(Number(q.dataset.revision ?? 0) + 1); }
        const response = await fetch(form.action, {method: 'POST', body: data, headers: {Accept: isToggle ? 'application/json' : 'text/html'}, cache: 'no-store'});
        if (isToggle) {
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors ?? {}).flat()[0] ?? result.message ?? 'Unable to save. Try again.');
            const checked = Boolean(result.state[button.name]);
            button.setAttribute('aria-checked', String(checked));
            button.value = checked ? '0' : '1';
            button.querySelector('[data-toggle-label]').textContent = checked ? button.dataset.onLabel : button.dataset.offLabel;
            document.querySelectorAll(`[data-kds-toggle] button[name="${button.name}"]`).forEach(other => {
                if (other.form.action !== form.action) return;
                other.setAttribute('aria-checked', String(checked)); other.value = checked ? '0' : '1';
                other.querySelector('[data-toggle-label]').textContent = checked ? other.dataset.onLabel : other.dataset.offLabel;
            });
        } else {
            if (!response.ok || (response.redirected && new URL(response.url).pathname === '/login')) throw new Error('Unable to update. Refresh and sign in again.');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const error = page.querySelector('#main > [role="alert"]');
            if (error) throw new Error(error.textContent.trim());
            const next = page.querySelector('[data-live-queue]');
            if (!next) throw new Error('Refresh to confirm the order status.');
            document.querySelector('[data-live-queue]').innerHTML = next.innerHTML;
        }
        if (feedback?.isConnected) feedback.textContent = 'Saved';
    } catch (error) {
        if (feedback) feedback.textContent = error.message || 'Connection lost. Refresh to confirm before retrying.';
    } finally {
        if (button.isConnected) button.disabled = false;
        form.removeAttribute('aria-busy');
    }
});

const kitchenQueue = document.querySelector('[data-live-queue]');
if (kitchenQueue) {
    kitchenQueue.dataset.selectedStage = kitchenQueue.querySelector('[data-queue-tab][aria-selected="true"]')?.dataset.queueTab ?? 'pending';
    const selectQueueStage = (stage, focus = false) => {
        if (!kitchenQueue.querySelector(`[data-queue-tab="${stage}"]`)) return;
        kitchenQueue.dataset.selectedStage = stage;
        kitchenQueue.querySelectorAll('[data-queue-tab]').forEach(tab => {
            const selected = tab.dataset.queueTab === stage;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) { tab.focus(); tab.scrollIntoView({block: 'nearest', inline: 'nearest'}); }
        });
        kitchenQueue.querySelectorAll('[data-queue-panel]').forEach(panel => { panel.hidden = panel.dataset.queuePanel !== stage; });
    };
    kitchenQueue.addEventListener('click', event => {
        const tab = event.target.closest('[data-queue-tab]');
        if (!tab || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault(); selectQueueStage(tab.dataset.queueTab);
    });
    kitchenQueue.addEventListener('keydown', event => {
        const tab = event.target.closest('[data-queue-tab]');
        if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const stages = [...kitchenQueue.querySelectorAll('[data-queue-tab]')].map(tab => tab.dataset.queueTab);
        const index = stages.indexOf(tab.dataset.queueTab);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? stages.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + stages.length) % stages.length;
        selectQueueStage(stages[next], true);
    });
    new MutationObserver(() => selectQueueStage(kitchenQueue.dataset.selectedStage)).observe(kitchenQueue, {childList: true});
}



// Confirm customer cancellation before any submit handler sends the request.
document.addEventListener('submit', (event) => {
    if (!event.target.matches('[data-confirm-cancellation]')) return;
    if (!window.confirm('Cancel this order? This cannot be undone.')) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);

document.addEventListener('submit', (event) => {
    if (!event.target.matches('[data-confirm-item-removal]')) return;
    if (!window.confirm('Remove this item from the menu? Past orders will be preserved.')) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);



const applyMenuImageFallback = (image) => {
    if (!image.matches('img[data-image-fallback]') || image.dataset.fallbackApplied) return;
    image.dataset.fallbackApplied = 'true';
    image.src = image.dataset.imageFallback;
    image.hidden = false;
    image.closest('[data-image-shell]')?.classList.remove('is-loading');
};
document.addEventListener('error', (event) => {
    if (event.target instanceof HTMLImageElement) applyMenuImageFallback(event.target);
}, true);
document.querySelectorAll('img[data-image-fallback]').forEach(image => {
    if (image.complete && !image.naturalWidth && image.getAttribute('src')) applyMenuImageFallback(image);
});


document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-basket-checkout][aria-disabled="true"]')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
}, true);
