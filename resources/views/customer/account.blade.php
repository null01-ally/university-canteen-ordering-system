@extends('layout')
@section('title', 'Profile & settings')
@section('compact-layout', 'true')
@section('main-class', 'customer-flow customer-profile')
@section('content')
@include('mobile-heading', ['back' => route('stalls.index'), 'heading' => 'Profile & settings'])
<section class="mb-6 rounded-2xl bg-maroon-50 p-4"><h2 class="text-lg font-semibold text-maroon-800">{{ auth()->user()->name }}</h2><p class="mt-1 text-xs text-stone-600">Customer · {{ 'CUS-'.str_pad(auth()->id(), 5, '0', STR_PAD_LEFT) }}</p></section>
<form action="{{ route('account.update') }}" method="post">
    @csrf @method('PATCH')
    <h2 class="mb-4 text-lg font-semibold">Personal details</h2>
    <div class="space-y-4">
        <label class="customer-label">Full name<input class="customer-input" name="name" value="{{ old('name', auth()->user()->name) }}" autocomplete="name" required maxlength="255"></label>
        <label class="customer-label">Contact number<input class="customer-input" type="tel" name="contact_phone" value="{{ old('contact_phone', auth()->user()->contact_phone) }}" autocomplete="tel" maxlength="32"></label>
        <label class="customer-label">Email address<input class="customer-input" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" autocomplete="email" required maxlength="255"></label>
    </div>
    <p class="mt-3 text-xs text-stone-600">Enter your current password to save account changes.</p>
    <h2 class="mt-5 mb-3 text-lg font-semibold">Security</h2>
    <div class="grid grid-cols-2 gap-3.5">
        <label class="customer-label">Current password<input class="customer-input" type="password" name="current_password" autocomplete="current-password" required></label>
        <label class="customer-label">New password<input class="customer-input" type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Enter new password"></label>
    </div>
    <label class="customer-label mt-3" data-password-confirmation>Confirm new password<input class="customer-input" type="password" name="password_confirmation" autocomplete="new-password" maxlength="72" placeholder="Re-enter new password"></label>
    <p class="mt-3 text-xs text-stone-600">Use at least 8 characters.</p>
    <button class="design-primary mt-5 w-full">Save changes</button>
</form>
<form method="post" action="{{ route('logout') }}" class="mt-3.5">@csrf<button class="design-secondary w-full" aria-label="Sign out">Log out</button></form>
<nav class="mt-5 flex justify-center gap-5 text-xs text-maroon-800" aria-label="Account navigation"><a href="{{ route('orders.index', ['history' => 1]) }}">Past orders</a><a href="{{ route('notifications.index') }}">Notifications</a></nav>
@endsection



