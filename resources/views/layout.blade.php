<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="@yield('referrer-policy', 'same-origin')">
    <title>@yield('title', 'Campus Canteen') · ZPPSU</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fonts
</head>
@php($workspace = ! View::hasSection('compact-layout') && in_array(auth()->user()?->user_type, ['staff', 'admin'], true))
<body data-icon-base="{{ asset('images/icons') }}/" @class(['min-h-screen font-sans text-stone-900 antialiased', 'workspace' => $workspace, 'bg-canteen-canvas' => ! $workspace, 'customer-shell' => auth()->user()?->user_type === 'customer'])>
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-10 focus:m-2 focus:bg-white focus:p-3">Skip to content</a>
    @if (auth()->user()?->user_type === 'customer')
        @include('customer.desktop-header')
    @endif
    @if ($workspace)
        @include('workspace-sidebar')
        <header class="workspace-top">
            <div class="workspace-context">
                <p class="text-xs text-stone-600">{{ auth()->user()->user_type === 'admin' ? 'University administration' : 'Canteen operations' }}</p>
                <p class="mt-1 text-sm font-semibold">{{ auth()->user()->user_type === 'admin' ? 'Overview & management' : auth()->user()->stall?->name }}</p>
            </div>
            @hasSection('workspace-controls')
                <div class="workspace-controls ml-auto flex flex-wrap items-center gap-4">@yield('workspace-controls')</div>
            @elseif (auth()->user()->user_type === 'admin')
                <p class="ml-auto rounded-xl bg-stone-50 px-4 py-3 text-xs">{{ now(config('canteen.timezone'))->format('l, j F Y') }}</p>

            @endif
            <div class="workspace-account-actions ml-auto flex items-center gap-3">
            <a class="menu-icon !size-10" href="{{ auth()->user()->user_type === 'admin' ? route('admin.audit.index') : route('staff.orders.index') }}" aria-label="View recent activity"><img src="{{ asset('images/icons/layout-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-5" decoding="async"></a>
            @if (auth()->user()->user_type === 'staff')
            <a href="{{ route('staff.profile.edit') }}" class="workspace-profile flex min-h-11 shrink-0 items-center gap-4 rounded-lg border-l border-maroon-100 pl-6 hover:bg-maroon-50" aria-label="Profile & account" title="Profile & account" @if (request()->routeIs('staff.profile.*')) aria-current="page" @endif>
            @else
            <div class="workspace-profile flex shrink-0 items-center gap-4 border-l border-maroon-100 pl-6">
            @endif
                <img src="{{ asset('images/icons/layout-2.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-5 text-maroon-800" decoding="async">
                <div><p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                <p class="text-xs text-stone-600">{{ auth()->user()->user_type === 'admin' ? 'Full access' : 'Canteen staff' }}</p></div>
            @if (auth()->user()->user_type === 'staff')
            </a>
            @else
            </div>
            @endif
            </div>
        </header>
    @elseif (! View::hasSection('compact-layout'))
    <header class="border-b border-slate-200 bg-white text-maroon-800">
        <div class="canteen-container flex flex-wrap items-center justify-between gap-x-8 gap-y-3 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg font-semibold">
                @include('seal', ['sealClass' => 'size-12 shrink-0'])
                <span><strong class="block text-[22px] leading-7">ZPPSU</strong><small class="block text-[10px] font-normal tracking-widest text-stone-600">CANTEEN SYSTEM</small></span>
            </a>
            @auth
                <nav aria-label="Main navigation" class="order-last flex w-full flex-wrap items-center gap-1 text-sm sm:order-none sm:w-auto">
                    @if (auth()->user()->user_type === 'customer')
                        <a @class(['nav-link', 'nav-current' => request()->routeIs('menu.*')]) href="{{ route('menu.index') }}">Menu</a>
                        <a @class(['nav-link', 'nav-current' => request()->routeIs('cart.*')]) href="{{ route('cart.index') }}">Cart</a>
                        <a @class(['nav-link', 'nav-current' => request()->routeIs('orders.*')]) href="{{ route('orders.index') }}">My orders</a>
                        <a @class(['nav-link', 'nav-current' => request()->routeIs('account.*')]) href="{{ route('account.edit') }}">Account</a>
                    @elseif (auth()->user()->user_type === 'staff')
                        <a @class(['nav-link', 'nav-current' => request()->routeIs('staff.orders.*')]) href="{{ route('staff.orders.index') }}">Order queue</a>
                        <a @class(['nav-link', 'nav-current' => request()->routeIs('staff.menu.*')]) href="{{ route('staff.menu.index') }}">Manage menu</a>
                    @elseif (auth()->user()->user_type === 'admin')
                        <a @class(['nav-link', 'nav-current' => request()->routeIs('admin.users.*')]) href="{{ route('admin.users.index') }}">Accounts</a>
                        <a @class(['nav-link', 'nav-current' => request()->routeIs('admin.sales.*')]) href="{{ route('admin.sales.index') }}">Daily sales</a>
                    @endif
                </nav>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="nav-link text-sm">Sign out</button>
                </form>
            @endauth
        </div>
    </header>
    @endif
    <main id="main" data-live-page="@yield('live-page', 'false')" class="{{ $workspace ? 'workspace-main' : '' }} @yield('main-class', $workspace ? '' : 'canteen-container py-7 sm:py-10')">
        @yield('page-header')
        @if (session('status'))
            <p role="status" class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-950">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <ul role="alert" class="mb-5 list-disc space-y-1 rounded-xl border border-red-200 bg-red-50 py-3 pr-4 pl-9 text-sm text-red-950">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        @yield('content')
    </main>
    @unless ($workspace || View::hasSection('compact-layout'))
    <footer class="canteen-container pb-8 text-center text-sm text-stone-600">
        Zamboanga Peninsula Polytechnic State University
    </footer>
    @endunless
</body>
</html>



