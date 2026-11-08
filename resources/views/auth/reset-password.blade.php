@extends('layout')
@section('title', 'Reset password')
@section('referrer-policy', 'no-referrer')
@section('compact-layout', 'true')
@section('main-class', 'customer-flow')
@section('content')
@include('mobile-heading', ['back' => route('password.request'), 'backLabel' => 'Request another link', 'heading' => 'Reset password'])
<form class="panel space-y-4 p-5" action="{{ route('password.update') }}" method="post">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <label class="field-label">Email address<input class="design-field" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" maxlength="255" required></label>
    <label class="field-label">New password<input class="design-field" type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required></label>
    <label class="field-label">Confirm new password<input class="design-field" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="72" required></label>
    <button class="design-primary w-full">Reset password</button>
</form>
@endsection
