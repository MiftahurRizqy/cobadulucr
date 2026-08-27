<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityApprovalDetail;
use App\Models\User;
use App\Services\CrmNotifier;
use App\Support\ApprovalCaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ApprovalController extends Controller
{
    public function captcha(Request $request, Activity $activity)
    {
        abort_unless(Activity::query()->visibleTo($request->user())->whereKey($activity)->exists(), 403);
        abort_unless($request->user()->canApprove(), 403);
        abort_if((int) $activity->user_id === (int) $request->user()->id, 403);
        abort_unless($activity->approvalDetail?->approval_status === 'pending', 422);

        $code = (string) random_int(10000, 99999);
        $token = Crypt::encryptString(json_encode([
            'activity_id' => $activity->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ], JSON_THROW_ON_ERROR));

        return response()->json([
            'image' => ApprovalCaptcha::imageDataUri($code),
            'token' => $token,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function index(Request $request)
    {
        $visibleActivityIds = Activity::query()->visibleTo($request->user())->select('activities.id');
        $status = $request->input('status', 'pending');
        $focusedApproval = null;
        if ($request->filled('activity')) {
            $focusedApproval = ActivityApprovalDetail::query()
                ->where('activity_id', $request->integer('activity'))
                ->whereIn('activity_id', Activity::query()->visibleTo($request->user())->select('activities.id'))
                ->first();
            if ($focusedApproval) $status = $focusedApproval->approval_status;
        }
        if (! in_array($status, ['all', 'pending', 'approved', 'revision', 'rejected'], true)) {
            $status = 'pending';
        }

        $approvals = ActivityApprovalDetail::query()
            ->whereIn('activity_id', $visibleActivityIds)
            ->with(['activity.customer', 'activity.opportunity', 'activity.user', 'activity.approvalDetail.decidedBy', 'activity.attachments'])
            ->when($status !== 'all', fn ($query) => $query->where('approval_status', $status))
            ->when($request->filled('type'), fn ($query) => $query->whereHas(
                'activity',
                fn ($activity) => $activity->where('type', $request->input('type'))
            ))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->whereHas('activity', fn ($activity) => $activity
                    ->where('summary', 'like', "%$search%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('company_name', 'like', "%$search%"))
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%$search%")));
            })
            ->when($focusedApproval, fn ($query) => $query->orderByRaw('activity_id = ? desc', [$focusedApproval->activity_id]))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = ActivityApprovalDetail::query()
            ->whereIn('activity_id', Activity::query()->visibleTo($request->user())->select('activities.id'))
            ->selectRaw('approval_status, count(*) as total')
            ->groupBy('approval_status')
            ->pluck('total', 'approval_status');

        return view('approvals.index', compact('approvals', 'counts', 'status'));
    }

    public function revise(Request $request, Activity $activity)
    {
        abort_unless($request->user()->isMasterAdmin() || (int) $activity->user_id === (int) $request->user()->id, 403);
        abort_unless($activity->approvalDetail, 404);

        if ($activity->approvalDetail->approval_status !== 'revision') {
            return redirect()
                ->route('approvals.index', ['status' => $activity->approvalDetail->approval_status, 'activity' => $activity->id])
                ->with('success', 'Pengajuan ini sudah diajukan ulang atau telah diputuskan.');
        }

        $activity->load(['customer', 'approvalDetail']);

        return view('approvals.revise', compact('activity'));
    }

    public function resubmit(Request $request, Activity $activity, CrmNotifier $notifier)
    {
        $this->authorizeRevision($request, $activity);
        $fields = Activity::APPROVAL_FIELDS[$activity->type] ?? [];
        abort_unless($fields, 404);

        if ($activity->type === 'approval_special_price') {
            $data = $request->validate([
                'summary' => ['required', 'string', 'max:255'],
                'detail' => ['nullable', 'string', 'max:5000'],
                'special_price_items' => ['required', 'array'],
                'special_price_items.*.requested_price' => ['required', 'numeric', 'min:0'],
                'special_price_items.*.reason' => ['required', 'string', 'max:2000'],
            ]);
            $submitted = collect($data['special_price_items']);
            $items = collect($activity->approvalDetail->special_price_items ?? [])->map(function ($item) use ($submitted) {
                $replacement = $submitted->get((string) $item['opportunity_item_id']);
                if (! $replacement) return $item;
                return array_merge($item, [
                    'requested_price' => (float) $replacement['requested_price'],
                    'reason' => trim($replacement['reason']),
                    'status' => 'pending',
                    'decision_note' => null,
                ]);
            })->values()->all();
            $activity->update(['summary' => $data['summary'], 'detail' => $data['detail'] ?? null]);
            $activity->approvalDetail->update([
                'special_price_items' => $items,
                'approval_status' => 'pending', 'decision_note' => null,
                'decided_by' => null, 'decided_at' => null,
            ]);
            return $this->notifyResubmission($activity, $request, $notifier);
        }

        $validator = Validator::make($request->all(), [
            'summary' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:5000'],
            'approval_details' => ['required', 'array'],
        ]);
        $validator->after(function ($validator) use ($request, $fields, $activity) {
            foreach ($fields as $key => $field) {
                if (str_starts_with($field['type'], 'computed_')) continue;
                if ($activity->type === 'approval_credit_limit' && $key === 'current_limit') continue;
                $value = $request->input("approval_details.$key");
                if ($value === null || $value === '') {
                    $validator->errors()->add("approval_details.$key", $field['label'].' wajib diisi.');
                } elseif (in_array($field['type'], ['currency', 'number'], true)
                    && (! is_numeric($value) || (float) $value < 0)) {
                    $validator->errors()->add("approval_details.$key", $field['label'].' harus berupa angka yang valid.');
                }
            }
        });
        $data = $validator->validate();
        $details = collect($data['approval_details'])
            ->only(array_keys($fields))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();
        if ($activity->type === 'approval_credit_limit') {
            $currentLimit = (float) $activity->customer->credit_limit;
            $details['current_limit'] = $currentLimit;
            if ((float) ($details['requested_limit'] ?? 0) <= $currentLimit) {
                throw ValidationException::withMessages([
                    'approval_details.requested_limit' => 'Batas kredit baru harus lebih besar dari batas kredit saat ini.',
                ]);
            }
            $outstanding = (float) ($details['outstanding_receivables'] ?? 0);
            $newOrder = (float) ($details['new_order_value'] ?? 0);
            $details['remaining_limit'] = $currentLimit - $outstanding;
            $details['over_limit'] = max(0, $newOrder - max(0, $details['remaining_limit']));
        }
        if ($activity->type === 'approval_payment_term') {
            $details['additional_days'] = max(
                0,
                (int) ($details['requested_days'] ?? 0) - (int) ($details['current_days'] ?? 0)
            );
        }

        $approvers = User::query()
            ->whereIn('id', $activity->participants ?? [])
            ->whereKeyNot($activity->user_id)
            ->where('is_active', true)
            ->where('is_approver', true)
            ->get();
        if ($approvers->isEmpty()) {
            throw ValidationException::withMessages([
                'approver' => 'Pengajuan belum memiliki approver aktif. Pilih akun approver terlebih dahulu.',
            ]);
        }

        $activity->update([
            'summary' => $data['summary'],
            'detail' => $data['detail'] ?? null,
        ]);
        $activity->approvalDetail->update($details + [
            'approval_status' => 'pending',
            'decision_note' => null,
            'decided_by' => null,
            'decided_at' => null,
        ]);

        foreach ($approvers as $approver) {
            $notifier->send(
                $approver->id,
                'activity_approval_waiting',
                'Approval telah diperbaiki',
                $request->user()->name.' mengajukan ulang '.(Activity::TYPES[$activity->type] ?? 'approval').' untuk '.$activity->customer->company_name.'.',
                route('approvals.index', ['status' => 'pending', 'activity' => $activity->id], false).'#approval-'.$activity->id,
                ['activity_id' => $activity->id]
            );
        }

        return redirect()
            ->route('approvals.index')
            ->with('success', 'Perbaikan berhasil diajukan ulang kepada approver.');
    }

    private function notifyResubmission(Activity $activity, Request $request, CrmNotifier $notifier)
    {
        $approvers = User::query()->whereIn('id', $activity->participants ?? [])->whereKeyNot($activity->user_id)
            ->where('is_active', true)->where('is_approver', true)->get();
        if ($approvers->isEmpty()) {
            throw ValidationException::withMessages(['approver' => 'Pengajuan belum memiliki approver aktif.']);
        }
        foreach ($approvers as $approver) {
            $notifier->send($approver->id, 'activity_approval_waiting', 'Approval telah diperbaiki',
                $request->user()->name.' mengajukan ulang '.(Activity::TYPES[$activity->type] ?? 'approval').' untuk '.$activity->customer->company_name.'.',
                route('approvals.index', ['status' => 'pending', 'activity' => $activity->id], false).'#approval-'.$activity->id,
                ['activity_id' => $activity->id]);
        }
        return redirect()->route('approvals.index')->with('success', 'Perbaikan berhasil diajukan ulang kepada approver.');
    }

    private function authorizeRevision(Request $request, Activity $activity): void
    {
        abort_unless($request->user()->isMasterAdmin() || (int) $activity->user_id === (int) $request->user()->id, 403);
        abort_unless($activity->approvalDetail, 404);
        abort_unless($activity->approvalDetail->approval_status === 'revision', 422, 'Pengajuan ini tidak sedang menunggu perbaikan.');
    }
}
