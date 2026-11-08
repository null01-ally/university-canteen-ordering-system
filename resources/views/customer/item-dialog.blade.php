<dialog id="dish-dialog" class="customer-item-dialog" aria-labelledby="dish-title">
    <button type="button" class="dish-close" data-close-customer-dialog aria-label="Close item details"><img src="{{ asset('images/icons/item-dialog-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain " decoding="async"></button>
    <div class="dish-photo food-photo" data-image-shell><img data-dish-photo data-menu-image data-image-fallback="{{ asset('images/icons/menu-placeholder.png') }}" width="720" height="480" loading="lazy" decoding="async" class="aspect-[1.5] w-full object-cover" alt="" hidden><div data-dish-placeholder class="dish-photo-placeholder"><img src="{{ asset('images/icons/menu-placeholder.png') }}" alt="ZPPSU Canteen" width="720" height="480" loading="lazy" class="aspect-[1.5] w-full object-cover"></div></div>
    <form id="dish-order-form" class="dish-body" method="post" data-cart-control data-async-form data-cart-item="" data-cart-url="" data-cart-price="0" data-cart-max="0">
        @csrf
        <div class="dish-heading"><h2 id="dish-title" data-dish-title></h2><strong data-dish-price></strong></div>
        <p data-dish-description class="dish-description"></p>
        <label for="dish-instructions" class="dish-label">Special instructions <span>Optional</span></label>
        <textarea id="dish-instructions" class="experience-input" name="instructions" rows="2" maxlength="500" placeholder="e.g., extra sauce, no ice"></textarea>
        <p class="dish-help">Instructions apply to this item in your basket.</p>
        <div class="dish-actions">
            <div class="dish-counter"><button type="button" data-dish-step="-1" aria-label="Decrease item quantity">−</button><input id="dish-quantity" name="quantity" type="number" value="1" min="1" max="999" aria-label="Item quantity" required><button type="button" data-dish-step="1" aria-label="Increase item quantity">+</button></div>
            <button type="submit" class="experience-button experience-button-maroon" data-dish-add>Add to Order</button>
        </div>
        <p class="dish-availability" data-dish-availability role="status"></p>
    </form>
</dialog>



