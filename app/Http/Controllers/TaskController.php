<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;
use App\Services\CrmNotifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = Task::visibleTo($request->user())->with(['customer', 'opportunity', 'assignees', 'creator', 'reviewer'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = trim((string) $request->input('q'));
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('task_id', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('company_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($request->scope === 'customer', fn ($q) => $q->whereNotNull('customer_id'))
            ->when($request->scope === 'internal', fn ($q) => $q->whereNull('customer_id'))
            ->when($request->mine, fn ($q) => $q->whereHas('assignees', fn ($q) => $q->whereKey($request->user()->id)))
            ->orderByRaw("CASE WHEN due_at IS NULL THEN 1 ELSE 0 END")->orderBy('due_at')->paginate(20)->withQueryString();
        return view('tasks.index', compact('tasks'));
    }

    public function create(Request $request)
    {
        return view('tasks.form', ['task' => new Task(['customer_id' => $request->customer, 'opportunity_id' => $request->opportunity]), 'customers' => Customer::visibleTo($request->user())->get(), 'opportunities' => Opportunity::visibleTo($request->user())->get(), 'users' => User::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, CrmNotifier $notifier)
    {
        // Requests created before the scope selector existed remain valid.
        $request->mergeIfMissing([
            'task_scope' => $request->filled('customer_id') ? 'customer' : 'internal',
        ]);
        $data = $request->validate(['task_scope' => ['required', 'in:customer,internal'], 'title' => ['required'], 'description' => ['nullable'], 'customer_id' => ['nullable', 'required_if:task_scope,customer', 'prohibited_if:task_scope,internal', 'exists:customers,id'], 'opportunity_id' => ['nullable', 'prohibited_if:task_scope,internal', Rule::exists('opportunities', 'id')->where(fn ($query) => $query->where('customer_id', $request->input('customer_id')))], 'reviewer_id' => ['nullable', 'exists:users,id'], 'due_at' => ['nullable', 'date'], 'priority' => ['required', 'in:low,medium,high'], 'assignee_ids' => ['required', 'array', 'min:1'], 'assignee_ids.*' => ['exists:users,id']]);
        $assigneeIds = $data['assignee_ids'];
        unset($data['task_scope'], $data['assignee_ids']);
        if ($data['customer_id'] ?? null) abort_unless(Customer::visibleTo($request->user())->whereKey($data['customer_id'])->exists(), 403);
        if ($data['opportunity_id'] ?? null) abort_unless(Opportunity::visibleTo($request->user())->whereKey($data['opportunity_id'])->exists(), 403);
        $task = Task::create($data + ['created_by' => $request->user()->id]);
        $task->assignees()->sync($assigneeIds);
        foreach ($assigneeIds as $id) $notifier->send($id, 'task_assigned', 'Task baru: '.$task->title, 'Batas waktu '.($task->due_at?->format('d M Y H:i') ?? 'belum ditentukan'), route('tasks.index'));
        return redirect()->route('tasks.index')->with('success', 'Task berhasil dibuat.');
    }

    public function updateStatus(Request $request, Task $task)
    {
        abort_unless(Task::visibleTo($request->user())->whereKey($task->id)->exists(), 403);
        $data = $request->validate(['status' => ['required', 'in:todo,in_progress,review,done,blocked,cancelled'], 'completion_note' => ['nullable']]);
        if ($data['status'] === 'review' && ! $task->reviewer_id) {
            return back()->withErrors(['status' => 'Pilih reviewer sebelum memindahkan task ke Menunggu Review.']);
        }
        $task->update($data + ['completed_at' => $data['status'] === 'done' ? now() : null]);
        return back()->with('success', 'Status task diperbarui.');
    }
}
