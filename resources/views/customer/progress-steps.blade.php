<ol class="minimal-progress" aria-label="Order progress">
    @foreach ($labels as $stage => $label)
        <li @class(['is-done' => $loop->index < $position, 'is-current' => $loop->index === $position]) @if ($loop->index === $position) aria-current="step" @endif>
            <span class="minimal-progress-dot" aria-hidden="true">{{ $loop->index <= $position ? '✓' : $loop->iteration }}</span>
            <strong>{{ $stage === 'preparing' ? 'Preparing' : ($stage === 'ready' ? 'Ready' : $label) }}</strong>
        </li>
    @endforeach
</ol>


