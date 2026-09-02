<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $logs = AuditLog::query()->with('user')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);
                $query->where(function ($nested) use ($search) {
                    $nested->where('module', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhere('auditable_id', $search)
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->module))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->action))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date_to))
            ->latest('id')->paginate(25)->withQueryString();

        $modules = AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $users = User::query()->whereHas('auditLogs')->orderBy('name')->get(['id', 'name', 'employee_id']);
        $todayCount = AuditLog::query()->whereDate('created_at', today())->count();
        $actorCount = AuditLog::query()->whereDate('created_at', today())->whereNotNull('user_id')->distinct('user_id')->count('user_id');

        return view('audit.index', compact('logs', 'modules', 'users', 'todayCount', 'actorCount'));
    }
}
