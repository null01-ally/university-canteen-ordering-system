<span @class([
    'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
    'bg-status-pending-bg text-status-pending-text' => $status === 'pending',
    'bg-status-preparing-bg text-status-preparing-text' => $status === 'preparing',
    'bg-status-ready-bg text-status-ready-text' => $status === 'ready',
    'bg-status-completed-bg text-status-completed-text' => in_array($status, ['completed', 'cancelled'], true),
])>{{ \App\Models\Order::STAGES[$status] }}</span>
