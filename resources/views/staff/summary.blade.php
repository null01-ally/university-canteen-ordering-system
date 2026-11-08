@extends('layout')
@section('title', 'Daily summary')
@section('content')
<div class="workspace-page-title"><div><h1 class="workspace-heading">Daily summary</h1><p>Today's sales and end-of-shift tally.</p></div></div>
<section class="w-full min-w-0" aria-label="Daily sales summary">
    @include('staff.summary-content')
</section>
@endsection
