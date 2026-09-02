<?php

namespace App\Services;

use App\Models\Pipeline;

class CustomProductProgress
{
    public function pipeline(): ?Pipeline
    {
        return Pipeline::query()
            ->where('is_active', true)
            ->where('uses_pipeline_for_custom_progress', true)
            ->with('stages')
            ->first();
    }

    public function stages(): array
    {
        return $this->pipeline()?->stages->map(fn ($stage) => ['key' => (string) $stage->id, 'name' => $stage->name])->all() ?? [];
    }

    public function initialStage(): ?string
    {
        return $this->stages()[0]['key'] ?? null;
    }

    public function label(?string $key): string
    {
        foreach ($this->stages() as $stage) if ($stage['key'] === (string) $key) return $stage['name'];
        return $this->stages()[0]['name'] ?? 'Belum ditentukan';
    }
}
