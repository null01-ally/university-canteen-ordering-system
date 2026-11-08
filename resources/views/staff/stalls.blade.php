@extends('layout')
@section('title', 'Stall settings')
@section('main-class', 'staff-balanced-settings')
@section('content')
@include('stall-settings-form', ['settingsAction' => route('staff.stalls.update', $stall), 'settingsBackUrl' => route('staff.stalls.index')])
@endsection
