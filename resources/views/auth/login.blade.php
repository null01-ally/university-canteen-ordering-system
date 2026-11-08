@extends('layout')
@section('title', 'Log in')
@section('compact-layout', 'true')
@section('main-class', 'login-screen')
@section('content')
<div class="mx-auto w-full max-w-[390px] px-6 pt-[clamp(3rem,13.5vh,7rem)] pb-12">
    <div class="text-center">
        @include('seal', ['sealClass' => 'mx-auto size-[104px]'])
        <h1 class="mt-6 text-[1.875rem] font-bold text-maroon-800">ZPPSU CANTEEN</h1>
    </div>
    <form method="post" action="{{ route('login') }}" class="mt-[clamp(2rem,7.5vh,4rem)] space-y-4">
        @csrf
        <label class="field-label font-medium">Email address
            <input class="design-field" name="email" type="email" autocomplete="username" placeholder="Enter your email address" value="{{ old('email') }}" required>
        </label>
        <div>
            <label class="field-label font-medium" for="login-password">Password</label>
            <div class="relative">
                <input class="design-field pr-12" id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                <button type="button" data-password-toggle class="menu-icon absolute top-1/2 right-1 -translate-y-1/2" aria-label="Show password" aria-controls="login-password" aria-pressed="false">
                    <img src="{{ asset('images/icons/login-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-5" decoding="async">
                </button>
            </div>
        </div>
        <a class="text-link block text-right text-xs" href="{{ route('password.request') }}">Forgot password?</a>
        <button class="design-primary mt-2 w-full">Log in</button>
        <p class="text-center text-xs text-stone-600">New to the canteen? <a class="text-link" href="{{ route('register') }}">Create an account</a></p>
    </form>
</div>
<footer class="mt-auto bg-maroon-800 px-6 py-10 text-center text-xs text-white">Zamboanga Peninsula Polytechnic State University</footer>
@endsection
