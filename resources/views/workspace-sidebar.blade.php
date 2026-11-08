@php
$adminWorkspace = auth()->user()->user_type === 'admin';
$workspaceIcons = [
    'staff.summary' => 'staff-summary.png',
    'admin.reports.index' => 'admin-reports-index.png',
    'admin.audit.index' => 'admin-audit-index.png',
    'admin.sales.index' => 'admin-sales-index.png',
    'admin.users.index' => 'admin-users-index.png',
    'admin.stalls.index' => 'admin-stalls-index.png',
    'staff.orders.index' => 'staff-orders-index.png',
    'staff.menu.index' => 'staff-menu-index.png',
    'staff.stalls.index' => 'staff-stalls-index.png',
];
@endphp
<aside class="workspace-sidebar">
    <a href="{{ route('home') }}" class="flex items-center gap-3 px-2">
        @include('seal', ['sealClass' => 'size-11'])
        <span><strong class="block text-xl leading-none">ZPPSU</strong><small class="text-[10px]">CANTEEN SYSTEM</small></span>
    </a>
    <p class="mt-12 mb-4 hidden px-2 text-[10px] lg:block font-bold text-gold-400">{{ $adminWorkspace ? 'ADMINISTRATION' : 'STAFF WORKSPACE' }}</p>
    <div class="hidden lg:block">@include('workspace-links')</div>
    <details class="lg:hidden">
        <summary class="absolute top-3 right-4 flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg border border-white/40 px-3 text-sm font-semibold">
            <img src="{{ asset('images/icons/workspace-sidebar-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-5" decoding="async">
            Menu
        </summary>
        <div class="mt-3 border-t border-white/25 pt-2">
            @include('workspace-links')
            <form method="post" action="{{ route('logout') }}">@csrf<button class="workspace-link w-full">Sign out</button></form>
        </div>
    </details>
    <div class="mt-auto hidden px-2 pt-10 lg:block">
        <div class="border-t border-gold-400"></div>
    </div>
    <form method="post" action="{{ route('logout') }}" class="mt-5 hidden lg:block">@csrf<button class="workspace-link w-full">Logout</button></form>
    <p class="mt-10 hidden px-2 text-[10px] lg:block">ZPPSU · Main Campus</p>
</aside>


