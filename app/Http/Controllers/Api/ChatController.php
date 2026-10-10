<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\ChatConversation;
use App\Models\ChatConversationMember;
use App\Models\ChatMessage;
use App\Models\ChatRating;
use App\Models\ChatBotReply;
use App\Models\User;

use App\Events\MessageSent;
use App\Events\MessageDeleted;
use App\Events\UserTyping;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    /*
    |==========================================================================
    | HELPER
    |==========================================================================
    */

    private function userId()
    {
        $user = Auth::user();
        if (!$user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401));
        }
        return $user->id;
    }

    private function findAdmin()
    {
        return User::where('role', 'admin')->first();
    }

    private function findSupportConversation(int $userId, int $adminId)
    {
        return ChatConversation::query()
            ->where('type', 'private')
            ->whereHas('members', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->whereHas('members', function ($q) use ($adminId) {
                $q->where('user_id', $adminId);
            })
            ->first();
    }

    /*
    |==========================================================================
    | SUPPORT CHAT
    |==========================================================================
    */

    public function supportConversation(Request $request)
    {
        try {
            $userId = $this->userId();
            $admin  = $this->findAdmin();

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No admin available to chat with.',
                ], 404);
            }

            if ($userId === $admin->id) {
                $other = User::where('role', '!=', 'admin')->first();
                if (!$other) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No user to chat with.',
                    ], 404);
                }
                $admin = $other;
            }

            $conversation = $this->findSupportConversation($userId, $admin->id);

            if (!$conversation) {
                DB::beginTransaction();
                try {
                    $conversation = ChatConversation::create([
                        'type'       => 'private',
                        'user_id'    => $userId,
                        'created_by' => $userId,
                    ]);

                    ChatConversationMember::insert([
                        [
                            'conversation_id' => $conversation->id,
                            'user_id'         => $userId,
                            'role'            => 'member',
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ],
                        [
                            'conversation_id' => $conversation->id,
                            'user_id'         => $admin->id,
                            'role'            => 'admin',
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ],
                    ]);

                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Unable to create conversation.',
                        'error'   => $e->getMessage(),
                    ], 500);
                }
            }

            $messages = ChatMessage::where('conversation_id', $conversation->id)
                ->with(['sender', 'parent.sender'])
                ->orderBy('created_at')
                ->get()
                ->map(fn($m) => $this->formatMessage($m));

            ChatMessage::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $userId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success'         => true,
                'conversation'    => [
                    'id'   => $conversation->id,
                    'type' => $conversation->type,
                ],
                'messages'        => $messages,
                'is_blocked'      => (bool) $conversation->is_blocked,
                'blocked_reason'  => $conversation->blocked_reason,
                'support_user_id' => $admin->id,
            ]);

        } catch (\Throwable $e) {
            \Log::error('supportConversation error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function sendSupportMessage(Request $request)
    {
        try {
            $userId = $this->userId();
            $admin  = $this->findAdmin();

            if (!$admin) {
                return response()->json(['success' => false, 'message' => 'No admin available.'], 404);
            }

            if ($userId === $admin->id) {
                $other = User::where('role', '!=', 'admin')->first();
                if ($other) $admin = $other;
            }

            $conversation = $this->findSupportConversation($userId, $admin->id);

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found.',
                ], 404);
            }

            if ($conversation->is_blocked) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have been blocked.',
                    'blocked' => true,
                ], 403);
            }

            $request->validate([
                'message'    => 'nullable|string|max:5000',
                'attachment' => 'nullable|file|max:20480',
                'parent_id'  => 'nullable|integer|exists:chat_messages,id',
            ]);

            if (!$request->filled('message') && !$request->hasFile('attachment')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message or attachment required.',
                ], 422);
            }

            $data = [
                'conversation_id' => $conversation->id,
                'sender_id'       => $userId,
                'sender_type'     => 'user',
                'message'         => $request->input('message'),
                'parent_id'       => $request->input('parent_id'),
            ];

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $path = $file->store('chat/attachments', 'public');
                $mime = $file->getMimeType();

                $data['attachment_path'] = $path;
                $data['attachment_name'] = $file->getClientOriginalName();
                $data['attachment_size'] = $file->getSize();
                $data['attachment_type'] = $mime;
            }

            $message = ChatMessage::create($data);

            // ⭐ Conversation update karein
            $conversation->update([
                'last_message'  => $message->message ?: $message->attachment_name,
                'last_reply_at' => now(),
                'unread_admin'  => $conversation->unread_admin + 1,
            ]);

            $message->load(['sender', 'parent.sender']);
            $payload = $this->formatMessage($message);

            // Broadcast user's message to admin
            try {
                broadcast(new MessageSent($payload, $admin->id))->toOthers();
            } catch (\Throwable $e) {}

            /*
            |------------------------------------------------------------------
            | ⭐ CHATBOT AUTO-REPLY
            |------------------------------------------------------------------
            */
            if ($request->filled('message')) {
                $botReplyText = ChatBotReply::findReply($request->input('message'));

                if ($botReplyText) {
                    $botMessage = ChatMessage::create([
                        'conversation_id' => $conversation->id,
                        'sender_id'       => $admin->id,
                        'sender_type'     => 'admin',
                        'message'         => $botReplyText,
                    ]);

                    $conversation->update([
                        'last_message' => $botReplyText,
                        'last_reply_at' => now(),
                    ]);

                    $botMessage->load(['sender', 'parent.sender']);
                    $botPayload = $this->formatMessage($botMessage);

                    try {
                        broadcast(new MessageSent($botPayload, $userId))->toOthers();
                    } catch (\Throwable $e) {}
                }
            }

            /*
            |------------------------------------------------------------------
            | ⭐ EMAIL NOTIFICATION TO ADMIN
            |------------------------------------------------------------------
            */
            try {
                $this->sendAdminEmailNotification($conversation, $message, $userId);
            } catch (\Throwable $e) {
                \Log::error('Email notification failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $payload,
            ], 201);

        } catch (\Throwable $e) {
            \Log::error('sendSupportMessage error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function markSupportAsRead(Request $request)
    {
        try {
            $userId = $this->userId();
            $admin  = $this->findAdmin();

            if (!$admin) return response()->json(['success' => false], 404);

            if ($userId === $admin->id) {
                $other = User::where('role', '!=', 'admin')->first();
                if ($other) $admin = $other;
            }

            $conversation = $this->findSupportConversation($userId, $admin->id);

            if (!$conversation) return response()->json(['success' => false], 404);

            ChatMessage::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $userId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function supportTyping(Request $request)
    {
        try {
            $userId = $this->userId();
            $admin  = $this->findAdmin();

            if (!$admin) return response()->json(['success' => false], 404);

            if ($userId === $admin->id) {
                $other = User::where('role', '!=', 'admin')->first();
                if ($other) $admin = $other;
            }

            $conversation = $this->findSupportConversation($userId, $admin->id);

            if (!$conversation) return response()->json(['success' => false], 404);

            $user = Auth::user();

            try {
                broadcast(new UserTyping($conversation->id, $user->id, $user->name, $admin->id))->toOthers();
            } catch (\Throwable $e) {}

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => true]);
        }
    }

    public function supportRate(Request $request)
    {
        try {
            $request->validate([
                'rating'   => 'required|integer|min:1|max:5',
                'feedback' => 'nullable|string|max:1000',
            ]);

            $userId = $this->userId();
            $admin  = $this->findAdmin();

            if (!$admin) return response()->json(['success' => false], 404);

            if ($userId === $admin->id) {
                $other = User::where('role', '!=', 'admin')->first();
                if ($other) $admin = $other;
            }

            $conversation = $this->findSupportConversation($userId, $admin->id);

            if (!$conversation) return response()->json(['success' => false], 404);

            $rating = ChatRating::updateOrCreate(
                ['conversation_id' => $conversation->id, 'user_id' => $userId],
                ['rating' => $request->rating, 'feedback' => $request->feedback]
            );

            return response()->json(['success' => true, 'rating' => $rating]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /*
    |==========================================================================
    | 1-TO-1 CONVERSATIONS
    |==========================================================================
    */

    public function conversations(Request $request)
    {
        $userId = $this->userId();

        $conversations = ChatConversation::query()
            ->whereHas('members', fn($q) => $q->where('user_id', $userId))
            ->with([
                'members'  => fn($q) => $q->select('users.id', 'users.name', 'users.email'),
                'messages' => fn($q) => $q->latest()->limit(1),
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($c) use ($userId) {
                $other = $c->members->firstWhere('id', '!=', $userId);
                $lastMessage = $c->messages->first();

                $unread = ChatMessage::where('conversation_id', $c->id)
                    ->where('sender_id', '!=', $userId)
                    ->whereNull('read_at')
                    ->whereNull('deleted_at')
                    ->count();

                return [
                    'id'              => $c->id,
                    'type'            => $c->type,
                    'name'            => $c->type === 'group' ? $c->name : ($other->name ?? 'Unknown'),
                    'other_user'      => $other ? ['id' => $other->id, 'name' => $other->name, 'email' => $other->email] : null,
                    'last_message'    => $lastMessage?->message,
                    'last_message_at' => $lastMessage?->created_at,
                    'unread_count'    => $unread,
                    'updated_at'      => $c->updated_at,
                ];
            });

        return response()->json([
            'success'       => true,
            'conversations' => $conversations,
        ]);
    }

    public function startConversation(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $userId   = $this->userId();
        $targetId = $validated['user_id'];

        if ($userId === $targetId) {
            return response()->json(['success' => false, 'message' => 'Cannot chat with yourself.'], 422);
        }

        $existing = ChatConversation::query()
            ->where('type', 'private')
            ->whereHas('members', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->whereHas('members', function ($q) use ($targetId) {
                $q->where('user_id', $targetId);
            })
            ->first();

        if ($existing) {
            return response()->json([
                'success'      => true,
                'conversation' => ['id' => $existing->id, 'type' => $existing->type],
            ]);
        }

        DB::beginTransaction();
        try {
            $conversation = ChatConversation::create([
                'type'       => 'private',
                'user_id'    => $userId,
                'created_by' => $userId,
            ]);

            ChatConversationMember::insert([
                ['conversation_id' => $conversation->id, 'user_id' => $userId, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()],
                ['conversation_id' => $conversation->id, 'user_id' => $targetId, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()],
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed.', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success'      => true,
            'conversation' => ['id' => $conversation->id, 'type' => $conversation->type],
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $userId = $this->userId();

        $conversation = ChatConversation::query()
            ->where('id', $id)
            ->whereHas('members', fn($q) => $q->where('user_id', $userId))
            ->with(['members' => fn($q) => $q->select('users.id', 'users.name', 'users.email')])
            ->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $messages = ChatMessage::where('conversation_id', $conversation->id)
            ->with(['sender', 'parent.sender'])
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => $this->formatMessage($m));

        return response()->json([
            'success'      => true,
            'conversation' => [
                'id'      => $conversation->id,
                'type'    => $conversation->type,
                'name'    => $conversation->name,
                'members' => $conversation->members->map(fn($m) => [
                    'id'    => $m->id,
                    'name'  => $m->name,
                    'email' => $m->email,
                    'pivot' => ['role' => $m->pivot->role],
                ]),
            ],
            'messages' => $messages,
        ]);
    }

    public function messages(Request $request, $id)
    {
        return $this->show($request, $id);
    }

    public function sendMessage(Request $request, $id)
    {
        $userId = $this->userId();

        $conversation = ChatConversation::query()
            ->where('id', $id)
            ->whereHas('members', fn($q) => $q->where('user_id', $userId))
            ->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $request->validate([
            'message'    => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:20480',
            'parent_id'  => 'nullable|integer|exists:chat_messages,id',
        ]);

        if (!$request->filled('message') && !$request->hasFile('attachment')) {
            return response()->json(['success' => false, 'message' => 'Required.'], 422);
        }

        $data = [
            'conversation_id' => $conversation->id,
            'sender_id'       => $userId,
            'sender_type'     => 'user',
            'message'         => $request->input('message'),
            'parent_id'       => $request->input('parent_id'),
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('chat/attachments', 'public');
            $mime = $file->getMimeType();

            $data['attachment_path'] = $path;
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_size'] = $file->getSize();
            $data['attachment_type'] = $mime;
        }

        $message = ChatMessage::create($data);

        $conversation->update([
            'last_message'  => $message->message ?: $message->attachment_name,
            'last_reply_at' => now(),
        ]);

        $message->load(['sender', 'parent.sender']);
        $payload = $this->formatMessage($message);

        $memberIds = ChatConversationMember::where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $userId)
            ->pluck('user_id');

        foreach ($memberIds as $memberId) {
            try {
                broadcast(new MessageSent($payload, $memberId))->toOthers();
            } catch (\Throwable $e) {}
        }

        return response()->json(['success' => true, 'message' => $payload], 201);
    }

    public function markAsRead(Request $request, $id)
    {
        $userId = $this->userId();

        $conversation = ChatConversation::query()
            ->where('id', $id)
            ->whereHas('members', fn($q) => $q->where('user_id', $userId))
            ->first();

        if (!$conversation) return response()->json(['success' => false], 404);

        ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        ChatConversationMember::where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function typing(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false], 401);

        $conversation = ChatConversation::query()
            ->where('id', $id)
            ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->first();

        if (!$conversation) return response()->json(['success' => false], 404);

        $memberIds = ChatConversationMember::where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)
            ->pluck('user_id');

        foreach ($memberIds as $memberId) {
            try {
                broadcast(new UserTyping($conversation->id, $user->id, $user->name, $memberId))->toOthers();
            } catch (\Throwable $e) {}
        }

        return response()->json(['success' => true]);
    }

    public function rate(Request $request, $id)
    {
        $validated = $request->validate([
            'rating'   => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $userId = $this->userId();

        $conversation = ChatConversation::query()
            ->where('id', $id)
            ->whereHas('members', fn($q) => $q->where('user_id', $userId))
            ->first();

        if (!$conversation) return response()->json(['success' => false], 404);

        $rating = ChatRating::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $userId],
            ['rating' => $validated['rating'], 'feedback' => $validated['feedback'] ?? null]
        );

        return response()->json(['success' => true, 'rating' => $rating]);
    }

    /*
    |==========================================================================
    | SHARED — MESSAGES
    |==========================================================================
    */

    public function deleteMessage(Request $request, $id)
    {
        $userId = $this->userId();

        $message = ChatMessage::find($id);
        if (!$message) return response()->json(['success' => false, 'message' => 'Not found.'], 404);

        $isMember = ChatConversationMember::where('conversation_id', $message->conversation_id)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

        if ($message->sender_id !== $userId) {
            return response()->json(['success' => false, 'message' => 'Can only delete your own messages.'], 403);
        }

        $message->delete();

        $memberIds = ChatConversationMember::where('conversation_id', $message->conversation_id)
            ->where('user_id', '!=', $userId)
            ->pluck('user_id');

        foreach ($memberIds as $memberId) {
            try {
                broadcast(new MessageDeleted($message->id, $memberId))->toOthers();
            } catch (\Throwable $e) {}
        }

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    public function toggleStar(Request $request, $id)
    {
        $userId = $this->userId();

        $message = ChatMessage::find($id);
        if (!$message) return response()->json(['success' => false, 'message' => 'Not found.'], 404);

        $isMember = ChatConversationMember::where('conversation_id', $message->conversation_id)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

        $message->is_starred = !$message->is_starred;
        $message->save();

        return response()->json([
            'success'    => true,
            'is_starred' => (bool) $message->is_starred,
        ]);
    }

    public function unreadCount(Request $request)
    {
        $userId = $this->userId();

        $conversationIds = ChatConversationMember::where('user_id', $userId)
            ->pluck('conversation_id');

        $count = ChatMessage::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->whereNull('deleted_at')
            ->count();

        return response()->json(['success' => true, 'count' => $count]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $userId = $this->userId();

        $conversationIds = ChatConversationMember::where('user_id', $userId)
            ->pluck('conversation_id');

        $results = ChatMessage::query()
            ->whereIn('conversation_id', $conversationIds)
            ->whereNull('deleted_at')
            ->where('message', 'like', '%' . $validated['q'] . '%')
            ->with(['sender', 'conversation'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($m) => [
                'id'              => $m->id,
                'conversation_id' => $m->conversation_id,
                'conversation'    => $m->conversation ? [
                    'id'   => $m->conversation->id,
                    'type' => $m->conversation->type,
                    'name' => $m->conversation->name,
                ] : null,
                'sender'          => $m->sender ? ['id' => $m->sender->id, 'name' => $m->sender->name] : null,
                'message'         => $m->message,
                'created_at'      => $m->created_at,
            ]);

        return response()->json(['success' => true, 'messages' => $results]);
    }

    /*
    |==========================================================================
    | HELPERS
    |==========================================================================
    */

    /**
     * ⭐ Admin ko email notification bhejein
     */
    protected function sendAdminEmailNotification($conversation, $message, $userId)
    {
        $admin = $this->findAdmin();
        $user  = User::find($userId);

        if (!$admin || !$admin->email) {
            return;
        }

        $dashboardUrl = url('/admin/chats');
        $userName     = $user?->name ?? 'User';
        $userEmail    = $user?->email ?? 'N/A';
        $messageText  = $message->message ?? '[Attachment]';
        $sentAt       = now()->format('d M Y, h:i A');

        $subject = "New Support Message from {$userName}";

        $body = "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2 style='color: #4f46e5;'>New Support Message</h2>
                <p><strong>User:</strong> {$userName} ({$userEmail})</p>
                <p><strong>Message:</strong> {$messageText}</p>
                <p><strong>Time:</strong> {$sentAt}</p>
                <p style='margin-top: 20px;'>
                    <a href='{$dashboardUrl}' style='
                        display: inline-block;
                        padding: 12px 24px;
                        background: #4f46e5;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 6px;
                        font-weight: bold;
                    '>
                        Open Admin Dashboard
                    </a>
                </p>
            </div>
        ";

        try {
            Mail::html($body, function ($mail) use ($admin, $subject) {
                $mail->to($admin->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            \Log::error('Admin email failed: ' . $e->getMessage());
        }
    }

    protected function formatMessage(ChatMessage $message)
    {
        return [
            'id'              => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id'       => $message->sender_id,
            'sender_type'     => $message->sender_type,
            'sender'          => $message->sender ? [
                'id'   => $message->sender->id,
                'name' => $message->sender->name,
            ] : null,
            'message'         => $message->message,
            'attachment_url'  => $message->attachment_path
                ? Storage::disk('public')->url($message->attachment_path)
                : null,
            'attachment_name' => $message->attachment_name,
            'attachment_size' => $message->attachment_size,
            'attachment_type' => $message->attachment_type,
            'is_image'        => (bool) $message->is_image,
            'is_video'        => (bool) $message->is_video,
            'is_audio'        => (bool) $message->is_audio,
            'is_starred'      => (bool) $message->is_starred,
            'read_at'         => $message->read_at,
            'deleted_at'      => $message->deleted_at,
            'parent'          => $message->parent ? [
                'id'              => $message->parent->id,
                'sender_type'     => $message->parent->sender_type,
                'message'         => $message->parent->message,
                'attachment_name' => $message->parent->attachment_name,
                'sender'          => $message->parent->sender ? [
                    'id'   => $message->parent->sender->id,
                    'name' => $message->parent->sender->name,
                ] : null,
            ] : null,
            'created_at'      => $message->created_at,
        ];
    }
}