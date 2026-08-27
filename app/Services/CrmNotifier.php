<?php

namespace App\Services;

use App\Models\CrmNotification;
use Illuminate\Support\Str;

class CrmNotifier
{
    public function __construct(private readonly NavigationData $navigationData) {}

    public function send(int $userId, string $type, string $title, string $message, ?string $url = null, array $data = []): CrmNotification
    {
        $notification = CrmNotification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'data' => $data,
        ]);
        $this->navigationData->forget($userId);

        return $notification;
    }
}
