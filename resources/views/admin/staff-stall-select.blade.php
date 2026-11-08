<label class="field-label">Assigned stall
    <select class="field" name="stall_id">
        <option value="">Select a stall for canteen staff</option>
        @foreach ($stalls as $stall)
            <option value="{{ $stall->id }}" @selected((string) (($restoreInput ?? true) ? old('stall_id', $selectedStall) : $selectedStall) === (string) $stall->id)>{{ $stall->name }}</option>
        @endforeach
    </select>
</label>
