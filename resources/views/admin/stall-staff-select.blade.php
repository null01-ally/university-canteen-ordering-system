<label class="field-label">Assigned staff account *
    <select class="field" name="staff_id" required>
        <option value="">Select a staff account</option>
        @foreach ($staffAccounts as $staff)<option value="{{ $staff->id }}" @selected((string) (old('stall_dialog') === $dialogId ? old('staff_id') : $currentStall?->staff->first()?->id) === (string) $staff->id)>{{ $staff->name }} · {{ $staff->email }}{{ $staff->stall ? ' — '.$staff->stall->name : ' — Unassigned' }}</option>@endforeach
    </select>
</label>
@if ($staffAccounts->isEmpty())<p class="mt-2 text-xs text-maroon-800">Create an active staff account in User accounts before registering a stall.</p>@else<p class="mt-2 text-[11px] text-stone-600">Selecting an assigned account moves it from its current stall.</p>@endif
