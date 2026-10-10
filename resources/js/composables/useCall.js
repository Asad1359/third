import { ref, onMounted, onUnmounted } from 'vue';
import {
    startCall as apiStartCall,
    acceptCall as apiAcceptCall,
    rejectCall as apiRejectCall,
    endCall as apiEndCall,
    sendSignal,
} from '../services/calls';

export function useCall({ currentUser, echo }) {
    const activeCall     = ref(null);
    const incomingCall   = ref(null);
    const callStatus     = ref('idle');
    const localStream    = ref(null);
    const remoteStream   = ref(null);
    const peer           = ref(null);
    const isMuted        = ref(false);
    const isVideoOff     = ref(false);
    const callDuration   = ref(0);

    let durationTimer = null;
    let channel = null;

    const iceServers = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
        ],
    };

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    // Payload may contain `call_id` (from CallSignal) OR `id` (from Call model).
    function resolveCallId(call) {
        if (!call) return undefined;
        return call.call_id ?? call.id ?? undefined;
    }

    /*
    |--------------------------------------------------------------------------
    | MEDIA
    |--------------------------------------------------------------------------
    */

    async function getLocalStream(type) {
        const constraints = type === 'video'
            ? { audio: true, video: true }
            : { audio: true, video: false };

        localStream.value = await navigator.mediaDevices.getUserMedia(constraints);
        return localStream.value;
    }

    function stopLocalStream() {
        if (localStream.value) {
            localStream.value.getTracks().forEach((track) => track.stop());
            localStream.value = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PEER CONNECTION
    |--------------------------------------------------------------------------
    */

    function createPeer(callId) {
        const pc = new RTCPeerConnection(iceServers);

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                sendSignal(callId, {
                    type: 'ice',
                    candidate: event.candidate,
                }).catch(() => {});
            }
        };

        pc.ontrack = (event) => {
            remoteStream.value = event.streams[0];
        };

        pc.onconnectionstatechange = () => {
            if (['failed', 'disconnected', 'closed'].includes(pc.connectionState)) {
                // optional auto-hangup
            }
        };

        if (localStream.value) {
            localStream.value.getTracks().forEach((track) => {
                pc.addTrack(track, localStream.value);
            });
        }

        peer.value = pc;
        return pc;
    }

    function closePeer() {
        if (peer.value) {
            peer.value.close();
            peer.value = null;
        }
        remoteStream.value = null;
    }

    /*
    |--------------------------------------------------------------------------
    | DURATION TIMER
    |--------------------------------------------------------------------------
    */

    function startDurationTimer() {
        callDuration.value = 0;
        stopDurationTimer();
        durationTimer = setInterval(() => {
            callDuration.value += 1;
        }, 1000);
    }

    function stopDurationTimer() {
        if (durationTimer) {
            clearInterval(durationTimer);
            durationTimer = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OUTGOING CALL
    |--------------------------------------------------------------------------
    */

    async function startCall(receiverId, type = 'audio') {
        try {
            const { data } = await apiStartCall(receiverId, type);
            if (!data.success) return;

            activeCall.value = data.call;
            callStatus.value = 'calling';

            await getLocalStream(type);
            const pc = createPeer(data.call.id);

            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);

            await sendSignal(data.call.id, {
                type: 'offer',
                sdp: offer,
            });
        } catch (err) {
            console.error('startCall failed:', err);
            await hangUp();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INCOMING CALL
    |--------------------------------------------------------------------------
    */

    async function acceptIncoming() {
        const call = incomingCall.value;
        if (!call) return;

        const callId = resolveCallId(call);

        if (!callId) {
            console.error('acceptIncoming: missing call id', call);
            return;
        }

        try {
            await apiAcceptCall(callId);

            activeCall.value   = { ...call, id: callId };
            callStatus.value   = 'active';
            incomingCall.value = null;

            await getLocalStream(call.type || 'audio');
            createPeer(callId);
            startDurationTimer();
        } catch (err) {
            console.error('acceptIncoming failed:', err);
            await rejectIncoming();
        }
    }

    async function rejectIncoming() {
        if (!incomingCall.value) return;

        const callId = resolveCallId(incomingCall.value);

        if (!callId) {
            console.error('rejectIncoming: missing call id', incomingCall.value);
            incomingCall.value = null;
            callStatus.value   = 'idle';
            return;
        }

        try {
            await apiRejectCall(callId);
        } catch (err) {
            console.error('rejectIncoming failed:', err);
        } finally {
            incomingCall.value = null;
            callStatus.value   = 'idle';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HANGUP / CLEANUP
    |--------------------------------------------------------------------------
    */

    async function hangUp() {
        const callId = resolveCallId(activeCall.value);

        if (callId) {
            try {
                await apiEndCall(callId);
            } catch (err) {
                // ignore
            }
        }

        closePeer();
        stopLocalStream();
        stopDurationTimer();

        activeCall.value   = null;
        incomingCall.value = null;
        callStatus.value   = 'idle';
        isMuted.value      = false;
        isVideoOff.value   = false;
        callDuration.value = 0;
    }

    /*
    |--------------------------------------------------------------------------
    | WEBRTC SIGNALING
    |--------------------------------------------------------------------------
    */

    async function handleSignal(payload) {
        const pc = peer.value;
        if (!pc) return;

        const { signal, call_id } = payload;

        const callId = call_id ?? resolveCallId(activeCall.value);
        if (!callId) {
            console.error('handleSignal: missing call id', payload);
            return;
        }

        try {
            if (signal.type === 'offer') {
                await pc.setRemoteDescription(new RTCSessionDescription(signal.sdp));
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                await sendSignal(callId, {
                    type: 'answer',
                    sdp: answer,
                });
            } else if (signal.type === 'answer') {
                await pc.setRemoteDescription(new RTCSessionDescription(signal.sdp));
                startDurationTimer();
            } else if (signal.type === 'ice') {
                if (signal.candidate) {
                    await pc.addIceCandidate(new RTCIceCandidate(signal.candidate));
                }
            }
        } catch (err) {
            console.error('handleSignal failed:', err);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONTROLS
    |--------------------------------------------------------------------------
    */

    function toggleMute() {
        if (!localStream.value) return;
        const audio = localStream.value.getAudioTracks()[0];
        if (audio) {
            audio.enabled  = !audio.enabled;
            isMuted.value  = !audio.enabled;
        }
    }

    function toggleVideo() {
        if (!localStream.value) return;
        const video = localStream.value.getVideoTracks()[0];
        if (video) {
            video.enabled   = !video.enabled;
            isVideoOff.value = !video.enabled;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REVERB LISTENERS
    |--------------------------------------------------------------------------
    */

    function bindChannel() {
        if (!echo || !currentUser?.value?.id) return;

        channel = echo.private(`call.user.${currentUser.value.id}`);

        channel.listen('.call.started', (payload) => {
            if (callStatus.value !== 'idle') return;
            incomingCall.value = payload;
            callStatus.value   = 'ringing';
        });

        channel.listen('.call.accepted', () => {
            callStatus.value = 'active';
            startDurationTimer();
        });

        channel.listen('.call.rejected', () => {
            hangUp();
        });

        channel.listen('.call.ended', () => {
            hangUp();
        });

        channel.listen('.call.signal', (payload) => {
            handleSignal(payload);
        });
    }

    function unbindChannel() {
        if (echo && currentUser?.value?.id) {
            echo.leave(`call.user.${currentUser.value.id}`);
        }
        channel = null;
    }

    /*
    |--------------------------------------------------------------------------
    | LIFECYCLE
    |--------------------------------------------------------------------------
    */

    onMounted(() => {
        bindChannel();
    });

    onUnmounted(() => {
        unbindChannel();
        hangUp();
    });

    /*
    |--------------------------------------------------------------------------
    | EXPORTS
    |--------------------------------------------------------------------------
    */

    return {
        activeCall,
        incomingCall,
        callStatus,
        localStream,
        remoteStream,
        isMuted,
        isVideoOff,
        callDuration,
        startCall,
        acceptIncoming,
        rejectIncoming,
        hangUp,
        toggleMute,
        toggleVideo,
    };
}