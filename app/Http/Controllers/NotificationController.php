<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CrmNotification;
use App\Services\NavigationData;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $followUps = Activity::query()->visibleTo($request->user())
            ->whereNotNull('next_follow_up_at')
            ->whereNull('follow_up_completed_at');

        if (! $request->user()->canAccess('activities.view')) $followUps->whereRaw('1 = 0');

        return view('notifications.index', [
            'overdueFollowUps' => (clone $followUps)->with(['customer', 'user'])->where('next_follow_up_at', '<', now())->orderBy('next_follow_up_at')->limit(20)->get(),
            'upcomingFollowUps' => (clone $followUps)->with(['customer', 'user'])->whereBetween('next_follow_up_at', [now(), now()->addDays(2)])->orderBy('next_follow_up_at')->limit(20)->get(),
            'notifications' => CrmNotification::where('user_id', $request->user()->id)->latest()->paginate(30),
        ]);
    }

    public function read(Request $request, CrmNotification $notification, NavigationData $navigationData)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);
        $navigationData->forget($request->user()->id);
        return $notification->url ? redirect($notification->url) : back();
    }

    public function poll(Request $request)
    {
        $user = $request->user();
        $latest = CrmNotification::where('user_id', $user->id)->latest()->first();
        $unread = CrmNotification::where('user_id', $user->id)->whereNull('read_at')->count();
        $recentNotifications = CrmNotification::where('user_id', $user->id)->latest()->limit(8)->get();
        $headerFollowUps = collect();

        if ($user->canAccess('activities.view')) {
            $pendingFollowUps = Activity::query()->visibleTo($user)
                ->whereNotNull('next_follow_up_at')->whereNull('follow_up_completed_at')
                ->where('next_follow_up_at', '<=', now()->addDays(2));
            $unread += (clone $pendingFollowUps)->count();
            $headerFollowUps = (clone $pendingFollowUps)->with('customer')->orderBy('next_follow_up_at')->limit(8)->get();
        }

        $popupNotifications = $recentNotifications->map(fn (CrmNotification $notification) => [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'read' => filled($notification->read_at),
            'created_at' => $notification->created_at?->diffForHumans(),
            'read_url' => route('notifications.read', $notification),
            'url' => $notification->url,
            '_sort' => $notification->created_at?->timestamp ?? 0,
        ])->concat($headerFollowUps->map(fn (Activity $activity) => [
            'id' => 'follow-up-'.$activity->id,
            'title' => $activity->next_follow_up_at->isPast() ? 'Follow-up terlambat' : 'Follow-up segera',
            'message' => $activity->summary.' · '.$activity->customer->company_name,
            'read' => false,
            'created_at' => $activity->next_follow_up_at->diffForHumans(),
            'read_url' => null,
            'url' => route('activities.follow-up', $activity),
            '_sort' => $activity->next_follow_up_at->timestamp,
        ]))->sortByDesc('_sort')->take(8)->map(function (array $item) {
            unset($item['_sort']);
            return $item;
        })->values();

        return response()->json([
            'unread_count' => $unread,
            'latest' => $latest ? [
                'id' => $latest->id,
                'title' => $latest->title,
                'message' => $latest->message,
                'url' => $latest->url,
                'created_at' => $latest->created_at?->toIso8601String(),
            ] : null,
            'notifications' => $popupNotifications,
        ]);
    }
}
