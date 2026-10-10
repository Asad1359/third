<template>
    <Teleport to="body">

        <!-- =========================================================
             INCOMING CALL MODAL
        ========================================================== -->
        <div
            v-if="incomingCall && callStatus === 'ringing'"
            class="call-overlay"
        >
            <div class="incoming-card">

                <div class="caller-avatar">
                    {{ incomingCall.caller?.name?.charAt(0)?.toUpperCase() || '?' }}
                </div>

                <h2>{{ incomingCall.caller?.name || 'Unknown' }}</h2>

                <p class="call-type">
                    {{ incomingCall.type === 'video' ? 'Incoming video call' : 'Incoming audio call' }}
                </p>

                <div class="incoming-actions">

                    <button
                        type="button"
                        class="btn-reject"
                        @click="rejectIncoming"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"/>
                            <line x1="23" y1="1" x2="1" y2="23"/>
                        </svg>
                        <span>Reject</span>
                    </button>

                    <button
                        type="button"
                        class="btn-accept"
                        @click="acceptIncoming"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        <span>Accept</span>
                    </button>

                </div>
            </div>
        </div>


        <!-- =========================================================
             ACTIVE CALL SCREEN
        ========================================================== -->
        <div
            v-if="callStatus === 'calling' || callStatus === 'active'"
            class="call-overlay active-call-overlay"
        >
            <div class="call-screen" :class="{ 'video-mode': isVideoCall }">

                <div class="call-header">

                    <div class="header-info">
                        <span class="status-dot" :class="callStatus"></span>

                        <span class="status-text">
                            {{ callStatus === 'calling' ? 'Calling...' : 'Connected' }}
                        </span>

                        <span
                            v-if="callStatus === 'active'"
                            class="call-timer"
                        >
                            {{ formattedDuration }}
                        </span>
                    </div>

                    <div class="header-title">
                        {{ activeCall?.type === 'video' ? 'Video Call' : 'Audio Call' }}
                    </div>

                </div>


                <div class="call-stage">

                    <div class="remote-container">

                        <video
                            v-if="isVideoCall && remoteStream"
                            ref="remoteVideoEl"
                            autoplay
                            playsinline
                            class="remote-video"
                        ></video>

                        <div
                            v-else
                            class="audio-placeholder"
                        >
                            <div class="audio-avatar">
                                {{ (activeCall?.type === 'video' ? 'V' : 'A') }}
                            </div>

                            <p class="audio-label">
                                {{ callStatus === 'calling' ? 'Ringing...' : 'In call' }}
                            </p>
                        </div>

                    </div>


                    <div
                        v-if="isVideoCall && localStream"
                        class="local-container"
                    >
                        <video
                            ref="localVideoEl"
                            autoplay
                            playsinline
                            muted
                            class="local-video"
                        ></video>
                    </div>

                </div>


                <div class="call-controls">

                    <button
                        type="button"
                        class="ctrl-btn"
                        :class="{ active: isMuted }"
                        @click="toggleMute"
                        :title="isMuted ? 'Unmute' : 'Mute'"
                    >
                        <svg
                            v-if="!isMuted"
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                            <line x1="12" y1="19" x2="12" y2="23"/>
                            <line x1="8" y1="23" x2="16" y2="23"/>
                        </svg>

                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <line x1="1" y1="1" x2="23" y2="23"/>
                            <path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/>
                            <path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"/>
                            <line x1="12" y1="19" x2="12" y2="23"/>
                            <line x1="8" y1="23" x2="16" y2="23"/>
                        </svg>
                    </button>


                    <button
                        v-if="isVideoCall"
                        type="button"
                        class="ctrl-btn"
                        :class="{ active: isVideoOff }"
                        @click="toggleVideo"
                        :title="isVideoOff ? 'Turn on camera' : 'Turn off camera'"
                    >
                        <svg
                            v-if="!isVideoOff"
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <polygon points="23 7 16 12 23 17 23 7"/>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                        </svg>

                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2m5.66 0H14a2 2 0 0 1 2 2v3.34l1 1L23 7v10"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>


                    <button
                        type="button"
                        class="ctrl-btn hangup"
                        @click="hangUp"
                        title="Hang up"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"/>
                            <line x1="23" y1="1" x2="1" y2="23"/>
                        </svg>
                    </button>

                </div>

            </div>
        </div>

    </Teleport>
</template>


<script setup>
import {
    ref,
    computed,
    watch,
    onMounted,
    onUnmounted,
    nextTick,
} from 'vue';

import { useCall } from '../composables/useCall';
import { authState } from '../stores/auth';


/*
|--------------------------------------------------------------------------
| PROPS
|--------------------------------------------------------------------------
*/

const props = defineProps({
    currentUser: {
        type: Object,
        default: () => authState.user,
    },
});


/*
|--------------------------------------------------------------------------
| ECHO INSTANCE
|--------------------------------------------------------------------------
*/

const echo = window.Echo || null;


/*
|--------------------------------------------------------------------------
| COMPOSABLE
|--------------------------------------------------------------------------
*/

const currentUserRef = computed(() => props.currentUser);

const {
    activeCall,
    incomingCall,
    callStatus,
    localStream,
    remoteStream,
    isMuted,
    isVideoOff,
    callDuration,
    startCall,
    acceptIncoming: acceptCallInternal,
    rejectIncoming,
    hangUp,
    toggleMute,
    toggleVideo,
} = useCall({
    currentUser: currentUserRef,
    echo,
});


/*
|--------------------------------------------------------------------------
| DOM REFS
|--------------------------------------------------------------------------
*/

const localVideoEl = ref(null);
const remoteVideoEl = ref(null);


/*
|--------------------------------------------------------------------------
| COMPUTED
|--------------------------------------------------------------------------
*/

const isVideoCall = computed(() => {
    return activeCall.value?.type === 'video'
        || incomingCall.value?.type === 'video';
});

const formattedDuration = computed(() => {
    const total = callDuration.value || 0;
    const m = String(Math.floor(total / 60)).padStart(2, '0');
    const s = String(total % 60).padStart(2, '0');
    return `${m}:${s}`;
});


/*
|--------------------------------------------------------------------------
| ACCEPT (delegates to composable)
|--------------------------------------------------------------------------
*/

async function acceptIncoming() {
    await acceptCallInternal();
}


/*
|--------------------------------------------------------------------------
| BIND VIDEO STREAMS
|--------------------------------------------------------------------------
*/

function bindStreams() {
    nextTick(() => {
        if (localVideoEl.value && localStream.value) {
            localVideoEl.value.srcObject = localStream.value;
        }

        if (remoteVideoEl.value && remoteStream.value) {
            remoteVideoEl.value.srcObject = remoteStream.value;
        }
    });
}


/*
|--------------------------------------------------------------------------
| WATCH STREAMS
|--------------------------------------------------------------------------
*/

watch(localStream, () => {
    bindStreams();
});

watch(remoteStream, () => {
    bindStreams();
});


/*
|--------------------------------------------------------------------------
| LIFECYCLE
|--------------------------------------------------------------------------
*/

onMounted(() => {
    bindStreams();
});

onUnmounted(() => {
    hangUp().catch(() => {});
});


/*
|--------------------------------------------------------------------------
| EXPOSE startCall SO PARENT CAN TRIGGER IT
|--------------------------------------------------------------------------
*/

defineExpose({
    startCall,
    hangUp,
});
</script>


<style scoped>

/* =========================================================
   OVERLAY (shared by incoming + active)
========================================================= */

.call-overlay {
    position: fixed;
    inset: 0;

    z-index: 2147483000;

    display: flex;
    align-items: center;
    justify-content: center;

    background: rgba(17, 24, 39, 0.72);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);

    padding: 20px;
}

.active-call-overlay {
    background: #0b0f17;
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
    padding: 0;
}


/* =========================================================
   INCOMING CALL CARD
========================================================= */

.incoming-card {
    width: 100%;
    max-width: 360px;

    padding: 36px 28px;

    background: #ffffff;
    border-radius: 22px;

    box-shadow:
        0 24px 60px rgba(0, 0, 0, 0.35);

    text-align: center;

    animation: incoming-pop 0.25s ease;
}

@keyframes incoming-pop {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.caller-avatar {
    width: 88px;
    height: 88px;

    margin: 0 auto 18px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: linear-gradient(135deg, #6366f1, #06b6d4);
    color: #ffffff;

    font-size: 34px;
    font-weight: 800;

    box-shadow:
        0 12px 30px rgba(99, 102, 241, 0.35);

    animation: caller-pulse 1.8s infinite;
}

@keyframes caller-pulse {
    0% {
        box-shadow: 0 12px 30px rgba(99, 102, 241, 0.35),
                    0 0 0 0 rgba(99, 102, 241, 0.5);
    }
    70% {
        box-shadow: 0 12px 30px rgba(99, 102, 241, 0.35),
                    0 0 0 18px rgba(99, 102, 241, 0);
    }
    100% {
        box-shadow: 0 12px 30px rgba(99, 102, 241, 0.35),
                    0 0 0 0 rgba(99, 102, 241, 0);
    }
}

.incoming-card h2 {
    margin: 0 0 6px;
    color: #111827;
    font-size: 22px;
    font-weight: 700;
}

.call-type {
    margin: 0 0 28px;
    color: #6b7280;
    font-size: 14px;
}

.incoming-actions {
    display: flex;
    gap: 14px;
    justify-content: center;
}

.btn-reject,
.btn-accept {
    flex: 1;

    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;

    padding: 14px;

    border: none;
    border-radius: 14px;

    font-size: 13px;
    font-weight: 600;

    cursor: pointer;

    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.btn-reject {
    background: #ef4444;
    color: #ffffff;
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}

.btn-accept {
    background: #10b981;
    color: #ffffff;
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
}

.btn-reject:hover,
.btn-accept:hover {
    transform: translateY(-2px);
}


/* =========================================================
   ACTIVE CALL SCREEN
========================================================= */

.call-screen {
    width: 100%;
    height: 100%;

    display: flex;
    flex-direction: column;

    background: #0f172a;
    color: #ffffff;
}


/* Header */

.call-header {
    height: 64px;

    padding: 0 22px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    background: rgba(15, 23, 42, 0.9);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);

    flex-shrink: 0;
}

.header-info {
    display: flex;
    align-items: center;
    gap: 10px;

    font-size: 13px;
}

.status-dot {
    width: 8px;
    height: 8px;

    border-radius: 50%;

    background: #f59e0b;

    animation: dot-blink 1.2s infinite;
}

.status-dot.active {
    background: #10b981;
    animation: none;
}

@keyframes dot-blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.35; }
}

.status-text {
    color: #cbd5e1;
}

.call-timer {
    margin-left: 6px;

    padding: 2px 10px;

    border-radius: 20px;

    background: rgba(255, 255, 255, 0.1);

    font-variant-numeric: tabular-nums;
    font-size: 12px;
    font-weight: 600;
}

.header-title {
    font-size: 14px;
    font-weight: 600;
    color: #94a3b8;
}


/* Stage */

.call-stage {
    position: relative;

    flex: 1 1 auto;
    min-height: 0;

    overflow: hidden;
}

.remote-container {
    width: 100%;
    height: 100%;

    display: flex;
    align-items: center;
    justify-content: center;

    background:
        radial-gradient(
            circle at center,
            #1e293b 0%,
            #0b0f17 75%
        );
}

.remote-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    background: #000;
}

.audio-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}

.audio-avatar {
    width: 110px;
    height: 110px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: linear-gradient(135deg, #6366f1, #06b6d4);
    color: #ffffff;

    font-size: 44px;
    font-weight: 800;

    box-shadow: 0 16px 40px rgba(99, 102, 241, 0.4);
}

.audio-label {
    margin: 0;
    color: #cbd5e1;
    font-size: 14px;
}


/* Local floating video */

.local-container {
    position: absolute;
    top: 20px;
    right: 20px;

    width: 160px;
    height: 220px;

    overflow: hidden;

    border-radius: 14px;

    box-shadow:
        0 10px 30px rgba(0, 0, 0, 0.5);

    border: 2px solid rgba(255, 255, 255, 0.15);

    background: #111827;
}

.local-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}


/* Controls */

.call-controls {
    height: 96px;
    flex-shrink: 0;

    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;

    background: rgba(15, 23, 42, 0.95);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.ctrl-btn {
    width: 56px;
    height: 56px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: none;
    border-radius: 50%;

    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;

    cursor: pointer;

    transition:
        background 0.15s ease,
        transform 0.15s ease;
}

.ctrl-btn:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: scale(1.06);
}

.ctrl-btn.active {
    background: #ef4444;
    color: #ffffff;
}

.ctrl-btn.hangup {
    background: #dc2626;
    color: #ffffff;
    box-shadow: 0 8px 20px rgba(220, 38, 38, 0.4);
}

.ctrl-btn.hangup:hover {
    background: #b91c1c;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 640px) {

    .local-container {
        width: 110px;
        height: 150px;
        top: 14px;
        right: 14px;
    }

    .audio-avatar {
        width: 88px;
        height: 88px;
        font-size: 34px;
    }

    .call-header {
        padding: 0 14px;
    }

    .call-controls {
        height: 84px;
        gap: 12px;
    }

    .ctrl-btn {
        width: 50px;
        height: 50px;
    }
}

</style>