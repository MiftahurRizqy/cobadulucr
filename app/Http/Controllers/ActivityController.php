<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\User;
use App\Services\ImageEvidenceInspector;
use App\Services\CrmNotifier;
use App\Services\HeicPreviewGenerator;
use App\Services\EvidenceThumbnailGenerator;
use App\Services\EvidencePreviewOptimizer;
use App\Services\AiEvidenceDetector;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $visibleActivities = Activity::query()->visibleTo($request->user());
        $canReviewEvidenceIntegrity = ! $request->user()->isSales();
        $needsEvidenceReview = $canReviewEvidenceIntegrity && $request->boolean('needs_review');
        $activityUsers = User::query()
            ->whereIn('id', (clone $visibleActivities)->select('user_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);
        $selectedUserId = $request->integer('user_id');
        if (! $activityUsers->contains('id', $selectedUserId)) $selectedUserId = 0;
        $selectedPeriod = in_array($request->period, ['today', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'], true)
            ? $request->period
            : null;
        [$dateFrom, $dateTo] = match ($selectedPeriod) {
            'today' => [today(), today()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            'custom' => [$this->filterDate($request->date_from), $this->filterDate($request->date_to)],
            default => [null, null],
        };

        $activitiesQuery = (clone $visibleActivities)->with(['customer', 'opportunity', 'user', 'approvalDetail', 'attachments', 'comments.user'])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($needsEvidenceReview, fn ($q) => $q->whereHas('attachments', fn ($q) => $q->whereIn(
                'verification_status',
                ['duplicate', 'suspicious', 'warning', 'review', 'tampered', 'ai_suspected', 'ai_review']
            )))
            ->when($dateFrom, fn ($q, $date) => $q->whereDate('occurred_at', '>=', $date->toDateString()))
            ->when($dateTo, fn ($q, $date) => $q->whereDate('occurred_at', '<=', $date->toDateString()))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('summary', 'like', "%$s%")
                ->orWhere('detail', 'like', "%$s%")
                ->orWhere('result', 'like', "%$s%")
                ->orWhere('next_action', 'like', "%$s%")
                ->orWhereHas('customer', fn ($q) => $q->where('company_name', 'like', "%$s%"))));
        $activities = $activitiesQuery->latest('occurred_at')->paginate(20, ['*'], 'activity_page')->withQueryString();
        $participantUsers = User::query()
            ->whereIn('id', $activities->getCollection()->flatMap(fn (Activity $activity) => $activity->participants ?? [])->unique())
            ->get()
            ->keyBy('id');
        $activityDiscussionUsers = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id', 'user_type']);

        return view('activities.index', compact('activities', 'activityUsers', 'selectedUserId', 'selectedPeriod', 'participantUsers', 'activityDiscussionUsers', 'canReviewEvidenceIntegrity', 'needsEvidenceReview'));
    }

    private function filterDate(?string $value): ?Carbon
    {
        if (! $value) return null;

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function create(Request $request)
    {
        return view('activities.form', $this->formData(
            $request,
            new Activity(['customer_id' => $request->customer, 'opportunity_id' => $request->opportunity, 'occurred_at' => now()]),
        ));
    }

    public function followUp(Request $request, Activity $activity)
    {
        abort_unless(Activity::query()->visibleTo($request->user())->whereKey($activity)->exists(), 403);
        abort_unless($activity->next_follow_up_at, 404);
        abort_if($activity->customer?->status === 'inactive', 422, 'Customer tidak aktif. Aktifkan customer terlebih dahulu sebelum membuat aktivitas.');

        return view('activities.form', $this->formData(
            $request,
            new Activity([
                'customer_id' => $activity->customer_id,
                'opportunity_id' => $activity->opportunity_id,
                'type' => 'call',
                'summary' => 'Follow-up: '.$activity->summary,
                'occurred_at' => now(),
            ]),
            $activity,
        ));
    }

    public function pendingFollowUps(Request $request)
    {
        $data = $request->validate(['customer_id' => ['required', 'integer', 'exists:customers,id']]);
        abort_unless(Customer::visibleTo($request->user())->whereKey($data['customer_id'])->exists(), 403);

        return response()->json(
            Activity::query()->visibleTo($request->user())
                ->where('customer_id', $data['customer_id'])
                ->whereNotNull('next_follow_up_at')
                ->whereNull('follow_up_completed_at')
                ->orderBy('next_follow_up_at')
                ->limit(50)
                ->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'summary' => $activity->summary,
                    'due_at' => $activity->next_follow_up_at->translatedFormat('d M Y, H:i'),
                    'overdue' => $activity->next_follow_up_at->isPast(),
                ])
        );
    }

    public function completeFollowUp(Request $request, Activity $activity)
    {
        abort_unless(Activity::query()->visibleTo($request->user())->whereKey($activity)->exists(), 403);
        abort_unless($activity->next_follow_up_at, 404);

        if (! $activity->follow_up_completed_at) {
            $activity->update([
                'follow_up_completed_at' => now(),
                'follow_up_completed_by' => $request->user()->id,
            ]);
            $this->syncCustomerNextFollowUp($activity->customer);
            $this->syncOpportunityNextFollowUp($activity->opportunity);
        }

        return back()->with('success', 'Follow-up ditandai selesai.');
    }

    private function formData(Request $request, Activity $activity, ?Activity $completesFollowUp = null): array
    {
        $evidenceRequired = $request->user()->requiresActivityEvidence();

        $customers = Customer::visibleTo($request->user())
            ->where('status', '!=', 'inactive')
            ->orderBy('company_name')
            ->get();

        return [
            'activity' => $activity,
            'customers' => $customers,
            'opportunities' => Opportunity::visibleTo($request->user())
                ->with(['stage:id,name', 'items:id,opportunity_id,product_name,quantity,quantity_unit,target_price,unit_price'])
                ->whereHas('customer', fn ($query) => $query->where('status', '!=', 'inactive'))
                ->orderBy('title')
                ->get(),
            'collaborationUsers' => User::query()
                ->where('is_active', true)
                ->whereKeyNot($request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id', 'user_type', 'authority_level', 'is_active', 'is_approver']),
            'evidenceRequired' => $evidenceRequired,
            'evidenceDepartments' => $evidenceRequired
                ? $request->user()->departments()->where('activity_evidence_required', true)->pluck('name')
                : collect(),
            'completesFollowUp' => $completesFollowUp,
        ];
    }

    public function store(Request $request, ImageEvidenceInspector $inspector, CrmNotifier $notifier, HeicPreviewGenerator $heicPreviewGenerator, AiEvidenceDetector $aiDetector, EvidencePreviewOptimizer $previewOptimizer, EvidenceThumbnailGenerator $thumbnailGenerator)
    {
        $isDecisionType = in_array($request->input('type'), Activity::DECISION_TYPES, true);
        $evidenceRequired = $request->user()->requiresActivityEvidence()
            && ! $isDecisionType;
        $resultRequired = $request->user()->isSales() && ! $isDecisionType;
        $validator = Validator::make($request->all(), [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($query) => $query->where('status', '!=', 'inactive'))],
            'opportunity_id' => ['nullable', 'exists:opportunities,id'],
            'type' => ['required', 'in:'.implode(',', array_keys(Activity::TYPES))], 'summary' => ['required'],
            'detail' => ['nullable', 'string'],
            'result' => [Rule::requiredIf($resultRequired), 'nullable', 'string'],
            'next_action' => ['nullable'],
            'approval_details' => ['nullable', 'array'],
            'special_price_items' => ['nullable', 'array'],
            'occurred_at' => ['required', 'date'], 'next_follow_up_at' => ['nullable', 'date'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'completes_follow_up_id' => ['nullable', 'integer', 'exists:activities,id'],
            'attachments' => [Rule::requiredIf($evidenceRequired), 'nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:30720'],
            'attachment_metadata' => ['nullable', 'json'],
        ], [
            'summary.required' => 'Judul / ringkasan wajib diisi.',
        ]);

        $validator->after(function ($validator) use ($request, $evidenceRequired) {
            if ($request->filled('opportunity_id')) {
                $opportunityMatchesCustomer = Opportunity::query()
                    ->visibleTo($request->user())
                    ->whereKey($request->input('opportunity_id'))
                    ->where('customer_id', $request->input('customer_id'))
                    ->exists();

                if (! $opportunityMatchesCustomer) {
                    $validator->errors()->add('opportunity_id', 'Opportunity harus berasal dari customer yang dipilih.');
                }
            }

            if ($request->input('type') === 'approval_special_price') {
                if (! $request->filled('opportunity_id')) {
                    $validator->errors()->add('opportunity_id', 'Opportunity wajib dipilih untuk pengajuan Harga Khusus.');
                }
                $selectedItems = collect($request->input('special_price_items', []))
                    ->filter(fn ($row) => ! empty($row['selected']));
                if ($selectedItems->isEmpty()) {
                    $validator->errors()->add('special_price_items', 'Pilih minimal satu produk yang akan diajukan.');
                }
                $validItemIds = OpportunityItem::query()
                    ->where('opportunity_id', $request->input('opportunity_id'))
                    ->whereIn('id', $selectedItems->keys())
                    ->pluck('id')->map(fn ($id) => (string) $id);
                foreach ($selectedItems as $itemId => $row) {
                    if (! $validItemIds->contains((string) $itemId)) {
                        $validator->errors()->add('special_price_items', 'Produk yang dipilih tidak sesuai dengan opportunity.');
                    }
                    if (blank($row['requested_price'] ?? null)) {
                        $validator->errors()->add("special_price_items.$itemId.requested_price", 'Harga khusus yang diajukan wajib diisi.');
                    } elseif (! is_numeric($row['requested_price']) || (float) $row['requested_price'] < 0) {
                        $validator->errors()->add("special_price_items.$itemId.requested_price", 'Harga khusus yang diajukan harus berupa angka yang valid.');
                    }
                    if (blank($row['reason'] ?? null)) {
                        $validator->errors()->add("special_price_items.$itemId.reason", 'Alasan pengajuan produk wajib diisi.');
                    }
                }
            }

            $approvalFields = Activity::APPROVAL_FIELDS[$request->input('type')] ?? null;
            if ($approvalFields) {
                foreach ($approvalFields as $key => $field) {
                    if ($request->input('type') === 'approval_special_price') continue;
                    if (str_starts_with($field['type'], 'computed_')) continue;
                    if ($request->input('type') === 'approval_credit_limit' && $key === 'current_limit') continue;
                    $value = $request->input("approval_details.$key");
                    if ($value === null || $value === '') {
                        $validator->errors()->add("approval_details.$key", $field['label'].' wajib diisi.');
                    } elseif (in_array($field['type'], ['currency', 'number'], true)
                        && (! is_numeric($value) || (float) $value < 0)) {
                        $validator->errors()->add("approval_details.$key", $field['label'].' harus berupa angka yang valid.');
                    }
                }

                $candidateIds = collect($request->input('participant_ids', []))
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->reject(fn ($id) => $id === (int) $request->user()->id);
                $hasOtherApprover = User::query()
                    ->whereIn('id', $candidateIds)
                    ->where('is_active', true)
                    ->where('is_approver', true)
                    ->exists();
                if (! $hasOtherApprover) {
                    $validator->errors()->add('participant_ids', 'Pilih minimal satu approver aktif. Pengaju tidak boleh menyetujui pengajuannya sendiri.');
                }

                if ($request->input('type') === 'approval_credit_limit') {
                    $currentLimit = (float) Customer::query()->whereKey($request->input('customer_id'))->value('credit_limit');
                    $requestedLimit = (float) $request->input('approval_details.requested_limit');
                    if ($requestedLimit <= $currentLimit) {
                        $validator->errors()->add('approval_details.requested_limit', 'Batas kredit baru harus lebih besar dari batas kredit saat ini.');
                    }
                }
            }

            if (! $evidenceRequired) return;

            $hasImage = collect($request->file('attachments', []))
                ->contains(fn ($file) => str_starts_with((string) $file->getMimeType(), 'image/')
                    || in_array(strtolower($file->getClientOriginalExtension()), ['heic', 'heif'], true));

            if (! $hasImage) {
                $validator->errors()->add('attachments', 'Divisi Anda mewajibkan minimal satu bukti berupa gambar.');
            }
        });

        $data = $validator->validate();
        $approvalDetails = null;
        if (in_array($data['type'], Activity::DECISION_TYPES, true)) {
            $allowedApprovalKeys = array_keys(Activity::APPROVAL_FIELDS[$data['type']]);
            $approvalDetails = collect($data['approval_details'] ?? [])
                ->only($allowedApprovalKeys)
                ->map(fn ($value) => is_string($value) ? trim($value) : $value)
                ->all();
            if ($data['type'] === 'approval_credit_limit') {
                $currentLimit = (float) Customer::query()->whereKey($data['customer_id'])->value('credit_limit');
                $approvalDetails['current_limit'] = $currentLimit;
                $outstanding = (float) ($approvalDetails['outstanding_receivables'] ?? 0);
                $newOrder = (float) ($approvalDetails['new_order_value'] ?? 0);
                $approvalDetails['remaining_limit'] = $currentLimit - $outstanding;
                $approvalDetails['over_limit'] = max(0, $newOrder - max(0, $approvalDetails['remaining_limit']));
            }
            if ($data['type'] === 'approval_payment_term') {
                $approvalDetails['additional_days'] = max(
                    0,
                    (int) ($approvalDetails['requested_days'] ?? 0) - (int) ($approvalDetails['current_days'] ?? 0)
                );
            }
            if ($data['type'] === 'approval_special_price') {
                $selected = collect($request->input('special_price_items', []))
                    ->filter(fn ($row) => ! empty($row['selected']));
                $items = OpportunityItem::query()->whereIn('id', $selected->keys())->get()->keyBy('id');
                $approvalDetails['special_price_items'] = $selected->map(function ($row, $itemId) use ($items) {
                    $item = $items->get((int) $itemId);
                    return [
                        'opportunity_item_id' => $item->id,
                        'product_name' => $item->product_name,
                        'quantity' => (int) $item->quantity,
                        'unit' => $item->quantity_unit,
                        'normal_price' => (float) ($item->unit_price ?? 0),
                        'target_price' => (float) ($item->target_price ?? 0),
                        'requested_price' => (float) $row['requested_price'],
                        'reason' => trim((string) $row['reason']),
                        'status' => 'pending',
                        'decision_note' => null,
                    ];
                })->values()->all();
                $first = $approvalDetails['special_price_items'][0];
                $approvalDetails += [
                    'product_name' => $first['product_name'], 'normal_price' => $first['normal_price'],
                    'requested_price' => $first['requested_price'], 'quantity' => $first['quantity'],
                    'unit' => $first['unit'], 'reason' => $first['reason'],
                ];
            }
        }
        unset($data['approval_details'], $data['special_price_items']);
        $completesFollowUpId = $data['completes_follow_up_id'] ?? null;
        $participantIds = collect($data['participant_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $request->user()->id)
            ->unique()
            ->values();
        unset($data['completes_follow_up_id'], $data['attachment_metadata'], $data['attachments'], $data['participant_ids']);
        abort_unless(Customer::visibleTo($request->user())->whereKey($data['customer_id'])->exists(), 403);
        $sourceFollowUp = $completesFollowUpId
            ? Activity::query()->visibleTo($request->user())->whereKey($completesFollowUpId)->firstOrFail()
            : null;
        abort_if($sourceFollowUp && (int) $sourceFollowUp->customer_id !== (int) $data['customer_id'], 422, 'Customer follow-up tidak sesuai.');
        abort_if($sourceFollowUp && $sourceFollowUp->follow_up_completed_at, 422, 'Follow-up ini sudah diselesaikan.');
        $data['user_id'] = $request->user()->id;
        $data['participants'] = $participantIds->all();
        $activity = Activity::create($data);
        if ($approvalDetails) {
            $activity->approvalDetail()->create($approvalDetails);
        }
        $clientMetadata = json_decode((string) $request->input('attachment_metadata', '[]'), true) ?: [];
        foreach ($request->file('attachments', []) as $index => $file) {
            $integrity = $inspector->inspectUpload($file, $clientMetadata[$index] ?? []);
            if ($integrity['sha256'] && Attachment::query()
                ->where(fn ($query) => $query
                    ->where('sha256', $integrity['sha256'])
                    ->orWhere('evidence_metadata->source_sha256', $integrity['sha256']))
                ->exists()) {
                $integrity['verification_status'] = 'duplicate';
                $integrity['verification_notes'][] = 'File identik dengan bukti yang pernah diupload sebelumnya.';
            }
            $path = $file->store('crm/activities', 'public');
            $integrity['evidence_metadata'] = array_merge(
                $integrity['evidence_metadata'] ?? [],
                ['source_sha256' => $integrity['sha256']]
            );
            $integrity['sha256'] = hash_file('sha256', Storage::disk('public')->path($path));
            $attachment = Attachment::create(['attachable_type' => Activity::class, 'attachable_id' => $activity->id, 'uploaded_by' => $request->user()->id, 'name' => $file->getClientOriginalName(), 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()] + $integrity);
            if ($heicPreview = $heicPreviewGenerator->generate($path, $file->getClientOriginalName())) {
                $attachment->update([
                    'captured_at' => $heicPreview['captured_at'],
                    'verification_status' => $heicPreview['captured_at'] ? 'metadata_found' : $attachment->verification_status,
                    'evidence_metadata' => array_merge(
                        $attachment->evidence_metadata ?? [],
                        $heicPreview['metadata'],
                        ['preview_path' => $heicPreview['preview_path'], 'gps_present' => isset($heicPreview['metadata']['gps_latitude'], $heicPreview['metadata']['gps_longitude'])]
                    ),
                ]);
            }
            $aiDetector->analyze($attachment->fresh());
            $previewOptimizer->optimize($attachment->fresh());
            $thumbnailGenerator->generate($attachment->fresh());
        }
        if ($sourceFollowUp && ! $sourceFollowUp->follow_up_completed_at) {
            $sourceFollowUp->update([
                'follow_up_completed_at' => now(),
                'follow_up_completed_by' => $request->user()->id,
                'follow_up_completion_activity_id' => $activity->id,
            ]);
        }
        $activity->customer->update(['last_activity_at' => $activity->occurred_at, 'next_follow_up_at' => $activity->next_follow_up_at]);
        $this->syncOpportunityFromActivity($activity);
        if ($sourceFollowUp) {
            $this->syncCustomerNextFollowUp($activity->customer);
            $this->syncOpportunityNextFollowUp($activity->opportunity);
        }
        foreach ($participantIds as $participantId) {
            $isApprovalRecipient = $approvalDetails && User::query()
                ->whereKey($participantId)
                ->where('is_active', true)
                ->where('is_approver', true)
                ->exists();
            $notifier->send(
                $participantId,
                $isApprovalRecipient ? 'activity_approval_waiting' : 'activity_invitation',
                $isApprovalRecipient ? 'Approval menunggu keputusan' : 'Anda dilibatkan dalam aktivitas',
                $isApprovalRecipient
                    ? $request->user()->name.' mengajukan '.(Activity::TYPES[$activity->type] ?? 'approval').' untuk '.$activity->customer->company_name.'.'
                    : $request->user()->name.' melibatkan Anda pada "'.$activity->summary.'" untuk '.$activity->customer->company_name.'.',
                ($isApprovalRecipient
                    ? route('approvals.index', ['status' => 'pending', 'activity' => $activity->id], false).'#approval-'.$activity->id
                    : route('activities.index', ['activity' => $activity->id], false).'#activity-'.$activity->id),
                ['activity_id' => $activity->id]
            );
        }
        return redirect()->route('customers.show', $activity->customer_id)->with('success', 'Aktivitas berhasil dicatat.');
    }

    public function decideApproval(Request $request, Activity $activity, CrmNotifier $notifier)
    {
        abort_unless(Activity::query()->visibleTo($request->user())->whereKey($activity)->exists(), 403);
        $detail = $activity->approvalDetail;
        abort_unless($detail, 404);
        abort_unless($request->user()->canApprove(), 403);
        abort_if((int) $activity->user_id === (int) $request->user()->id, 403, 'Pengaju tidak boleh memberikan keputusan untuk pengajuannya sendiri.');
        abort_unless($detail->approval_status === 'pending', 422, 'Pengajuan ini sudah diputuskan.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'revision', 'rejected'])],
            'decision_note' => [
                Rule::requiredIf(fn () => $activity->type !== 'approval_special_price'),
                'nullable', 'string', 'max:5000',
            ],
            'captcha_answer' => [
                Rule::requiredIf(fn () => $request->input('decision') === 'approved'),
                'nullable', 'digits:5',
            ],
            'captcha_token' => [
                Rule::requiredIf(fn () => $request->input('decision') === 'approved'),
                'nullable', 'string',
            ],
            'item_decisions' => ['nullable', 'array'],
            'item_decisions.*.decision' => ['required', Rule::in(['approved', 'revision', 'rejected'])],
            'item_decisions.*.note' => ['required', 'string', 'max:2000'],
        ]);

        $specialPriceDecisions = collect();
        if ($activity->type === 'approval_special_price') {
            $storedItems = collect($detail->special_price_items ?? []);
            $submitted = collect($data['item_decisions'] ?? []);
            if ($storedItems->isEmpty() || $submitted->count() !== $storedItems->count()) {
                return back()->withErrors(['item_decisions' => 'Keputusan wajib diberikan untuk seluruh produk.']);
            }
            $specialPriceDecisions = $storedItems->map(function ($item) use ($submitted) {
                $decision = $submitted->get((string) $item['opportunity_item_id']);
                abort_unless($decision, 422, 'Produk pengajuan tidak valid.');
                if (blank($decision['note'] ?? null)) {
                    throw ValidationException::withMessages(['item_decisions' => 'Catatan approver harus diisi untuk setiap produk.']);
                }
                return array_merge($item, ['status' => $decision['decision'], 'decision_note' => trim($decision['note'])]);
            });
            $data['decision'] = $specialPriceDecisions->contains('status', 'revision')
                ? 'revision'
                : ($specialPriceDecisions->contains('status', 'approved') ? 'approved' : 'rejected');
        }

        if ($data['decision'] === 'approved') {
            $rateLimitKey = 'approval-captcha:'.$request->user()->id.':'.$request->ip();

            if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                return back()->withInput($request->except('captcha_answer', 'captcha_token'))->withErrors([
                    'captcha_answer' => "Terlalu banyak percobaan. Coba kembali dalam {$seconds} detik.",
                ]);
            }

            try {
                $challenge = json_decode(Crypt::decryptString($data['captcha_token']), true, flags: JSON_THROW_ON_ERROR);
                $captchaIsValid = (int) ($challenge['activity_id'] ?? 0) === (int) $activity->id
                    && (int) ($challenge['expires_at'] ?? 0) >= now()->timestamp
                    && hash_equals(
                        strtoupper((string) ($challenge['code'] ?? '')),
                        strtoupper(trim((string) $data['captcha_answer']))
                    );
            } catch (DecryptException|\JsonException) {
                $captchaIsValid = false;
            }

            if (! $captchaIsValid) {
                RateLimiter::hit($rateLimitKey, 60);
                return back()->withInput($request->except('captcha_answer', 'captcha_token'))->withErrors([
                    'captcha_answer' => 'Kode verifikasi tidak sesuai atau sudah kedaluwarsa.',
                ]);
            }

            RateLimiter::clear($rateLimitKey);
        }

        DB::transaction(function () use ($activity, $detail, $data, $request, $specialPriceDecisions) {
            $detail->update([
                'approval_status' => $data['decision'],
                'decision_note' => $data['decision_note'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'special_price_items' => $specialPriceDecisions->isNotEmpty() ? $specialPriceDecisions->values()->all() : $detail->special_price_items,
            ]);

            if ($activity->type === 'approval_special_price') {
                foreach ($specialPriceDecisions->where('status', 'approved') as $approvedItem) {
                    $item = OpportunityItem::query()
                        ->whereKey($approvedItem['opportunity_item_id'])
                        ->where('opportunity_id', $activity->opportunity_id)
                        ->lockForUpdate()->firstOrFail();
                    $item->update([
                        'unit_price' => $approvedItem['requested_price'],
                        'subtotal' => (float) $item->quantity * (float) $approvedItem['requested_price'],
                    ]);
                }
                $activity->opportunity?->update([
                    'offered_price' => $activity->opportunity->items()->orderBy('id')->value('unit_price'),
                ]);
            }

            if ($data['decision'] === 'approved'
                && $activity->type === 'approval_credit_limit'
                && $detail->requested_limit !== null) {
                $activity->customer()->update(['credit_limit' => $detail->requested_limit]);
            }
        });

        $labels = ['approved' => 'disetujui', 'revision' => 'perlu diperbaiki', 'rejected' => 'ditolak'];
        $notificationUrl = $data['decision'] === 'revision'
            ? route('approvals.revise', $activity, false)
            : route('approvals.index', ['status' => $data['decision'], 'activity' => $activity->id], false).'#approval-'.$activity->id;
        $notifier->send(
            $activity->user_id,
            'activity_approval_decided',
            'Pengajuan '.$labels[$data['decision']],
            $request->user()->name.' memberikan keputusan untuk "'.$activity->summary.'".',
            $notificationUrl,
            ['activity_id' => $activity->id, 'decision' => $data['decision']]
        );

        return back()->with('success', 'Keputusan approval berhasil disimpan.');
    }

    public function comment(Request $request, Activity $activity, CrmNotifier $notifier)
    {
        abort_unless(Activity::query()->visibleTo($request->user())->whereKey($activity)->exists(), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $comment = $activity->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'mentioned_user_ids' => [],
        ]);

        $recipientIds = collect($activity->participants ?? [])
            ->push($activity->user_id)
            ->unique()
            ->reject(fn ($id) => (int) $id === $request->user()->id);
        foreach ($recipientIds as $recipientId) {
            $notifier->send(
                (int) $recipientId,
                'activity_comment',
                'Komentar baru pada aktivitas',
                $request->user()->name.': '.Str::limit($data['body'], 100),
                route('activities.index', ['activity' => $activity->id], false).'#activity-'.$activity->id,
                ['activity_id' => $activity->id, 'comment_id' => $comment->id]
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->commentPayload($comment->load('user'))], 201);
        }

        return back()->with('success', 'Komentar berhasil dikirim.');
    }

    public function addParticipants(Request $request, Activity $activity, CrmNotifier $notifier)
    {
        abort_unless(Activity::query()->visibleTo($request->user())->whereKey($activity)->exists(), 403);
        $data = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        $existingIds = collect($activity->participants ?? [])->map(fn ($id) => (int) $id);
        $newIds = collect($data['participant_ids'])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $activity->user_id)
            ->diff($existingIds)
            ->unique()
            ->values();

        if ($newIds->isNotEmpty()) {
            $activity->update(['participants' => $existingIds->merge($newIds)->unique()->values()->all()]);

            foreach ($newIds as $participantId) {
                $notifier->send(
                    $participantId,
                    'activity_invitation',
                    'Anda dilibatkan dalam aktivitas',
                    $request->user()->name.' menambahkan Anda ke diskusi "'.$activity->summary.'" untuk '.$activity->customer->company_name.'.',
                    route('activities.index', ['activity' => $activity->id], false).'#activity-'.$activity->id,
                    ['activity_id' => $activity->id]
                );
            }
        }

        return redirect()
            ->route('activities.index', ['activity' => $activity->id])
            ->with('success', $newIds->isEmpty() ? 'Semua akun tersebut sudah dilibatkan.' : $newIds->count().' orang berhasil ditambahkan ke diskusi.');
    }

    public function comments(Request $request, Activity $activity)
    {
        abort_unless(Activity::query()->visibleTo($request->user())->whereKey($activity)->exists(), 403);
        $comments = $activity->comments()
            ->with('user')
            ->where('id', '>', max(0, $request->integer('after_id')))
            ->limit(100)
            ->get();

        return response()->json(['messages' => $comments->map(fn (Comment $comment) => $this->commentPayload($comment))->values()]);
    }

    private function commentPayload(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'user_id' => $comment->user_id,
            'user_name' => $comment->user->name,
            'initial' => mb_substr($comment->user->name, 0, 1),
            'body' => $comment->body,
            'created_at' => $comment->created_at->format('d M Y, H:i'),
        ];
    }

    private function syncOpportunityFromActivity(Activity $activity): void
    {
        if (! $activity->opportunity) {
            return;
        }

        $updates = [
            'last_activity_at' => $activity->occurred_at,
            'next_follow_up_at' => $activity->next_follow_up_at,
        ];

        if (filled($activity->next_action)) {
            $updates['next_action'] = trim($activity->next_action);
        }

        $activity->opportunity->update($updates);
    }

    private function storeActivityAttachments(
        Request $request,
        Activity $activity,
        ImageEvidenceInspector $inspector,
        HeicPreviewGenerator $heicPreviewGenerator,
        AiEvidenceDetector $aiDetector,
        EvidencePreviewOptimizer $previewOptimizer,
        EvidenceThumbnailGenerator $thumbnailGenerator
    ): void {
        $clientMetadata = json_decode((string) $request->input('attachment_metadata', '[]'), true) ?: [];
        foreach ($request->file('attachments', []) as $index => $file) {
            $integrity = $inspector->inspectUpload($file, $clientMetadata[$index] ?? []);
            if ($integrity['sha256'] && Attachment::query()
                ->where(fn ($query) => $query
                    ->where('sha256', $integrity['sha256'])
                    ->orWhere('evidence_metadata->source_sha256', $integrity['sha256']))
                ->exists()) {
                $integrity['verification_status'] = 'duplicate';
                $integrity['verification_notes'][] = 'File identik dengan bukti yang pernah diupload sebelumnya.';
            }
            $path = $file->store('crm/activities', 'public');
            $integrity['evidence_metadata'] = array_merge(
                $integrity['evidence_metadata'] ?? [],
                ['source_sha256' => $integrity['sha256']]
            );
            $integrity['sha256'] = hash_file('sha256', Storage::disk('public')->path($path));
            $attachment = Attachment::create([
                'attachable_type' => Activity::class,
                'attachable_id' => $activity->id,
                'uploaded_by' => $request->user()->id,
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ] + $integrity);
            if ($heicPreview = $heicPreviewGenerator->generate($path, $file->getClientOriginalName())) {
                $attachment->update([
                    'captured_at' => $heicPreview['captured_at'],
                    'verification_status' => $heicPreview['captured_at'] ? 'metadata_found' : $attachment->verification_status,
                    'evidence_metadata' => array_merge(
                        $attachment->evidence_metadata ?? [],
                        $heicPreview['metadata'],
                        ['preview_path' => $heicPreview['preview_path'], 'gps_present' => isset($heicPreview['metadata']['gps_latitude'], $heicPreview['metadata']['gps_longitude'])]
                    ),
                ]);
            }
            $aiDetector->analyze($attachment->fresh());
            $previewOptimizer->optimize($attachment->fresh());
            $thumbnailGenerator->generate($attachment->fresh());
        }
    }

    private function syncCustomerNextFollowUp(Customer $customer): void
    {
        $customer->update([
            'next_follow_up_at' => Activity::query()
                ->where('customer_id', $customer->id)
                ->whereNotNull('next_follow_up_at')
                ->whereNull('follow_up_completed_at')
                ->orderBy('next_follow_up_at')
                ->value('next_follow_up_at'),
        ]);
    }

    private function syncOpportunityNextFollowUp(?Opportunity $opportunity): void
    {
        if (! $opportunity) return;

        $opportunity->update([
            'next_follow_up_at' => Activity::query()
                ->where('opportunity_id', $opportunity->id)
                ->whereNotNull('next_follow_up_at')
                ->whereNull('follow_up_completed_at')
                ->orderBy('next_follow_up_at')
                ->value('next_follow_up_at'),
        ]);
    }
}
