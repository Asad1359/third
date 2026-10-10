<template>
    <Teleport to="body">
        <div class="chat-widget">

            <!-- Floating Button -->
            <button
                class="chat-button"
                :class="{ hidden: isOpen }"
                @click="toggleChat"
                aria-label="Open chat"
            >
                <span class="chat-icon">💬</span>

                <span
                    v-if="unreadCount > 0 && !isOpen"
                    class="unread-badge"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </button>


            <!-- Chat Window -->
            <div v-if="isOpen" class="chat-window">

                <!-- Header -->
                <div class="chat-header">
                    <div>
                        <h3>Support Chat</h3>
                        <span class="online-status">
                            ● {{ connected ? 'Live' : 'Online' }}
                        </span>
                    </div>

                    <div class="header-actions">

                        <!-- 📞 CALL SUPPORT -->
                        <button
                            class="header-icon-btn"
                            @click="callSupport"
                            :disabled="!supportUserId"
                            :title="supportUserId ? 'Call support' : 'Support not available'"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                        </button>

                        <button
                            class="header-icon-btn"
                            @click="showSearch = !showSearch"
                            title="Search"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                        </button>

                        <button class="header-icon-btn" @click="openRating" title="Rate">⭐</button>

                        <button
                            class="header-close"
                            @click="closeChat"
                            type="button"
                            aria-label="Close"
                        >×</button>
                    </div>
                </div>


                <!-- Search -->
                <div v-if="showSearch" class="search-bar">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search messages..."
                        @input="debouncedSearch"
                    />
                    <button v-if="searchQuery" type="button" @click="clearSearch">×</button>
                </div>


                <!-- Messages -->
                <div
                    ref="messagesContainer"
                    class="messages-container"
                    @click="closeMessageMenu"
                >
                    <div v-if="loading" class="loading-message">Loading chat...</div>

                    <div v-else-if="messages.length === 0" class="empty-message">
                        <div class="empty-icon">💬</div>
                        <p>{{ searchQuery ? 'No matches.' : 'No messages yet.' }}</p>
                        <span v-if="!searchQuery">Send a message to contact admin.</span>
                    </div>

                    <template v-else>
                        <div
                            v-for="message in messages"
                            :key="message.id"
                            class="message-row"
                            :class="{
                                'user-message-row': message.sender_type === 'user',
                                'admin-message-row': message.sender_type === 'admin',
                                'system-message-row': message.sender_type === 'system'
                            }"
                        >
                            <!-- ⭐ SYSTEM MESSAGE (call logs, etc.) -->
                            <div
                                v-if="message.sender_type === 'system'"
                                class="system-message"
                            >
                                {{ message.message }}
                            </div>

                            <!-- NORMAL MESSAGE -->
                            <div
                                v-else
                                class="message-bubble-wrapper"
                                @contextmenu.prevent="openMenu($event, message)"
                            >
                                <div
                                    class="message-bubble"
                                    :class="{
                                        'user-message': message.sender_type === 'user',
                                        'admin-message': message.sender_type === 'admin',
                                        'deleted': message.deleted_at
                                    }"
                                >
                                    <div v-if="message.deleted_at" class="deleted-message">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"/>
                                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                        </svg>
                                        <span>This message was deleted</span>
                                    </div>

                                    <template v-else>
                                        <div v-if="message.parent" class="message-reply-quote">
                                            <span class="quote-sender">
                                                {{ message.parent.sender_type === 'user' ? 'You' : 'Admin' }}
                                            </span>
                                            <span class="quote-text">
                                                {{ message.parent.message || message.parent.attachment_name || 'Attachment' }}
                                            </span>
                                        </div>

                                        <div v-if="message.attachment_url" class="message-attachment">
                                            <img
                                                v-if="message.is_image"
                                                :src="message.attachment_url"
                                                :alt="message.attachment_name"
                                                class="attachment-image"
                                                @click="openLightbox(message.attachment_url)"
                                            />

                                            <video
                                                v-else-if="message.is_video"
                                                :src="message.attachment_url"
                                                controls
                                                class="attachment-video"
                                            ></video>

                                            <VoicePlayer
                                                v-else-if="message.is_audio"
                                                :src="message.attachment_url"
                                                :own="message.sender_type === 'user'"
                                            />

                                            <a
                                                v-else
                                                :href="message.attachment_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="attachment-file"
                                            >
                                                <span class="file-icon">📎</span>
                                                <span class="file-name">{{ message.attachment_name }}</span>
                                                <span class="file-size">{{ formatSize(message.attachment_size) }}</span>
                                            </a>
                                        </div>

                                        <div v-if="message.message" class="message-text">
                                            {{ message.message }}
                                        </div>
                                    </template>

                                    <div class="message-meta">
                                        <span v-if="message.is_starred" class="star-indicator" title="Starred">★</span>
                                        <span class="message-time">{{ formatTime(message.created_at) }}</span>
                                        <span
                                            v-if="message.sender_type === 'user' && !message.deleted_at"
                                            class="read-tick"
                                            :class="{ read: !!message.read_at }"
                                        >
                                            <svg v-if="message.read_at" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="1 12 5 16 11 10"/>
                                                <polyline points="9 12 13 16 22 6"/>
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="adminIsTyping" class="typing-indicator">
                            <span></span><span></span><span></span>
                        </div>
                    </template>
                </div>


                <!-- File preview -->
                <div v-if="selectedFile" class="file-preview">
                    <span class="file-icon">📎</span>
                    <span class="file-name">{{ selectedFile.name }}</span>
                    <span class="file-size">{{ formatSize(selectedFile.size) }}</span>
                    <button class="remove-file" type="button" @click="clearSelectedFile" :disabled="sending">×</button>
                </div>


                <!-- Reply preview -->
                <div v-if="replyTo" class="reply-preview">
                    <div class="reply-preview-content">
                        <strong>Replying to {{ replyTo.sender_type === 'user' ? 'yourself' : 'Admin' }}</strong>
                        <span>{{ replyTo.message || replyTo.attachment_name || 'Attachment' }}</span>
                    </div>
                    <button type="button" class="reply-cancel" @click="cancelReply">×</button>
                </div>


                <!-- Blocked banner -->
                <div v-if="isBlocked" class="blocked-banner">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                    </svg>
                    <span>You have been blocked from sending messages. {{ blockedReason ? `Reason: ${blockedReason}` : '' }}</span>
                </div>


                <!-- Emoji picker -->
                <div v-if="showEmojiPicker" class="emoji-picker-wrapper">
                    <EmojiPicker
                        :native="true"
                        :disable-skin-tones="true"
                        theme="light"
                        @select="onEmojiSelect"
                    />
                </div>


                <!-- Input -->
                <form v-if="!isBlocked" class="chat-input-area" @submit.prevent="sendMessage">
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                        style="display: none;"
                        @change="onFileSelected"
                    />

                    <button v-if="!isRecording" type="button" class="emoji-button" @click="toggleEmojiPicker" :disabled="sending" title="Emoji">😀</button>

                    <button v-if="!isRecording" type="button" class="attach-button" @click="openFilePicker" :disabled="sending" title="Attach">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>

                    <button
                        v-if="!isRecording && !selectedFile"
                        type="button"
                        class="mic-button"
                        @click="startRecording"
                        :disabled="sending || !!newMessage.trim()"
                        title="Record voice"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                            <line x1="12" y1="19" x2="12" y2="23"/>
                            <line x1="8" y1="23" x2="16" y2="23"/>
                        </svg>
                    </button>

                    <input
                        v-if="!isRecording"
                        v-model="newMessage"
                        type="text"
                        placeholder="Type a message..."
                        maxlength="5000"
                        :disabled="sending"
                        autocomplete="off"
                        @input="onInputTyping"
                    />

                    <button
                        v-if="!isRecording"
                        type="submit"
                        class="send-button"
                        :disabled="sending || (!newMessage.trim() && !selectedFile)"
                    >
                        <span v-if="sending">...</span>
                        <span v-else>➤</span>
                    </button>

                    <div v-if="isRecording" class="recording-bar">
                        <button type="button" class="recording-cancel" @click="cancelRecording">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>

                        <span class="recording-dot"></span>
                        <span class="recording-time">{{ formattedRecordTime }}</span>

                        <div class="recording-waveform">
                            <span
                                v-for="(height, i) in liveBars"
                                :key="i"
                                class="rec-bar"
                                :style="{ height: height + '%' }"
                            ></span>
                        </div>

                        <button type="button" class="recording-stop" @click="stopAndSendRecording" :disabled="recordSeconds < 1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2 21l21-9L2 3v7l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </form>

            </div>


            <!-- Rating -->
            <div v-if="showRating" class="rating-overlay" @click.self="showRating = false">
                <div class="rating-modal">
                    <h3>Rate this conversation</h3>
                    <p>How was your experience?</p>

                    <div class="rating-stars">
                        <button
                            v-for="star in 5"
                            :key="star"
                            type="button"
                            @click="ratingValue = star"
                            :class="{ active: star <= ratingValue }"
                        >★</button>
                    </div>

                    <textarea v-model="ratingFeedback" placeholder="Optional feedback..." maxlength="1000" rows="3"></textarea>

                    <div class="rating-actions">
                        <button type="button" class="btn-cancel" @click="showRating = false">Cancel</button>
                        <button type="button" class="btn-primary" @click="submitRating" :disabled="!ratingValue || ratingSubmitting">
                            {{ ratingSubmitting ? 'Sending...' : 'Submit' }}
                        </button>
                    </div>
                </div>
            </div>


            <!-- Lightbox -->
            <ImageLightbox v-model="lightboxOpen" :src="lightboxSrc" />

        </div>


        <!-- 📞 Call widget -->
        <CallWidget
            ref="callWidgetRef"
            :current-user="currentUser"
        />

    </Teleport>


    <!-- Context Menu -->
    <Teleport to="body">
        <div v-if="activeMenu && activeMenuMessage" class="message-menu" :style="menuStyle" @click.stop>
            <button type="button" class="menu-item" @click="startReply">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 17 4 12 9 7"/>
                    <path d="M20 18v-2a4 4 0 0 0-4-4H4"/>
                </svg>
                Reply
            </button>

            <button type="button" class="menu-item" @click="toggleStar">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" :fill="activeMenuMessage?.is_starred ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                {{ activeMenuMessage?.is_starred ? 'Unstar' : 'Star' }}
            </button>

            <button type="button" class="menu-item" @click="deleteForMe">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
                Delete for me
            </button>

            <button type="button" class="menu-item danger" @click="deleteForEveryone">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Delete for everyone
            </button>
        </div>
    </Teleport>
</template>


<script setup>
import {
    ref,
    computed,
    onMounted,
    onUnmounted,
    nextTick,
} from 'vue';

import EmojiPicker from 'vue3-emoji-picker';
import 'vue3-emoji-picker/css';

import {
    getChat,
    getChatUnreadCount,
    sendChatMessage,
    markChatAsRead,
    sendUserTyping,
    deleteChatMessage,
    searchUserChat,
    rateChat,
    toggleStarMessage,
} from '../services/chat';

import { useUserChatChannel } from '../composables/useChatChannel';
import VoicePlayer from './VoicePlayer.vue';
import ImageLightbox from './ImageLightbox.vue';

// ⭐ Call widget
import CallWidget from './CallWidget.vue';
import { authState } from '../stores/auth';

import { playNotificationSound, isMuted } from '../utils/notificationSound';
import {
    requestNotificationPermission,
    showNotification,
    isDesktopNotificationsEnabled,
} from '../utils/desktopNotifications';


/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const isOpen = ref(false);
const loading = ref(false);
const sending = ref(false);
const messages = ref([]);
const newMessage = ref('');
const unreadCount = ref(0);
const messagesContainer = ref(null);
const fileInput = ref(null);
const selectedFile = ref(null);

const showEmojiPicker = ref(false);
const adminIsTyping = ref(false);
const replyTo = ref(null);

const showSearch = ref(false);
const searchQuery = ref('');

const showRating = ref(false);
const ratingValue = ref(0);
const ratingFeedback = ref('');
const ratingSubmitting = ref(false);

const lightboxOpen = ref(false);
const lightboxSrc = ref('');

const isBlocked = ref(false);
const blockedReason = ref('');

const activeMenu = ref(null);
const activeMenuMessage = ref(null);
const menuStyle = ref({ top: '0px', left: '0px' });

// ⭐ Call
const callWidgetRef = ref(null);
const currentUser = computed(() => authState.user);
const supportUserId = ref(null);

let unreadPollTimer = null;
let messagePollTimer = null;
let typingTimer = null;
let typingDebounce = null;
let searchDebounce = null;


/*
|--------------------------------------------------------------------------
| Voice
|--------------------------------------------------------------------------
*/

const isRecording = ref(false);
const recordSeconds = ref(0);
const liveBars = ref(Array(20).fill(30));

let mediaRecorder = null;
let recordingStream = null;
let recordChunks = [];
let recordTimer = null;
let recordStartTime = 0;
let liveBarTimer = null;


/*
|--------------------------------------------------------------------------
| Websocket
|--------------------------------------------------------------------------
*/

const { connected } = useUserChatChannel({
    onMessage: (payload) => {
        const id = payload.id ?? payload.message?.id;
        if (!id) return;

        if (!messages.value.some((m) => m.id === id)) {
            messages.value.push(payload);
            scrollToBottom();
        }

        // ⭐ Track admin id
        if (
            payload.sender_type === 'admin' &&
            payload.sender_id &&
            !supportUserId.value
        ) {
            supportUserId.value = payload.sender_id;
        }

        // Sound / notifications for admin messages only
        if (!isMuted() && payload.sender_type === 'admin') {
            playNotificationSound();
        }

        if (
            isDesktopNotificationsEnabled() &&
            !isOpen.value &&
            payload.sender_type === 'admin'
        ) {
            showNotification(
                'New message from Support',
                payload.message || 'You have a new message',
                () => { if (!isOpen.value) toggleChat(); }
            );
        }

        if (isOpen.value) {
            unreadCount.value = 0;
            markChatAsRead().catch(() => {});
        } else if (payload.sender_type !== 'system') {
            unreadCount.value++;
        }
    },

    onUnread: (count) => {
        unreadCount.value = Number(count) || 0;
    },

    onRead: (payload) => {
        const msg = messages.value.find((m) => m.id === payload.id);
        if (msg) msg.read_at = payload.read_at;
    },

    onDeleted: (payload) => {
        const msg = messages.value.find((m) => m.id === payload.id);
        if (msg) {
            msg.deleted_at = new Date().toISOString();
            msg.message = null;
            msg.attachment_url = null;
        }
    },

    onDeletedForMe: (payload) => {
        if (payload.deleter_type === 'admin') {
            messages.value = messages.value.filter((m) => m.id !== payload.id);
        }
    },

    onStarred: (payload) => {
        const msg = messages.value.find((m) => m.id === payload.id);
        if (msg) msg.is_starred = payload.is_starred;
    },

    onBlocked: (payload) => {
        isBlocked.value = !!payload.is_blocked;
        blockedReason.value = payload.blocked_reason || '';
    },

    onTyping: (payload) => {
        if (payload.sender_type === 'admin') {
            adminIsTyping.value = true;
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => { adminIsTyping.value = false; }, 3000);
        }
    },
});


/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const formattedRecordTime = computed(() => {
    const m = String(Math.floor(recordSeconds.value / 60)).padStart(2, '0');
    const s = String(recordSeconds.value % 60).padStart(2, '0');
    return `${m}:${s}`;
});


/*
|--------------------------------------------------------------------------
| 📞 Call support
|--------------------------------------------------------------------------
*/

const callSupport = () => {
    if (!supportUserId.value) {
        alert('Support is not available right now. Please send a message first.');
        return;
    }

    if (!callWidgetRef.value) {
        alert('Call system not ready. Please refresh the page.');
        return;
    }

    callWidgetRef.value.startCall(supportUserId.value, 'audio');
};


/*
|--------------------------------------------------------------------------
| Load
|--------------------------------------------------------------------------
*/

const loadChat = async () => {
    try {
        if (messages.value.length === 0) loading.value = true;

        const response = await getChat();

        if (response.data?.success) {
            messages.value = response.data.messages || [];
            isBlocked.value = !!response.data.is_blocked;
            blockedReason.value = response.data.blocked_reason || '';

            // ⭐ Extract admin/support id
            if (response.data.support_user_id) {
                supportUserId.value = response.data.support_user_id;
            } else {
                const adminMsg = messages.value.find(
                    (m) => m.sender_type === 'admin' && m.sender_id
                );
                if (adminMsg && !supportUserId.value) {
                    supportUserId.value = adminMsg.sender_id;
                }
            }

            await scrollToBottom();

            if (isOpen.value) {
                await markChatAsRead().catch(() => {});
            }
        }
    } catch (error) {
        console.error('Load error:', error);
    } finally {
        loading.value = false;
    }
};

const loadUnreadCount = async () => {
    try {
        const response = await getChatUnreadCount();

        if (response.data?.success) {
            const newCount = Number(response.data.count) || 0;

            if (newCount > unreadCount.value && isOpen.value) {
                await loadChat();
                unreadCount.value = 0;
                return;
            }

            unreadCount.value = newCount;
        }
    } catch (error) {
        console.error('Unread error:', error);
    }
};


/*
|--------------------------------------------------------------------------
| Toggle
|--------------------------------------------------------------------------
*/

const toggleChat = async () => {
    if (isOpen.value) {
        closeChat();
        return;
    }

    isOpen.value = true;
    await loadChat();
    unreadCount.value = 0;
};

const closeChat = () => {
    isOpen.value = false;
    showEmojiPicker.value = false;
    showSearch.value = false;
    showRating.value = false;
    replyTo.value = null;
    closeMessageMenu();
};


/*
|--------------------------------------------------------------------------
| Typing
|--------------------------------------------------------------------------
*/

const onInputTyping = () => {
    if (typingDebounce) return;

    typingDebounce = setTimeout(() => { typingDebounce = null; }, 2000);

    sendUserTyping().catch(() => {});
};


/*
|--------------------------------------------------------------------------
| Emoji
|--------------------------------------------------------------------------
*/

const toggleEmojiPicker = () => {
    showEmojiPicker.value = !showEmojiPicker.value;
};

const onEmojiSelect = (emoji) => {
    newMessage.value += emoji.i || emoji.emoji || '';
};


/*
|--------------------------------------------------------------------------
| File
|--------------------------------------------------------------------------
*/

const openFilePicker = () => {
    if (sending.value) return;
    fileInput.value?.click();
};

const onFileSelected = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 20 * 1024 * 1024) {
        alert('File too large. Max 20 MB.');
        event.target.value = '';
        return;
    }

    selectedFile.value = file;
};

const clearSelectedFile = () => {
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = '';
};

const formatSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
};


/*
|--------------------------------------------------------------------------
| Send
|--------------------------------------------------------------------------
*/

const sendMessage = async () => {
    const messageText = newMessage.value.trim();
    const file = selectedFile.value;

    if (!messageText && !file) return;
    if (sending.value) return;

    try {
        sending.value = true;

        const response = await sendChatMessage(
            messageText,
            file,
            replyTo.value?.id || null
        );

        if (response.data?.success && response.data?.message) {
            const msg = response.data.message;

            if (!messages.value.some((m) => m.id === msg.id)) {
                messages.value.push(msg);
            }

            newMessage.value = '';
            clearSelectedFile();
            showEmojiPicker.value = false;
            replyTo.value = null;

            await scrollToBottom();
        }
    } catch (error) {
        if (error.response?.data?.blocked) {
            isBlocked.value = true;
        }
        alert(error.response?.data?.message || 'Message send nahi ho saka.');
    } finally {
        sending.value = false;
    }
};


/*
|--------------------------------------------------------------------------
| Context Menu
|--------------------------------------------------------------------------
*/

const openMenu = (event, message) => {
    if (message.deleted_at) return;
    if (message.sender_type === 'system') return;

    activeMenu.value = message.id;
    activeMenuMessage.value = message;

    const menuWidth = 220;
    const menuHeight = 190;
    const padding = 10;

    let x = event.clientX;
    let y = event.clientY;

    if (x + menuWidth > window.innerWidth - padding) {
        x = window.innerWidth - menuWidth - padding;
    }

    if (y + menuHeight > window.innerHeight - padding) {
        y = y - menuHeight;
    }

    if (x < padding) x = padding;
    if (y < padding) y = padding;

    menuStyle.value = { top: y + 'px', left: x + 'px' };
};

const closeMessageMenu = () => {
    activeMenu.value = null;
    activeMenuMessage.value = null;
};


/*
|--------------------------------------------------------------------------
| Reply
|--------------------------------------------------------------------------
*/

const startReply = () => {
    if (!activeMenuMessage.value) return;
    replyTo.value = activeMenuMessage.value;
    closeMessageMenu();
};

const cancelReply = () => {
    replyTo.value = null;
};


/*
|--------------------------------------------------------------------------
| Star
|--------------------------------------------------------------------------
*/

const toggleStar = async () => {
    const message = activeMenuMessage.value;
    if (!message) return;

    closeMessageMenu();

    try {
        const response = await toggleStarMessage(message.id);
        if (response.data?.success) {
            message.is_starred = response.data.is_starred;
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to star.');
    }
};


/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

const deleteForMe = async () => {
    const message = activeMenuMessage.value;
    if (!message) return;

    closeMessageMenu();

    try {
        await deleteChatMessage(message.id, 'me');
        messages.value = messages.value.filter((m) => m.id !== message.id);
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to delete.');
    }
};

const deleteForEveryone = async () => {
    const message = activeMenuMessage.value;
    if (!message) return;

    closeMessageMenu();

    try {
        await deleteChatMessage(message.id, 'all');
        message.deleted_at = new Date().toISOString();
        message.message = null;
        message.attachment_url = null;
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to delete.');
    }
};


/*
|--------------------------------------------------------------------------
| Lightbox
|--------------------------------------------------------------------------
*/

const openLightbox = (url) => {
    lightboxSrc.value = url;
    lightboxOpen.value = true;
};


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

const debouncedSearch = () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(runSearch, 400);
};

const runSearch = async () => {
    if (!searchQuery.value.trim()) {
        await loadChat();
        return;
    }

    try {
        const response = await searchUserChat(searchQuery.value);
        if (response.data?.success) {
            messages.value = response.data.messages || [];
            await scrollToBottom();
        }
    } catch (error) {
        console.error('Search failed:', error);
    }
};

const clearSearch = async () => {
    searchQuery.value = '';
    showSearch.value = false;
    await loadChat();
};


/*
|--------------------------------------------------------------------------
| Rating
|--------------------------------------------------------------------------
*/

const openRating = () => {
    showRating.value = true;
    ratingValue.value = 0;
    ratingFeedback.value = '';
};

const submitRating = async () => {
    if (!ratingValue.value) return;

    ratingSubmitting.value = true;

    try {
        await rateChat(ratingValue.value, ratingFeedback.value);
        showRating.value = false;
        alert('Thank you for your feedback!');
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to submit.');
    } finally {
        ratingSubmitting.value = false;
    }
};


/*
|--------------------------------------------------------------------------
| Voice
|--------------------------------------------------------------------------
*/

const startLiveBars = () => {
    stopLiveBars();
    liveBarTimer = setInterval(() => {
        liveBars.value = Array.from({ length: 20 }, () => Math.floor(Math.random() * 70) + 30);
    }, 120);
};

const stopLiveBars = () => {
    if (liveBarTimer) {
        clearInterval(liveBarTimer);
        liveBarTimer = null;
    }
    liveBars.value = Array(20).fill(30);
};

const startRecording = async () => {
    if (isRecording.value || sending.value) return;

    if (!navigator.mediaDevices || !window.MediaRecorder) {
        alert('Voice not supported.');
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

        recordingStream = stream;
        recordChunks = [];
        recordSeconds.value = 0;
        isRecording.value = true;

        mediaRecorder = new MediaRecorder(stream);

        mediaRecorder.ondataavailable = (e) => {
            if (e.data && e.data.size > 0) recordChunks.push(e.data);
        };

        mediaRecorder.onstop = () => {
            stream.getTracks().forEach((t) => t.stop());
            recordingStream = null;

            if (!recordChunks.length) return;

            const mimeType = mediaRecorder.mimeType || 'audio/webm';
            const blob = new Blob(recordChunks, { type: mimeType });
            sendVoiceMessage(blob, mimeType);
        };

        mediaRecorder.start();
        recordStartTime = Date.now();
        startLiveBars();

        recordTimer = setInterval(() => {
            recordSeconds.value = Math.floor((Date.now() - recordStartTime) / 1000);
            if (recordSeconds.value >= 120) stopRecording();
        }, 200);
    } catch (error) {
        alert('Mic error.');
        isRecording.value = false;
    }
};

const stopRecording = () => {
    if (!isRecording.value) return;
    stopLiveBars();
    if (recordTimer) {
        clearInterval(recordTimer);
        recordTimer = null;
    }
    isRecording.value = false;
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
};

const cancelRecording = () => {
    if (!isRecording.value) return;
    stopLiveBars();
    recordChunks = [];
    if (recordTimer) {
        clearInterval(recordTimer);
        recordTimer = null;
    }
    isRecording.value = false;
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
};

const stopAndSendRecording = () => {
    if (recordSeconds.value < 1) return;
    stopRecording();
};

const sendVoiceMessage = async (blob, mimeType) => {
    let ext = 'webm';
    if (mimeType.includes('mp4')) ext = 'm4a';
    else if (mimeType.includes('ogg')) ext = 'ogg';
    else if (mimeType.includes('wav')) ext = 'wav';
    else if (mimeType.includes('mpeg')) ext = 'mp3';

    const filename = `voice-${Date.now()}.${ext}`;
    const file = new File([blob], filename, { type: mimeType });

    try {
        sending.value = true;
        const response = await sendChatMessage('', file, replyTo.value?.id || null);

        if (response.data?.success && response.data?.message) {
            const msg = response.data.message;
            if (!messages.value.some((m) => m.id === msg.id)) {
                messages.value.push(msg);
            }
            replyTo.value = null;
            await scrollToBottom();
        }
    } catch (error) {
        alert('Voice send failed.');
    } finally {
        sending.value = false;
        recordChunks = [];
    }
};


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const formatTime = (date) => {
    if (!date) return '';
    const d = new Date(date);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};


/*
|--------------------------------------------------------------------------
| Polling
|--------------------------------------------------------------------------
*/

const startPolling = () => {
    stopPolling();
    unreadPollTimer = setInterval(loadUnreadCount, 5000);
    messagePollTimer = setInterval(() => {
        if (isOpen.value) loadChat();
    }, 5000);
};

const stopPolling = () => {
    if (unreadPollTimer) {
        clearInterval(unreadPollTimer);
        unreadPollTimer = null;
    }
    if (messagePollTimer) {
        clearInterval(messagePollTimer);
        messagePollTimer = null;
    }
};


/*
|--------------------------------------------------------------------------
| Global listeners
|--------------------------------------------------------------------------
*/

const handleScrollOrResize = () => {
    closeMessageMenu();
};


/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    await loadUnreadCount();

    setTimeout(() => {
        if (!connected.value) startPolling();
    }, 1500);

    if ('Notification' in window && Notification.permission === 'default') {
        setTimeout(() => {
            requestNotificationPermission();
        }, 5000);
    }

    window.addEventListener('scroll', handleScrollOrResize, true);
    window.addEventListener('resize', handleScrollOrResize);
});

onUnmounted(() => {
    stopPolling();
    stopLiveBars();

    if (recordTimer) clearInterval(recordTimer);
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
    if (recordingStream) {
        recordingStream.getTracks().forEach((t) => t.stop());
    }

    if (typingTimer) clearTimeout(typingTimer);
    if (typingDebounce) clearTimeout(typingDebounce);
    if (searchDebounce) clearTimeout(searchDebounce);

    window.removeEventListener('scroll', handleScrollOrResize, true);
    window.removeEventListener('resize', handleScrollOrResize);
});
</script>


<style scoped>

.chat-widget {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 99999;
    font-family: Arial, Helvetica, sans-serif;
    pointer-events: none;
}

.chat-widget > * { pointer-events: auto; }

.chat-button {
    position: relative;
    width: 58px;
    height: 58px;
    border: none;
    border-radius: 50%;
    background: #4f46e5;
    color: #ffffff;
    font-size: 25px;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}

.chat-button:hover {
    transform: scale(1.06);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.30);
}

.chat-button.hidden {
    opacity: 0;
    pointer-events: none;
    transform: scale(0.5);
}

.chat-icon { font-size: 25px; line-height: 1; }

.unread-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ef4444;
    color: #ffffff;
    border-radius: 50%;
    font-size: 11px;
    font-weight: bold;
    border: 2px solid #ffffff;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

.chat-window {
    position: fixed;
    right: 24px;
    bottom: 96px;
    width: 380px;
    height: 540px;
    max-height: calc(100vh - 120px);
    background: #ffffff;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
    border: 1px solid #e5e7eb;
    z-index: 100000;
}

.chat-header {
    height: 68px;
    padding: 0 12px 0 16px;
    background: #4f46e5;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.chat-header h3 { margin: 0 0 4px; font-size: 17px; }
.online-status { font-size: 12px; opacity: 0.9; }

.header-actions { display: flex; align-items: center; gap: 4px; }

.header-icon-btn {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #ffffff;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease;
}

.header-icon-btn:hover { background: rgba(255, 255, 255, 0.15); }
.header-icon-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.header-close {
    border: none;
    background: transparent;
    color: #ffffff;
    font-size: 28px;
    cursor: pointer;
    padding: 5px 8px;
    line-height: 1;
}

.search-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #eef2ff;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.search-bar input {
    flex: 1;
    height: 36px;
    border: 1px solid #d1d5db;
    border-radius: 18px;
    padding: 0 14px;
    font-size: 13px;
    outline: none;
    background: #ffffff;
}

.search-bar button {
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 50%;
    background: #dc2626;
    color: white;
    font-size: 16px;
    cursor: pointer;
    line-height: 1;
    flex-shrink: 0;
}

.messages-container {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 16px;
    background: #f8fafc;
    scroll-behavior: smooth;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.message-row { display: flex; margin: 0; }
.user-message-row { justify-content: flex-end; }
.admin-message-row { justify-content: flex-start; }
.system-message-row { justify-content: center; }

/* ⭐ SYSTEM MESSAGE */
.system-message {
    display: inline-block;
    padding: 5px 14px;
    background: #e5e7eb;
    color: #4b5563;
    font-size: 11.5px;
    font-weight: 500;
    border-radius: 20px;
    text-align: center;
    max-width: 80%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.message-bubble-wrapper { position: relative; max-width: 78%; }

.message-bubble {
    padding: 8px 12px;
    border-radius: 12px;
    word-break: break-word;
}

.user-message {
    background: #4f46e5;
    color: #ffffff;
    border-bottom-right-radius: 4px;
}

.admin-message {
    background: #ffffff;
    color: #1f2937;
    border: 1px solid #e5e7eb;
    border-bottom-left-radius: 4px;
}

.message-bubble.deleted {
    opacity: 0.6;
    font-style: italic;
    background: #f1f5f9 !important;
    color: #64748b !important;
}

.deleted-message {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}

.message-reply-quote {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 6px 10px;
    margin-bottom: 6px;
    border-left: 3px solid currentColor;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.05);
    font-size: 11px;
    opacity: 0.85;
}

.user-message .message-reply-quote { background: rgba(255, 255, 255, 0.15); }

.quote-sender { font-weight: 700; }
.quote-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.message-text { font-size: 14px; line-height: 1.4; white-space: pre-wrap; }
.message-time { font-size: 10px; opacity: 0.65; }

.message-meta {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    margin-top: 4px;
}

.star-indicator { color: #f59e0b; font-size: 11px; }
.user-message .star-indicator { color: #fcd34d; }

.read-tick { display: inline-flex; align-items: center; color: #94a3b8; }
.read-tick.read { color: #4f46e5; }
.user-message .read-tick { color: rgba(255, 255, 255, 0.6); }
.user-message .read-tick.read { color: #ffffff; }

.message-menu {
    position: fixed;
    z-index: 2147483647;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    overflow: hidden;
    min-width: 220px;
    padding: 4px 0;
    font-family: Arial, Helvetica, sans-serif;
}

.message-menu .menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 16px;
    border: none;
    background: transparent;
    font-size: 13px;
    color: #374151;
    cursor: pointer;
    text-align: left;
    font-weight: 500;
    white-space: nowrap;
}

.message-menu .menu-item:hover { background: #f3f4f6; }
.message-menu .menu-item.danger { color: #dc2626; }
.message-menu .menu-item.danger:hover { background: #fee2e2; }
.message-menu .menu-item svg { flex-shrink: 0; width: 14px; height: 14px; }

.typing-indicator {
    display: flex;
    gap: 4px;
    align-self: flex-start;
    padding: 8px 14px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    border-bottom-left-radius: 4px;
    width: fit-content;
}

.typing-indicator span {
    width: 7px;
    height: 7px;
    background: #94a3b8;
    border-radius: 50%;
    animation: typing-bounce 1.4s infinite;
}

.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing-bounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-5px); }
}

.message-attachment { margin-bottom: 6px; }

.attachment-image {
    max-width: 100%;
    max-height: 220px;
    border-radius: 8px;
    cursor: pointer;
    display: block;
}

.attachment-video {
    max-width: 100%;
    max-height: 220px;
    border-radius: 8px;
    display: block;
}

.attachment-file {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    text-decoration: none;
    color: inherit;
    font-size: 12px;
}

.user-message .attachment-file { background: rgba(255, 255, 255, 0.15); }
.admin-message .attachment-file { background: #f1f5f9; }

.file-icon { font-size: 16px; }
.file-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-size { opacity: 0.7; font-size: 10px; }

.loading-message {
    text-align: center;
    color: #6b7280;
    padding: 30px 10px;
    font-size: 14px;
}

.empty-message {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #6b7280;
}

.empty-icon { font-size: 40px; margin-bottom: 10px; }
.empty-message p { margin: 0 0 5px; font-weight: 600; color: #374151; }
.empty-message span { font-size: 13px; }

.file-preview {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #eef2ff;
    border-top: 1px solid #e5e7eb;
    font-size: 12px;
    flex-shrink: 0;
}

.file-preview .file-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #1f2937;
}

.file-preview .file-size { color: #6b7280; font-size: 11px; }

.remove-file {
    width: 24px;
    height: 24px;
    border: none;
    border-radius: 50%;
    background: #dc2626;
    color: #ffffff;
    font-size: 16px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.reply-preview {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #eef2ff;
    border-top: 1px solid #e5e7eb;
    border-left: 3px solid #4f46e5;
    flex-shrink: 0;
}

.reply-preview-content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 12px;
}

.reply-preview-content strong { color: #4f46e5; font-weight: 700; font-size: 11px; }
.reply-preview-content span { color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.reply-cancel {
    width: 24px;
    height: 24px;
    border: none;
    border-radius: 50%;
    background: #dc2626;
    color: white;
    font-size: 14px;
    cursor: pointer;
    line-height: 1;
    flex-shrink: 0;
}

.blocked-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #fef2f2;
    border-top: 1px solid #fecaca;
    color: #b91c1c;
    font-size: 12px;
    flex-shrink: 0;
}

.emoji-picker-wrapper {
    position: absolute;
    bottom: 70px;
    right: 12px;
    z-index: 100;
    box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    border-radius: 10px;
    overflow: hidden;
    background: white;
}

.chat-input-area {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px;
    background: #ffffff;
    border-top: 1px solid #e5e7eb;
    flex-shrink: 0;
    position: relative;
}

.chat-input-area input[type="text"] {
    flex: 1;
    height: 40px;
    border: 1px solid #d1d5db;
    border-radius: 20px;
    padding: 0 14px;
    outline: none;
    font-size: 14px;
    min-width: 0;
}

.emoji-button,
.attach-button,
.mic-button {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #4f46e5;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.emoji-button:disabled,
.attach-button:disabled,
.mic-button:disabled { opacity: 0.4; cursor: not-allowed; }

.send-button {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border: none;
    border-radius: 50%;
    background: #4f46e5;
    color: #ffffff;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.send-button:disabled { opacity: 0.5; cursor: not-allowed; }

.recording-bar {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    height: 40px;
    padding: 0 8px 0 6px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 20px;
    font-size: 13px;
    min-width: 0;
}

.recording-cancel {
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #dc2626;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.recording-dot {
    width: 10px;
    height: 10px;
    flex-shrink: 0;
    border-radius: 50%;
    background: #dc2626;
    animation: rec-pulse 1s infinite;
}

@keyframes rec-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.8); }
}

.recording-time {
    font-weight: 700;
    color: #dc2626;
    min-width: 44px;
    font-variant-numeric: tabular-nums;
}

.recording-waveform {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 2px;
    height: 26px;
    overflow: hidden;
    min-width: 0;
}

.rec-bar {
    flex: 1;
    min-width: 2px;
    max-width: 3px;
    border-radius: 2px;
    background: #dc2626;
    transition: height 0.12s ease;
    opacity: 0.75;
}

.recording-stop {
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    border: none;
    border-radius: 50%;
    background: #dc2626;
    color: #ffffff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
}

.recording-stop:disabled { opacity: 0.5; cursor: not-allowed; }

.rating-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    background: rgba(17, 24, 39, 0.55);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.rating-modal {
    width: 100%;
    max-width: 380px;
    padding: 28px;
    background: white;
    border-radius: 16px;
    text-align: center;
}

.rating-modal h3 { margin: 0 0 6px; color: #111827; }
.rating-modal p { margin: 0 0 20px; color: #6b7280; font-size: 14px; }

.rating-stars {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-bottom: 20px;
}

.rating-stars button {
    background: transparent;
    border: none;
    font-size: 38px;
    color: #d1d5db;
    cursor: pointer;
    line-height: 1;
    padding: 0;
}

.rating-stars button:hover,
.rating-stars button.active { color: #f59e0b; }

.rating-modal textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    outline: none;
    font-family: inherit;
    font-size: 13px;
    resize: vertical;
    margin-bottom: 18px;
    box-sizing: border-box;
}

.rating-actions { display: flex; gap: 10px; }

.rating-actions button {
    flex: 1;
    height: 42px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
}

.btn-cancel { background: #f3f4f6; color: #374151; }
.btn-primary { background: #4f46e5; color: white; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.messages-container::-webkit-scrollbar { width: 6px; }
.messages-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

@media (max-width: 480px) {
    .chat-widget { right: 15px; bottom: 15px; }

    .chat-window {
        position: fixed;
        left: 10px;
        right: 10px;
        bottom: 84px;
        width: auto;
        height: auto;
        max-height: calc(100vh - 110px);
    }
}
</style>