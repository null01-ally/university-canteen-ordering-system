@extends('layout')
@section('title', 'Create an account')
@section('content')
<section class="panel mx-auto max-w-lg p-6 sm:p-8">
    <h1 class="page-heading">Create an account</h1>
    <p class="mt-2 text-stone-600">Register with your email address to order from the canteen.</p>
    <form method="post" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf
        <label class="field-label">Full name
            <input class="field" name="name" autocomplete="name" value="{{ old('name') }}" required maxlength="255">
        </label>
        <label class="field-label">Email address
            <input class="field" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required>
        </label>
        <label class="field-label">Phone number
            @include('phone-input', ['phoneValue' => old('contact_phone', '+63 ')])
        </label>
        <label class="field-label">Password
            <input class="field" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
        </label>
        <label class="field-label">Confirm password
            <input class="field" name="password_confirmation" type="password" autocomplete="new-password" required>
        </label>
        <button class="button-primary w-full">Create account</button>
        <a class="text-link inline-block" href="{{ route('login') }}">Back to sign in</a>
    </form>
</section>
@endsection
