@if (is_file(public_path('zppsu-seal.png')))
    <img src="{{ asset('zppsu-seal.png') }}" alt="ZPPSU seal" class="{{ $sealClass }} object-contain">
@else
    <span class="{{ $sealClass }} inline-grid shrink-0 place-items-center rounded-full border-2 border-gold-400 bg-maroon-800 text-xs font-bold text-gold-400" aria-label="ZPPSU">ZPPSU</span>
@endif
