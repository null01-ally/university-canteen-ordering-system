@extends('layout')
@section('title', 'Your basket')
@section('compact-layout', 'true')
@section('main-class', 'customer-flow')
@section('content')
@include('mobile-heading', ['back' => route('stalls.index'), 'heading' => 'Your basket'])
@include('customer.empty-state')
@endsection


