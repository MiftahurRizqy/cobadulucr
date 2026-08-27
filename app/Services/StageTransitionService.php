<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\PipelineStage;
use Illuminate\Validation\ValidationException;

class StageTransitionService
{
    public function move(Opportunity $opportunity, PipelineStage $target, ?string $reason, int $userId, ?string $lostReason = null): void
    {
        abort_unless($target->pipeline_id === $opportunity->pipeline_id, 422, 'Stage tidak termasuk pipeline ini.');

        $missing = [];
        foreach ($target->rules()->where('is_mandatory', true)->get() as $rule) {
            $valid = match ($rule->rule_type) {
                'field' => $this->fieldIsFilled($opportunity, $rule->field_key),
                'note' => filled($reason),
                'follow_up' => filled($opportunity->next_follow_up_at)
                    || $opportunity->activities()
                        ->whereNotNull('next_follow_up_at')
                        ->whereNull('follow_up_completed_at')
                        ->exists(),
                'reason' => filled($reason),
                'task' => $opportunity->tasks()->where('status', 'done')->exists(),
                // Approval lama tidak lagi menghambat pipeline. Keputusan dicatat sebagai aktivitas.
                'approval' => true,
                'file' => $opportunity->activities()->whereHas('attachments')->exists(),
                default => true,
            };

            if (! $valid) {
                $missing[] = $rule->label;
            }
        }

        if ($missing) {
            throw ValidationException::withMessages(['stage' => 'Stage belum dapat dipindahkan. Lengkapi: '.implode(', ', $missing).'.']);
        }

        $from = $opportunity->pipeline_stage_id;
        $opportunity->update([
            'pipeline_stage_id' => $target->id,
            'probability' => $target->probability,
            'stage_entered_at' => now(),
            'status' => $target->is_won ? 'won' : ($target->is_lost ? 'lost' : 'open'),
            'lost_reason' => $target->is_lost ? $lostReason : null,
            'lost_reason_detail' => $target->is_lost ? $reason : null,
        ]);

        OpportunityStageHistory::create([
            'opportunity_id' => $opportunity->id,
            'from_stage_id' => $from,
            'to_stage_id' => $target->id,
            'changed_by' => $userId,
            'reason' => $reason,
            'validation_snapshot' => [
                'passed' => true,
                'rules' => $target->rules->pluck('label'),
                'lost_reason' => $target->is_lost ? $lostReason : null,
            ],
        ]);
    }

    private function fieldIsFilled(Opportunity $opportunity, ?string $fieldKey): bool
    {
        if ($fieldKey === 'product_name') {
            return $opportunity->items()->exists() || filled($opportunity->product_name);
        }

        return filled(data_get($opportunity, $fieldKey));
    }
}
