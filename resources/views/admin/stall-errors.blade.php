@if (old('stall_dialog') === $dialogId && $errors->any())
    <ul role="alert" class="mb-4 list-disc rounded-xl bg-red-50 py-3 pr-3 pl-8 text-sm text-red-900">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
@endif
