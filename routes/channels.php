<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| USER PRIVATE CHANNEL (CHAT)
|--------------------------------------------------------------------------
| Every authenticated user has their own private channel.
| This is used to deliver 1-to-1 and group messages in real-time.
*/

Broadcast::channel('chat.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});


/*
|--------------------------------------------------------------------------
| ADMIN CHANNEL
|--------------------------------------------------------------------------
*/

Broadcast::channel('chat.admin', function ($user) {
    return $user->role === 'admin';
});


/*
|--------------------------------------------------------------------------
| GROUP CHANNEL
|--------------------------------------------------------------------------
| (Optional) If you want a per-group channel instead of user channels.
*/

Broadcast::channel('chat.group.{groupId}', function ($user, $groupId) {
    return \DB::table('conversation_members')
        ->where('conversation_id', $groupId)
        ->where('user_id', $user->id)
        ->exists();
});


/*
|==========================================================================
| CALLS — Voice / Video (WebRTC signaling via Reverb)
|==========================================================================
| Har user apne `call.user.{id}` channel pe call events receive karta hai.
| `CallSignal` event isi channel pe broadcast karta hai.
|==========================================================================
*/

Broadcast::channel('call.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});


/*
|--------------------------------------------------------------------------
| CALL ROOM CHANNEL (optional)
|--------------------------------------------------------------------------
| Agar aap ek dedicated call room bana kar ICE/SDP signals usi room mein
| bhejna chahte hain (call ke dono participants subscribe karein).
|--------------------------------------------------------------------------
*/

Broadcast::channel('call.room.{callId}', function ($user, $callId) {
    $call = \App\Models\Call::find($callId);

    if (! $call) {
        return false;
    }

    return (int) $call->caller_id === (int) $user->id
        || (int) $call->receiver_id === (int) $user->id;
});