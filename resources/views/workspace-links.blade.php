<nav aria-label="Workspace" class="space-y-1">
        @foreach ($adminWorkspace ? ['admin.sales.index' => 'Sales overview', 'admin.users.index' => 'User accounts', 'admin.stalls.index' => 'Stall directory', 'admin.reports.index' => 'Reports', 'admin.audit.index' => 'Audit log'] : ['staff.orders.index' => 'Live order queue', 'staff.menu.index' => 'Menu & inventory', 'staff.stalls.index' => 'Stall settings', 'staff.summary' => 'Daily summary'] as $destination => $label)
            @php($active = ($destination === 'staff.summary' ? request()->routeIs('staff.summary') : request()->routeIs(Str::beforeLast($destination, '.').'.*')) || ($destination === 'staff.menu.index' && request()->routeIs('staff.inventory.*')) || ($destination === 'staff.stalls.index' && request()->routeIs('staff.settings.*')))
            <a @class(['workspace-link', 'workspace-link-active' => $active]) @if ($active) aria-current="page" @endif href="{{ route($destination) }}">
                <img src="{{ asset('images/icons/'.$workspaceIcons[$destination]) }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon size-5 shrink-0 object-contain" decoding="async">
                {{ $label }}
            </a>
        @endforeach
    </nav>


