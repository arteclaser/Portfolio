<?php

namespace App\Http\Controllers\Panel;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityController extends PanelController
{
    public function __invoke(Request $request)
    {
        Gate::authorize('view-activity');
        $filters = $request->validate(['usuario' => ['nullable', 'integer'], 'evento' => ['nullable', 'string', 'max:40']]);
        $query = ActivityLog::with('user')->latest('id');
        if (! empty($filters['usuario'])) {
            $query->where('user_id', $filters['usuario']);
        }
        if (! empty($filters['evento'])) {
            $query->where('event', 'like', str_replace(['%', '_'], ['\\%', '\\_'], $filters['evento']).'%');
        }

        return view('panel.activity', [
            'logs' => $query->paginate(50)->withQueryString(),
            'users' => User::where('portfolio_id', \App\Support\Tenant::id())->orderBy('name')->pluck('name', 'id'),
            'filters' => $filters,
        ]);
    }
}
