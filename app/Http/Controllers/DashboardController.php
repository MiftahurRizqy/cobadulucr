<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $customers = Customer::query()->visibleTo($user)->when(! $user->canAccess('customers.view'), fn ($query) => $query->whereRaw('1 = 0'));
        $opportunities = Opportunity::query()->visibleTo($user)->when(! $user->canAccess('opportunities.view'), fn ($query) => $query->whereRaw('1 = 0'));
        $tasks = Task::query()->visibleTo($user)->when(! $user->canAccess('tasks.view'), fn ($query) => $query->whereRaw('1 = 0'));
        $stageSummary = collect();

        if ($user->canAccess('opportunities.view')) {
            $stageSummary = PipelineStage::query()
                ->whereHas('opportunities', fn ($q) => $q->visibleTo($user)->where('status', 'open'))
                ->withCount(['opportunities' => fn ($q) => $q->visibleTo($user)->where('status', 'open')])
                ->withSum(['opportunities as open_value' => fn ($q) => $q->visibleTo($user)->where('status', 'open')], 'estimated_value')
                ->orderBy('position')->get();
        }

        return view('dashboard', [
            'stats' => [
                'customers' => (clone $customers)->count(),
                'leads' => $user->canAccess('leads.view') ? Lead::query()->visibleTo($user)->whereNotIn('status', ['converted', 'leads_hold'])->count() : 0,
                'pipeline' => (float) (clone $opportunities)->where('status', 'open')->sum('estimated_value'),
                'weighted' => (float) (clone $opportunities)->where('status', 'open')
                    ->selectRaw('COALESCE(SUM(estimated_value * probability / 100), 0) as weighted_total')
                    ->value('weighted_total'),
                'open_opportunities' => (clone $opportunities)->where('status', 'open')->count(),
                'tasks_due' => (clone $tasks)->whereNotIn('status', ['done', 'cancelled'])->where('due_at', '<=', now()->endOfDay())->count(),
                'tasks_overdue' => (clone $tasks)->whereNotIn('status', ['done', 'cancelled'])->where('due_at', '<', now()->startOfDay())->count(),
            ],
            'stageSummary' => $stageSummary,
            'myTasks' => (clone $tasks)->with('customer')->whereNotIn('status', ['done', 'cancelled'])->orderBy('due_at')->limit(6)->get(),
            'followUps' => (clone $customers)->whereNotNull('next_follow_up_at')->orderBy('next_follow_up_at')->limit(6)->get(),
            'recentActivities' => $user->canAccess('activities.view')
                ? Activity::query()->visibleTo($user)->with(['customer', 'lead', 'user'])->latest('occurred_at')->limit(7)->get()
                : collect(),
            'teamUsers' => $user->isMasterAdmin() ? User::where('is_active', true)->count() : null,
        ]);
    }
}
