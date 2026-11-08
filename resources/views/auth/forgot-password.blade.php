@extends('layout')
@section('title', 'Forgot password')
@section('compact-layout', 'true')
@section('main-class', 'customer-flow customer-recovery')
@section('content')
@include('mobile-heading', ['back' => route('login'), 'heading' => 'Forgot password'])
@include('seal', ['sealClass' => 'mx-auto mt-8 mb-7 size-[72px]'])
<h2 class="text-2xl font-bold text-maroon-800">Let’s get you back in.</h2>
<p class="mt-2 text-sm leading-5 text-stone-600">Enter your email to receive a secure password reset link.</p>
<form class="mt-7" action="{{ route('password.email') }}" method="post">
    @csrf
    <label class="customer-label">Email address<input class="customer-input" type="email" name="email" value="{{ old('email') }}" placeholder="name@example.com" autocomplete="email" maxlength="255" required></label>
    <p class="mt-3 text-xs text-stone-600">Use the email linked to your campus account.</p>
    <button class="design-primary mt-7 w-full">Send reset link</button>
</form>
<section class="mt-7 rounded-2xl bg-maroon-50 p-4"><h3 class="text-sm font-semibold text-maroon-800">What happens next?</h3><p class="mt-2 text-sm leading-5 text-stone-600">Check your inbox, follow the secure link, then choose a new password to log in again.</p></section>
<a class="design-secondary mt-6 w-full" href="{{ route('login') }}">Back to log in</a>
<p class="mt-7 text-xs text-stone-600">Need help? Contact the university administrator.</p>
@endsection
