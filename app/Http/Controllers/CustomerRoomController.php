<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CrmNotification;
use App\Models\Customer;
use App\Models\CustomerRoom;
use App\Models\RoomMember;
use App\Models\User;
use App\Services\CrmNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerRoomController extends Controller
{
    public function show(CustomerRoom $room)
    {
        $this->authorizeRoom($room);
        return redirect()->to(route('customers.show', $room->customer_id).'#collaboration');
    }

    public function invite(Request $request, CustomerRoom $room, CrmNotifier $notifier)
    {
        $this->authorizeRoom($room);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'access_level' => ['required', 'in:owner,editor,contributor,commenter,viewer'], 'expires_at' => ['nullable', 'date']]);
        RoomMember::updateOrCreate(['customer_room_id' => $room->id, 'user_id' => $data['user_id']], $data + ['invited_by' => $request->user()->id]);
        $notifier->send($data['user_id'], 'room_invitation', 'Akses customer dibagikan', 'Anda mendapat akses ke customer '.$room->customer->company_name, route('customers.show', $room->customer_id).'#collaboration');
        return back()->with('success', 'Akses customer berhasil dibagikan.');
    }

    public function comment(Request $request, CustomerRoom $room, CrmNotifier $notifier)
    {
        $this->authorizeRoom($room);
        $data = $request->validate(['body' => ['required'], 'mentioned_user_ids' => ['nullable', 'array'], 'mentioned_user_ids.*' => ['exists:users,id']]);
        $comment = Comment::create(['commentable_type' => CustomerRoom::class, 'commentable_id' => $room->id, 'user_id' => $request->user()->id, 'body' => $data['body'], 'mentioned_user_ids' => $data['mentioned_user_ids'] ?? []]);

        $mentionedIds = collect($data['mentioned_user_ids'] ?? []);
        $recipientIds = $room->members()
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('user_id')->push($room->owner_id)->unique()->reject(fn ($id) => (int) $id === $request->user()->id);

        foreach ($recipientIds as $userId) {
            $mentioned = $mentionedIds->contains((int) $userId);
            $notifier->send(
                (int) $userId,
                $mentioned ? 'mention' : 'room_message',
                $mentioned ? 'Anda disebut dalam diskusi customer' : 'Diskusi baru pada '.$room->customer->company_name,
                $request->user()->name.': '.Str::limit($data['body'], 100),
                route('customers.show', $room->customer_id).'#collaboration',
                ['room_id' => $room->id, 'comment_id' => $comment->id]
            );
        }

        if ($request->expectsJson()) return response()->json(['message' => $this->messagePayload($comment->load('user'))], 201);

        return back()->with('success', 'Komentar diskusi ditambahkan.');
    }

    public function messages(Request $request, CustomerRoom $room)
    {
        $this->authorizeRoom($room);
        $afterId = max(0, (int) $request->query('after_id', 0));
        $comments = Comment::query()->with('user')
            ->where('commentable_type', CustomerRoom::class)
            ->where('commentable_id', $room->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')->limit(100)->get();

        return response()->json(['messages' => $comments->map(fn (Comment $comment) => $this->messagePayload($comment))->values()]);
    }

    private function authorizeRoom(CustomerRoom $room): void
    {
        $user = auth()->user();
        abort_unless($user->isMasterAdmin() || $room->owner_id === $user->id || $room->members()->where('user_id', $user->id)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists() || Customer::visibleTo($user)->whereKey($room->customer_id)->exists(), 403);
    }

    private function messagePayload(Comment $comment): array
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
}
