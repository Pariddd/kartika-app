<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditAction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Log audit: siapa membuka atau mengubah apa, kapan, dan apakah ditolak. Hanya admin.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', Rule::in(array_keys(AuditAction::all()))],
            'denied' => ['nullable', 'boolean'],
        ]);

        $logs = AuditLog::query()
            ->with(['user:id,name,role', 'case:id,name,mode'])
            ->when($filters['user'] ?? null, fn ($query, $user) => $query->where('user_id', $user))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['denied'] ?? false, fn ($query) => $query->where('status', '>=', 400))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $since = now()->subDay();

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
            'actions' => AuditAction::all(),
            'stats' => [
                'total' => AuditLog::query()->where('created_at', '>=', $since)->count(),
                'denied' => AuditLog::query()->where('created_at', '>=', $since)->where('status', '>=', 400)->count(),
                'failed_logins' => AuditLog::query()->where('created_at', '>=', $since)->where('action', 'login_gagal')->count(),
            ],
        ]);
    }
}
