@extends('layout')
@section('title', 'Verify your email')
@section('compact-layout', 'true')
@section('main-class', 'customer-flow customer-recovery')
@section('content')
@include('seal', ['sealClass' => 'mx-auto mb-7 size-[72px]'])
<h1 class="text-2xl font-bold text-maroon-800">Verify your email address</h1>
<p class="mt-3 text-sm leading-6 text-stone-600">Open the verification link sent to <strong class="wrap-anywhere text-stone-900">{{ auth()->user()->email }}</strong> to browse menus, manage your basket, and place orders.</p>
<p class="mt-3 text-sm text-stone-600">Check your spam folder if the email has not arrived. Verification links expire after {{ config('auth.verification.expire', 60) }} minutes.</p>
<form method="post" action="{{ route('verification.send') }}" class="mt-7">@csrf<button class="design-primary w-full">Resend verification email</button></form>
<p class="mt-3 text-xs text-stone-600">You can request a new link once every 60 seconds.</p>
<a href="{{ route('account.edit') }}" class="design-secondary mt-5 w-full">Change email address</a>
<form method="post" action="{{ route('logout') }}" class="mt-3">@csrf<button class="design-secondary w-full">Log out</button></form>
@endsection
