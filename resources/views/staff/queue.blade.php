@extends('layout')
@section('title', 'Kitchen orders')
@section('content')
<div class="kds-heading kds-queue-heading"><div><h1 class="workspace-heading">Live order queue</h1><div class="kds-live-tools"><span data-queue-indicator role="status">Updates every 10 seconds</span><button type="button" data-queue-sound aria-pressed="false" hidden>Enable order sound</button></div></div>
@include('staff.operation-switch', ['field' => 'is_active', 'onLabel' => 'Stall Open', 'offLabel' => 'Stall Closed'])
</div>
<div data-live-queue>@include('staff.queue-list')</div>
@endsection


