@extends('layout')
@section('title', 'Stall settings')
@section('content')
@include('stall-settings-form', ['settingsAction' => route('admin.stalls.configuration.update', $stall), 'settingsBackUrl' => route('admin.stalls.index')])
@endsection
