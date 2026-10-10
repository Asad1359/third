import api from './api';

/*
|--------------------------------------------------------------------------
| CALL HISTORY
|--------------------------------------------------------------------------
| Current user ki saari calls (caller ya receiver) — paginated.
*/

export const getCallHistory = async (page = 1) => {
    return api.get('/calls', {
        params: { page },
    });
};

/*
|--------------------------------------------------------------------------
| START CALL
|--------------------------------------------------------------------------
| Naya call initiate karta hai (caller side).
|
| @param {number} receiverId  Jis user ko call karni hai
| @param {string} type        'audio' | 'video'
*/

export const startCall = async (receiverId, type = 'audio') => {
    return api.post('/calls/start', {
        receiver_id: receiverId,
        type,
    });
};

/*
|--------------------------------------------------------------------------
| ACCEPT CALL
|--------------------------------------------------------------------------
| Incoming ringing call ko accept karta hai (receiver side).
*/

export const acceptCall = async (callId) => {
    return api.post(`/calls/${callId}/accept`);
};

/*
|--------------------------------------------------------------------------
| REJECT CALL
|--------------------------------------------------------------------------
| Incoming ringing call ko reject karta hai (receiver side).
*/

export const rejectCall = async (callId) => {
    return api.post(`/calls/${callId}/reject`);
};

/*
|--------------------------------------------------------------------------
| END CALL
|--------------------------------------------------------------------------
| Active call ko khatam karta hai (either side).
*/

export const endCall = async (callId) => {
    return api.post(`/calls/${callId}/end`);
};

/*
|--------------------------------------------------------------------------
| SEND SIGNAL
|--------------------------------------------------------------------------
| WebRTC signaling — SDP offer/answer, ICE candidates.
| Reverb ke through dusre peer tak relay hota hai.
|
| @param {number} callId
| @param {Object} signal  { type: 'offer'|'answer'|'ice', sdp?, candidate? }
*/

export const sendSignal = async (callId, signal) => {
    return api.post(`/calls/${callId}/signal`, { signal });
};

/*
|--------------------------------------------------------------------------
| DEFAULT EXPORT
|--------------------------------------------------------------------------
*/

export default {
    getCallHistory,
    startCall,
    acceptCall,
    rejectCall,
    endCall,
    sendSignal,
};