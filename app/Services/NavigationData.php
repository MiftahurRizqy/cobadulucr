<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityApprovalDetail;
use App\Models\CrmNotification;
use App\Models\User;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;

class NavigationData
{
    public function for(User $user): array
    {
        return Cache::remember($this->key($user->id), now()->addSeconds(20), function () use ($user): array {
            $notificationQuery = CrmNotification::query()->where('user_id', $user->id);
            $notifications = (clone $notificationQuery)->latest()->limit(8)->get()
                ->map(fn (CrmNotification $notification): array => [
                    'id' => $notification->id,
                    'is_read' => $notification->read_at !== null,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'created_ago' => $notification->created_at->diffForHumans(),
                ])->values()->all();
            $notificationUnread = (clone $notificationQuery)->whereNull('read_at')->count();
            $followUps = collect();
            $followUpCount = 0;

            if ($user->canAccess('activities.view')) {
                $query = Activity::query()->visibleTo($user)->whereNotNull('next_follow_up_at')
                    ->whereNull('follow_up_completed_at')->where('next_follow_up_at', '<=', now()->addDays(2));
                $followUpCount = (clone $query)->count();
                $followUps = $query->with(['customer:id,company_name', 'lead:id,company_name'])->orderBy('next_follow_up_at')->limit(8)->get()
                    ->map(fn (Activity $activity): array => [
                        'id' => $activity->id,
                        'is_overdue' => $activity->next_follow_up_at->isPast(),
                        'summary' => $activity->summary,
                        'customer_name' => $activity->subject_name,
                        'due_ago' => $activity->next_follow_up_at->diffForHumans(),
                    ])->values()->all();
            }

            $approvalWaiting = $user->canAccess('approvals.view')
                ? ActivityApprovalDetail::query()->where('approval_status', 'pending')
                    ->whereIn('activity_id', Activity::query()->visibleTo($user)->select('activities.id'))->count()
                : 0;

            $taskOpen = $user->canAccess('tasks.view')
                ? Task::query()->visibleTo($user)->whereNotIn('status', ['done', 'cancelled'])->count()
                : 0;

            return ['unread' => $notificationUnread + $followUpCount,
                'headerNotifications' => $notifications, 'headerFollowUps' => $followUps,
                'approvalWaiting' => $approvalWaiting, 'taskOpen' => $taskOpen,
                'workWaiting' => $taskOpen + $approvalWaiting];
        });
    }

    public function forget(int $userId): void { Cache::forget($this->key($userId)); }
    // Keep the payload version in the key so a deployment that changes the
    // cached navigation structure never reuses an incompatible old value.
    private function key(int $userId): string
    {
        $tenantId = app(TenantManager::class)->current()?->id ?? 'central';

        return "navigation-data:v5:tenant:{$tenantId}:user:{$userId}";
    }
}
