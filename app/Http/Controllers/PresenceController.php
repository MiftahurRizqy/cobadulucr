<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPresence;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function heartbeat(Request $request)
    {
        $data = $request->validate([
            'path' => ['nullable', 'string', 'max:500'],
            'page' => ['nullable', 'string', 'max:160'],
        ]);

        UserPresence::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'current_path' => $data['path'] ?? '/',
                'current_page' => $this->activityLabel($data['path'] ?? '/', $data['page'] ?? null),
                'last_seen_at' => now(),
            ]
        );

        return response()->noContent();
    }

    public function index(Request $request)
    {
        $this->authorizeMonitor($request->user());
        $users = $this->recentUsers();
        return view('users.active', compact('users'));
    }

    public function data(Request $request)
    {
        $this->authorizeMonitor($request->user());

        return response()->json([
            'users' => $this->recentUsers()->map(function (User $user) {
                $lastSeen = $user->presence?->last_seen_at;
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'initials' => collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join(''),
                    'role' => $user->roleNames() ?: ucfirst(str_replace('_', ' ', $user->authority_level)),
                    'page' => $user->presence?->current_page ?: 'Menggunakan CRM',
                    'online' => $lastSeen?->gte(now()->subMinutes(2)) ?? false,
                    'last_seen' => $lastSeen?->diffForHumans(),
                ];
            })->values(),
        ]);
    }

    private function recentUsers()
    {
        return User::query()
            ->with(['roles', 'presence'])
            ->where('is_active', true)
            ->whereHas('presence', fn ($query) => $query->where('last_seen_at', '>=', now()->subDay()))
            ->get()
            ->sortByDesc(fn (User $user) => $user->presence?->last_seen_at)
            ->values();
    }

    private function authorizeMonitor(User $user): void
    {
        abort_unless($user->isMasterAdmin() || in_array($user->authority_level, ['manager', 'supervisor'], true), 403);
    }

    private function activityLabel(string $path, ?string $page): string
    {
        $cleanPath = parse_url($path, PHP_URL_PATH) ?: '/';
        $patterns = [
            '#^/activities/create#' => 'Mencatat aktivitas',
            '#^/activities#' => 'Melihat aktivitas',
            '#^/customers/\d+/edit#' => 'Mengubah customer',
            '#^/customers/\d+#' => 'Melihat detail customer',
            '#^/customers#' => 'Melihat daftar customer',
            '#^/opportunities/kanban#' => 'Melihat pipeline penjualan',
            '#^/opportunities/create#' => 'Membuat opportunity',
            '#^/opportunities/\d+#' => 'Melihat detail opportunity',
            '#^/opportunities#' => 'Melihat daftar opportunity',
            '#^/tasks#' => 'Melihat task',
            '#^/approvals#' => 'Memproses approval',
            '#^/notifications#' => 'Melihat notifikasi',
            '#^/reports#' => 'Melihat laporan',
            '#^/users/active#' => 'Memantau pengguna aktif',
            '#^/users#' => 'Mengelola pengguna',
            '#^/$#' => 'Melihat ringkasan',
        ];

        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $cleanPath)) return $label;
        }

        return $page ? trim(str_replace(['· Unified CRM', '- Unified CRM'], '', $page)) : 'Menggunakan CRM';
    }
}
