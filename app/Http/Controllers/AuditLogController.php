<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditEvents;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user' => ['nullable', 'integer'],
            'event' => ['nullable', 'string', 'max:60'],
            'type' => ['nullable', Rule::in(AuditEvents::TYPES)],
            'id' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::in([AuditLogger::SOURCE_WEB, AuditLogger::SOURCE_MAINTENANCE, AuditLogger::SOURCE_CONSOLE])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->filter($filters)
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $view = $request->header('HX-Request') && ! $request->header('HX-Boosted') ? 'audit._table' : 'audit.index';

        return view($view, [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::withTrashed()->orderBy('username')->get(['id', 'username', 'name']),
            'events' => AuditEvents::ALL,
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        return view('audit.show', ['log' => $auditLog->load('user')]);
    }
}
