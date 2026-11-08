<?php

namespace App\Http\Middleware;

use App\Models\Stall;
use App\Services\CanteenAudit;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordWorkspaceActivity
{
    private const FIELDS = ['name', 'user_type', 'is_active', 'stall_id', 'price', 'stock_qty', 'is_available', 'delivery_enabled', 'pickup_enabled', 'delivery_fee', 'opens_at', 'closes_at', 'weekly_hours', 'preparation_minutes', 'image_path'];

    public function __construct(private CanteenAudit $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $download = $request->routeIs('admin.sales.export')
            || ($request->routeIs('admin.reports.index') && in_array($request->query('format'), ['csv', 'print'], true))
            || ($request->routeIs('admin.audit.index') && $request->boolean('export'));
        $actor = $request->user();
        if (! $actor || ($request->isMethodSafe() && ! $download)) {
            return $next($request);
        }
        $models = collect($request->route()->parameters())->filter(fn ($value) => $value instanceof Model)
            ->filter(fn (Model $model) => $actor->user_type === 'admin' || $model->getAttribute('stall_id') === $actor->stall_id || ($model instanceof Stall && $model->id === $actor->stall_id));
        if ($request->routeIs('staff.profile.*')) {
            $models->put('account', $actor);
        }
        if ($request->routeIs('staff.delivery.update')) {
            $models->put('stall', $actor->stall);
        }
        $before = $models->map(fn (Model $model) => $model->only(self::FIELDS))->all();
        $result = 'failed';
        try {
            $response = $next($request);
            $result = $response->getStatusCode() < 400 && ! in_array('errors', $request->session()->get('_flash.new', []), true) ? 'success' : 'failed';

            return $response;
        } finally {
            try {
                $parameters = collect($request->route()->parameters())->map(fn ($value) => $value instanceof Model ? $value->getKey() : $value)->all();
                $this->audit->record([
                    'actor_id' => $actor->id,
                    'actor' => $actor->name,
                    'event' => $request->route()->getName() ?? 'Workspace change',
                    'target' => $parameters ? collect($parameters)->map(fn ($value, $key) => ucfirst($key).' #'.$value)->implode(' · ') : 'Workspace',
                    'result' => $result,
                    'details' => json_encode([
                        'method' => $request->method(),
                        'route' => $request->route()->getName(),
                        'before' => $before,
                        'after' => $result === 'success' ? $models->map(fn (Model $model) => $model->fresh()?->only(self::FIELDS))->all() : [],
                    ], JSON_THROW_ON_ERROR),
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
