<form method="post" action="{{ route('staff.stalls.update', $stall) }}" data-kds-toggle>
    @csrf @method('PATCH')
    <button type="submit" name="{{ $field }}" value="{{ (int) ! $stall->{$field} }}" class="kds-toggle" role="switch" aria-checked="{{ $stall->{$field} ? 'true' : 'false' }}" data-on-label="{{ $onLabel }}" data-off-label="{{ $offLabel }}">
        <span data-toggle-label>{{ $stall->{$field} ? $onLabel : $offLabel }}</span><span class="kds-switch-track" aria-hidden="true"><span></span></span>
    </button>
    <p class="kds-feedback" role="status"></p>
</form>

