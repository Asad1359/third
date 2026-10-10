<?php

namespace App\Http\Controllers\Api;

use App\Events\CallSignal;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | HELPER — find private conversation between two users
    |--------------------------------------------------------------------------
    */
    private function findConversation(int $userA, int $userB): ?ChatConversation
    {
        return ChatConversation::query()
            ->where('type', 'private')
            ->whereHas('members', fn($q) => $q->where('user_id', $userA))
            ->whereHas('members', fn($q) => $q->where('user_id', $userB))
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER — insert call log system message + broadcast
    |--------------------------------------------------------------------------
    */
    private function insertCallLogMessage(
        int $callerId,
        int $receiverId,
        string $title,
        string $direction = 'outgoing',
        ?int $duration = null,
        ?string $callType = 'audio'
    ): void {
        $conversation = $this->findConversation($callerId, $receiverId);

        if (!$conversation) {
            return;
        }

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $callerId,
            'sender_type'     => 'system',
            'message'         => $title,
            'call_direction'  => $direction,
            'call_duration'   => $duration,
            'call_type'       => $callType,
        ]);

        $conversation->update([
            'last_message'  => $title,
            'last_reply_at' => now(),
        ]);

        $payload = [
            'id'              => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id'       => $message->sender_id,
            'sender_type'     => 'system',
            'sender'          => null,
            'message'         => $title,
            'attachment_url'  => null,
            'attachment_name' => null,
            'attachment_size' => null,
            'attachment_type' => null,
            'is_image'        => false,
            'is_video'        => false,
            'is_audio'        => false,
            'is_starred'      => false,
            'read_at'         => null,
            'deleted_at'      => null,
            'parent'          => null,
            'call_direction'  => $direction,
            'call_duration'   => $duration,
            'call_type'       => $callType,
            'created_at'      => $message->created_at,
        ];

        foreach ([$callerId, $receiverId] as $uid) {
            try {
                broadcast(new MessageSent($payload, $uid));
            } catch (\Throwable $e) {
                \Log::warning('Call log broadcast failed', [
                    'uid' => $uid,
                    'err' => $e->getMessage(),
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | START
    |--------------------------------------------------------------------------
    */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'type'        => 'required|in:audio,video',
        ]);

        $caller = $request->user();

        if ((int) $data['receiver_id'] === $caller->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot call yourself.',
            ], 422);
        }

        Call::where('caller_id', $caller->id)
            ->where('status', 'ringing')
            ->update(['status' => 'missed', 'ended_at' => now()]);

        $call = Call::create([
            'caller_id'   => $caller->id,
            'receiver_id' => $data['receiver_id'],
            'type'        => $data['type'],
            'status'      => 'ringing',
            'started_at'  => now(),
        ]);

        // ⭐ Call log: outgoing "Voice call" / "Video call"
        $this->insertCallLogMessage(
            $caller->id,
            (int) $data['receiver_id'],
            $data['type'] === 'video' ? 'Video call' : 'Voice call',
            'outgoing',
            null,
            $data['type']
        );

        broadcast(new CallSignal(
            receiverId: (int) $data['receiver_id'],
            event: 'call.started',
            payload: [
                'call_id'   => $call->id,
                'type'      => $call->type,
                'caller'    => [
                    'id'     => $caller->id,
                    'name'   => $caller->name,
                    'avatar' => $caller->avatar ?? null,
                ],
            ]
        ))->toOthers();

        return response()->json([
            'success' => true,
            'call'    => $call,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCEPT
    |--------------------------------------------------------------------------
    */
    public function accept(Request $request, int $id): JsonResponse
    {
        $call = Call::findOrFail($id);

        if ($call->receiver_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if ($call->status !== 'ringing') {
            return response()->json(['success' => false, 'message' => 'Call is not ringing.'], 409);
        }

        $call->update([
            'status'      => 'accepted',
            'answered_at' => now(),
        ]);

        broadcast(new CallSignal(
            receiverId: $call->caller_id,
            event: 'call.accepted',
            payload: ['call_id' => $call->id]
        ))->toOthers();

        return response()->json(['success' => true, 'call' => $call->fresh()]);
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT
    |--------------------------------------------------------------------------
    */
    public function reject(Request $request, int $id): JsonResponse
    {
        $call = Call::findOrFail($id);

        if ($call->receiver_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $call->update(['status' => 'rejected', 'ended_at' => now()]);

        broadcast(new CallSignal(
            receiverId: $call->caller_id,
            event: 'call.rejected',
            payload: ['call_id' => $call->id]
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | END
    |--------------------------------------------------------------------------
    */
    public function end(Request $request, int $id): JsonResponse
    {
        $call = Call::findOrFail($id);
        $user = $request->user();

        if ($call->caller_id !== $user->id && $call->receiver_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $duration = $call->answered_at
            ? now()->diffInSeconds($call->answered_at)
            : 0;

        $call->update([
            'status'   => 'ended',
            'ended_at' => now(),
            'duration' => $duration,
        ]);

        // ⭐ Call log: outgoing with duration
        $this->insertCallLogMessage(
            $call->caller_id,
            $call->receiver_id,
            $call->type === 'video' ? 'Video call' : 'Voice call',
            'outgoing',
            $duration,
            $call->type
        );

        $otherId = $call->caller_id === $user->id
            ? $call->receiver_id
            : $call->caller_id;

        broadcast(new CallSignal(
            receiverId: $otherId,
            event: 'call.ended',
            payload: ['call_id' => $call->id, 'duration' => $duration]
        ))->toOthers();

        return response()->json(['success' => true, 'call' => $call->fresh()]);
    }

    /*
    |--------------------------------------------------------------------------
    | SIGNAL
    |--------------------------------------------------------------------------
    */
    public function signal(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'signal' => 'required|array',
        ]);

        $call = Call::findOrFail($id);
        $user = $request->user();

        if ($call->caller_id !== $user->id && $call->receiver_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $otherId = $call->caller_id === $user->id
            ? $call->receiver_id
            : $call->caller_id;

        broadcast(new CallSignal(
            receiverId: $otherId,
            event: 'call.signal',
            payload: [
                'call_id' => $call->id,
                'from'    => $user->id,
                'signal'  => $data['signal'],
            ]
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(Request $request): JsonResponse
    {
        $calls = Call::with(['caller:id,name', 'receiver:id,name'])
            ->where(function ($q) use ($request) {
                $q->where('caller_id', $request->user()->id)
                  ->orWhere('receiver_id', $request->user()->id);
            })
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'calls' => $calls]);
    }
}