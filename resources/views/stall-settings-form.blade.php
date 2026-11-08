<form id="stall-settings" method="post" enctype="multipart/form-data" action="{{ $settingsAction }}">
    @csrf @method('PATCH')
    <div class="workspace-page-title">
        <div><h1 class="workspace-heading">Stall settings</h1><p>Keep your stall information and service hours up to date.</p></div>
        <div class="flex flex-wrap gap-4"><a class="button-secondary" href="{{ $settingsBackUrl }}">Discard changes</a><button class="button-primary">Save changes</button></div>
    </div>
    <div class="workspace-settings-grid">
        <div class="space-y-6">
            <section class="workspace-card">
                <h2 class="workspace-section-title">Stall information</h2>
                @php
                    $stallImageUrl = $stall->imageUrl();
                @endphp
                <div class="mt-5 grid items-start gap-4 sm:grid-cols-[180px_minmax(0,1fr)]">
                    <div class="overflow-hidden rounded-xl border border-maroon-100 bg-canteen-cream">
                        <img data-stall-photo-preview data-original-src="{{ $stallImageUrl ?? '' }}" @if ($stallImageUrl) src="{{ $stallImageUrl }}" @endif alt="Stall image preview" @class(['aspect-[1.8] w-full object-cover', 'hidden' => ! $stallImageUrl])>
                        <div data-stall-photo-placeholder @class(['aspect-[1.8] items-center justify-center p-4 text-center text-xs text-stone-600', 'hidden' => $stallImageUrl, 'flex' => ! $stallImageUrl])>No stall image yet</div>
                    </div>
                    <div>
                        <label for="stall-photo" class="field-label">{{ $stallImageUrl ? 'Replace stall image' : 'Add stall image' }}</label>
                        <input id="stall-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" data-stall-photo class="mt-2 block w-full min-w-0 text-xs text-stone-600 file:mr-3 file:cursor-pointer file:rounded-full file:border-0 file:bg-maroon-50 file:px-4 file:py-2.5 file:font-semibold file:text-maroon-800" aria-describedby="stall-photo-help">
                        <p id="stall-photo-help" class="mt-2 text-xs text-stone-600">JPG, PNG, or WebP · Up to 2 MB and 6000 × 6000 pixels. Shown on customer stall cards. Select an image, then save your changes.</p>
                        @if ($stall->image_path)<label class="mt-3 flex items-center gap-2 text-xs text-maroon-800"><input type="checkbox" name="remove_photo" value="1" @checked(old('remove_photo')) class="accent-maroon-800">Remove uploaded image</label>@endif
                        @error('photo')<p class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <label class="field-label">Stall name<input class="field" name="name" value="{{ old('name', $stall->name) }}" maxlength="255" required></label>
                    <label class="field-label">Campus location<input class="field" name="location" value="{{ old('location', $stall->location) }}" maxlength="255"></label>
                    <label class="field-label sm:col-span-2">Description<textarea class="field" name="description" maxlength="1000" rows="1">{{ old('description', $stall->description) }}</textarea></label>
                </div>
                <p class="mt-4 text-xs text-stone-600">Shown on your customer-facing stall page.</p>
            </section>
            <section class="workspace-card">
                <h2 class="workspace-section-title">Operating hours</h2><p class="mt-1 text-xs text-stone-600">Campus local time · Asia/Manila</p>
                <div class="mt-6 space-y-5">
                    @foreach (['weekdays' => [1, 'Monday – Friday'], 'saturday' => [6, 'Saturday'], 'sunday' => [7, 'Sunday']] as $group => [$day, $label])
                        @php
                            $defaults = ['enabled' => $stall->weekly_hours === null, 'opens' => substr($stall->opens_at ?? '07:00', 0, 5), 'closes' => substr($stall->closes_at ?? '17:00', 0, 5)];
                            $savedSlot = old('weekly_groups.'.$group, $stall->weekly_hours[$day] ?? []);
                            $slot = (is_array($savedSlot) ? $savedSlot : []) + $defaults;
                        @endphp
                        <fieldset class="grid items-center gap-3 sm:grid-cols-[1fr_1fr_1fr]">
                            <legend class="sr-only">{{ $label }} hours</legend>
                            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="weekly_groups[{{ $group }}][enabled]" value="0"><input type="checkbox" name="weekly_groups[{{ $group }}][enabled]" value="1" @checked($slot['enabled']) class="accent-maroon-800">{{ $label }}</label>
                            <input type="time" class="field mt-0" aria-label="{{ $label }} opening time" name="weekly_groups[{{ $group }}][opens]" value="{{ $slot['opens'] }}" required>
                            <input type="time" class="field mt-0" aria-label="{{ $label }} closing time" name="weekly_groups[{{ $group }}][closes]" value="{{ $slot['closes'] }}" required>
                        </fieldset>
                    @endforeach
                </div>
                <p class="mt-5 text-xs text-stone-600">Unchecked days are closed. Enable each day you accept orders.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach (['break_starts_at' => 'Prep break starts', 'break_ends_at' => 'Prep break ends'] as $field => $label)
                        <label class="field-label">{{ $label }}<input class="field" type="time" name="{{ $field }}" value="{{ old($field, substr((string) $stall->{$field}, 0, 5)) }}"></label>
                    @endforeach
                </div>
                <input type="hidden" name="opens_at" value="00:00"><input type="hidden" name="closes_at" value="23:59">
            </section>
        </div>
        <div class="space-y-6">
            <section class="workspace-card">
                <h2 class="workspace-section-title">Service availability</h2>
                <div class="mt-7 space-y-8">
                    @foreach (['is_active' => ['Accept new orders', 'Stall is visible and open to customers.'], 'delivery_enabled' => ['Campus delivery', 'Allow delivery within campus grounds.'], 'pickup_enabled' => ['Pickup at stall', 'Customers can collect at your counter.']] as $field => [$label, $hint])
                        <label class="flex cursor-pointer items-start justify-between gap-4">
                            <span><strong class="block text-sm font-semibold">{{ $label }}</strong><span class="mt-1 block text-xs text-stone-600">{{ $hint }}</span></span>
                            <input type="hidden" name="{{ $field }}" value="0"><input class="peer sr-only" type="checkbox" role="switch" name="{{ $field }}" value="1" @checked(old($field, $stall->{$field}))>
                            <span class="design-switch peer-checked:justify-end peer-checked:bg-maroon-800 peer-focus-visible:outline-2 peer-focus-visible:outline-maroon-800" aria-hidden="true"><span></span></span>
                        </label>
                    @endforeach
                </div>
            </section>
            <section class="workspace-card">
                <h2 class="workspace-section-title">Local order settings</h2>
                <div class="mt-5 space-y-4">
                    <label class="field-label">Preparation time (minutes)<input class="field" type="number" name="preparation_minutes" min="1" max="180" value="{{ old('preparation_minutes', $stall->preparation_minutes) }}" required></label>
                    <label class="field-label">Delivery fee (PHP)<input class="field" type="number" name="delivery_fee" min="0" step="0.01" value="{{ old('delivery_fee', $stall->delivery_fee) }}" required></label>
                    <label class="field-label">Store phone<input class="field" type="tel" name="phone" value="{{ old('phone', $stall->phone) }}" maxlength="32"></label>
                </div>
            </section>
            <p class="px-4 text-xs text-stone-600">Changes apply to {{ $stall->name }} only. Other stalls are not affected.</p>
        </div>
    </div>
</form>
