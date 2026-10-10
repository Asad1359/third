<template>
    <div class="admin-chat">

        <!-- LEFT: users -->
        <div class="chat-users">

            <div class="users-header">
                <div>
                    <h2>Customer Chats</h2>
                    <p>{{ connected ? '● Live' : 'Users ke messages' }}</p>
                </div>

                <button
                    class="refresh-button"
                    @click="loadConversations"
                    :disabled="loadingConversations"
                    type="button"
                >
                    ↻
                </button>
            </div>

            <!-- Search bar -->
            <div class="users-search">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search messages..."
                    @input="debouncedSearch"
                />

                <button
                    v-if="searchQuery"
                    type="button"
                    @click="clearSearch"
                    aria-label="Clear"
                >
                    ×
                </button>
            </div>

            <!-- Search results -->
            <div v-if="searchResults.length > 0" class="search-results">
                <div class="search-results-header">
                    Results ({{ searchResults.length }})
                </div>

                <button
                    v-for="result in searchResults"
                    :key="result.id"
                    type="button"
                    class="search-result-item"
                    @click="jumpToConversation(result)"
                >
                    <strong>{{ result.user_name || 'User' }}</strong>
                    <span>{{ result.message }}</span>
                </button>
            </div>

            <div v-if="loadingConversations" class="loading-users">
                Loading chats...
            </div>

            <div
                v-else-if="conversations.length === 0 && !searchQuery"
                class="no-chats"
            >
                <div class="no-chat-icon">💬</div>
                <p>No conversations yet.</p>
            </div>

            <div v-else-if="!searchQuery" class="user-list">
                <button
                    v-for="conversation in conversations"
                    :key="conversation.id"
                    class="user-item"
                    :class="{ active: selectedConversationId === conversation.id }"
                    @click="selectConversation(conversation)"
                    @contextmenu.prevent="openUserContextMenu($event, conversation)"
                    type="button"
                >
                    <div class="user-avatar">
                        {{ getInitials(conversation.user) }}
                    </div>

                    <div class="user-info">
                        <div class="user-name-row">
                            <strong>
                                {{ conversation.user?.name || 'Unknown User' }}
                            </strong>

                            <span
                                v-if="Number(conversation.unread_admin) > 0"
                                class="unread-count"
                            >
                                {{ conversation.unread_admin }}
                            </span>
                        </div>

                        <p>{{ conversation.last_message || 'No messages yet' }}</p>

                        <span
                            v-if="conversation.assignee"
                            class="assign-badge"
                        >
                            → {{ conversation.assignee.name }}
                        </span>

                        <span
                            v-if="conversation.is_blocked"
                            class="blocked-badge"
                        >
                            🚫 Blocked
                        </span>

                        <span
                            v-if="conversation.is_archived"
                            class="archive-badge"
                        >
                            📦 Archived
                        </span>
                    </div>
                </button>
            </div>

        </div>


        <!-- RIGHT: conversation -->
        <div class="conversation-panel">

            <div v-if="!selectedUser" class="no-selected-user">
                <div class="big-chat-icon">💬</div>
                <h3>Select a conversation</h3>
                <p>Left side se kisi user ko select karein.</p>
            </div>

            <template v-else>

                <!-- Header with 3-dot menu -->
                <div class="conversation-header">
                    <div class="selected-user-avatar">
                        {{ getInitials(selectedUser) }}
                    </div>

                    <div class="conversation-header-info">
                        <h3>{{ selectedUser.name }}</h3>
                        <span>{{ selectedUser.email }}</span>
                    </div>

                    <!-- 📞 CALL BUTTON -->
                    <button
                        class="header-call-btn"
                        type="button"
                        title="Audio call customer"
                        @click="startAudioCall"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                    </button>

                    <!-- Three-dot menu -->
                    <div class="header-menu-wrapper">
                        <button
                            class="header-menu-btn"
                            @click.stop="toggleHeaderMenu"
                            type="button"
                            aria-label="Conversation menu"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <circle cx="12" cy="5" r="2"/>
                                <circle cx="12" cy="12" r="2"/>
                                <circle cx="12" cy="19" r="2"/>
                            </svg>
                        </button>

                        <div
                            v-if="showHeaderMenu"
                            class="header-menu-dropdown"
                            @click.stop
                        >
                            <button
                                type="button"
                                class="menu-item"
                                @click="handleAssignFromMenu"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                {{ assignedToMe ? 'Unassign me' : 'Assign to me' }}
                            </button>

                            <button
                                type="button"
                                class="menu-item"
                                @click="handleClearChat"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                                </svg>
                                Clear Chat
                            </button>

                            <button
                                type="button"
                                class="menu-item"
                                :class="{ 'danger': conversationIsBlocked }"
                                @click="handleBlockFromMenu"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                </svg>
                                {{ conversationIsBlocked ? 'Unblock User' : 'Block User' }}
                            </button>

                            <button
                                type="button"
                                class="menu-item"
                                @click="handleArchive"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <polyline points="21 8 21 21 3 21 3 8"/>
                                    <rect x="1" y="3" width="22" height="5"/>
                                    <line x1="10" y1="12" x2="14" y2="12"/>
                                </svg>
                                {{ conversationIsArchived ? 'Unarchive' : 'Archive' }}
                            </button>

                            <button
                                type="button"
                                class="menu-item danger"
                                @click="handleDeleteConversation"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                </svg>
                                Delete Conversation
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <div
                    ref="messagesContainer"
                    class="messages-container"
                    @click="closeAllMenus"
                >
                    <div v-if="loadingMessages" class="loading-messages">
                        Loading messages...
                    </div>

                    <div
                        v-else-if="messages.length === 0"
                        class="empty-messages"
                    >
                        No messages in this conversation.
                    </div>

                    <template v-else>
                        <div
                            v-for="message in messages"
                            :key="message.id"
                            class="message-row"
                            :class="{
                                'admin-row': message.sender_type === 'admin',
                                'user-row': message.sender_type === 'user',
                                'system-row': message.sender_type === 'system'
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
                                        'admin-message': message.sender_type === 'admin',
                                        'user-message': message.sender_type === 'user',
                                        'deleted': message.deleted_at
                                    }"
                                >
                                    <div v-if="message.deleted_at" class="deleted-message">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            width="14"
                                            height="14"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <circle cx="12" cy="12" r="10"/>
                                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                        </svg>
                                        <span>This message was deleted</span>
                                    </div>

                                    <template v-else>
                                        <div
                                            v-if="message.parent"
                                            class="message-reply-quote"
                                        >
                                            <span class="quote-sender">
                                                {{
                                                    message.parent.sender_type === 'admin'
                                                        ? 'You'
                                                        : (selectedUser?.name || 'User')
                                                }}
                                            </span>
                                            <span class="quote-text">
                                                {{
                                                    message.parent.message ||
                                                    message.parent.attachment_name ||
                                                    'Attachment'
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="message.attachment_url"
                                            class="message-attachment"
                                        >
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
                                                :own="message.sender_type === 'admin'"
                                            />

                                            <a
                                                v-else
                                                :href="message.attachment_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="attachment-file"
                                            >
                                                <span class="file-icon">📎</span>
                                                <span class="file-name">
                                                    {{ message.attachment_name }}
                                                </span>
                                                <span class="file-size">
                                                    {{ formatSize(message.attachment_size) }}
                                                </span>
                                            </a>
                                        </div>

                                        <div v-if="message.message" class="message-text">
                                            {{ message.message }}
                                        </div>
                                    </template>

                                    <div class="message-meta">
                                        <span
                                            v-if="message.is_starred"
                                            class="star-indicator"
                                            title="Starred"
                                        >
                                            ★
                                        </span>

                                        <span class="message-time">
                                            {{ formatTime(message.created_at) }}
                                        </span>

                                        <span
                                            v-if="message.sender_type === 'admin' && !message.deleted_at"
                                            class="read-tick"
                                            :class="{ read: !!message.read_at }"
                                        >
                                            <svg
                                                v-if="message.read_at"
                                                xmlns="http://www.w3.org/2000/svg"
                                                width="14"
                                                height="14"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <polyline points="1 12 5 16 11 10"/>
                                                <polyline points="9 12 13 16 22 6"/>
                                            </svg>

                                            <svg
                                                v-else
                                                xmlns="http://www.w3.org/2000/svg"
                                                width="14"
                                                height="14"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="userIsTyping" class="typing-indicator">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </template>
                </div>

                <!-- Canned responses picker -->
                <div v-if="showCannedPicker" class="canned-picker">
                    <div class="canned-header">
                        <span>Quick Replies</span>
                        <button @click="showCannedPicker = false" type="button">×</button>
                    </div>

                    <div v-if="cannedResponses.length === 0" class="canned-empty">
                        No canned responses yet.
                    </div>

                    <div v-else class="canned-list">
                        <button
                            v-for="canned in cannedResponses"
                            :key="canned.id"
                            type="button"
                            class="canned-item"
                            @click="pickCanned(canned)"
                        >
                            <strong>{{ canned.title }}</strong>
                            <span>{{ canned.body }}</span>
                        </button>
                    </div>
                </div>

                <!-- File preview -->
                <div v-if="selectedFile" class="file-preview">
                    <span class="file-icon">📎</span>
                    <span class="file-name">{{ selectedFile.name }}</span>
                    <span class="file-size">{{ formatSize(selectedFile.size) }}</span>

                    <button
                        class="remove-file"
                        type="button"
                        @click="clearSelectedFile"
                        :disabled="sending"
                    >
                        ×
                    </button>
                </div>

                <!-- Reply preview -->
                <div v-if="replyTo" class="reply-preview">
                    <div class="reply-preview-content">
                        <strong>
                            Replying to
                            {{
                                replyTo.sender_type === 'admin'
                                    ? 'yourself'
                                    : (selectedUser?.name || 'User')
                            }}
                        </strong>
                        <span>
                            {{
                                replyTo.message ||
                                replyTo.attachment_name ||
                                'Attachment'
                            }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="reply-cancel"
                        @click="cancelReply"
                    >
                        ×
                    </button>
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

                <!-- Reply area -->
                <form class="reply-area" @submit.prevent="sendMessage">

                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                        style="display: none;"
                        @change="onFileSelected"
                    />

                    <button
                        v-if="!isRecording"
                        type="button"
                        class="emoji-button"
                        @click="showEmojiPicker = !showEmojiPicker"
                        :disabled="sending"
                        title="Emoji"
                    >
                        😀
                    </button>

                    <button
                        v-if="!isRecording"
                        type="button"
                        class="canned-button"
                        @click="toggleCannedPicker"
                        :disabled="sending"
                        title="Quick replies"
                    >
                        ⚡
                    </button>

                    <button
                        v-if="!isRecording"
                        type="button"
                        class="attach-button"
                        @click="openFilePicker"
                        :disabled="sending"
                        title="Attach"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
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
                        <svg
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
                    </button>

                    <input
                        v-if="!isRecording"
                        v-model="newMessage"
                        type="text"
                        maxlength="5000"
                        placeholder="Type your reply..."
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
                        <span v-else>Send</span>
                    </button>

                    <div v-if="isRecording" class="recording-bar">
                        <button
                            type="button"
                            class="recording-cancel"
                            @click="cancelRecording"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                            >
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

                        <button
                            type="button"
                            class="recording-stop"
                            @click="stopAndSendRecording"
                            :disabled="recordSeconds < 1"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path d="M2 21l21-9L2 3v7l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </form>

            </template>

        </div>


        <!-- Image lightbox -->
        <ImageLightbox
            v-model="lightboxOpen"
            :src="lightboxSrc"
        />

        <!-- 📞 Call widget (incoming + active call UI) -->
        <CallWidget
            ref="callWidgetRef"
            :current-user="currentUser"
        />

    </div>


    <!-- Context menu for messages -->
    <Teleport to="body">
        <div
            v-if="activeMenu && activeMenuMessage"
            class="message-menu"
            :style="menuStyle"
            @click.stop
        >
            <button
                type="button"
                class="menu-item"
                @click="startReply"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polyline points="9 17 4 12 9 7"/>
                    <path d="M20 18v-2a4 4 0 0 0-4-4H4"/>
                </svg>
                Reply
            </button>

            <button
                type="button"
                class="menu-item"
                @click="toggleStar"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    :fill="activeMenuMessage?.is_starred ? 'currentColor' : 'none'"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                {{ activeMenuMessage?.is_starred ? 'Unstar' : 'Star' }}
            </button>

            <button
                type="button"
                class="menu-item"
                @click="deleteForMe"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
                Delete for me
            </button>

            <button
                type="button"
                class="menu-item danger"
                @click="deleteForEveryone"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Delete for everyone
            </button>
        </div>
    </Teleport>


    <!-- Context menu for sidebar users -->
    <Teleport to="body">
        <div
            v-if="userContextMenu.show"
            class="message-menu"
            :style="{
                top: userContextMenu.y + 'px',
                left: userContextMenu.x + 'px',
            }"
            @click.stop
        >
            <button
                type="button"
                class="menu-item"
                @click="contextBlock"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                </svg>
                {{ contextIsBlocked ? 'Unblock User' : 'Block User' }}
            </button>

            <button
                type="button"
                class="menu-item"
                @click="contextArchive"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polyline points="21 8 21 21 3 21 3 8"/>
                    <rect x="1" y="3" width="22" height="5"/>
                    <line x1="10" y1="12" x2="14" y2="12"/>
                </svg>
                {{ contextIsArchived ? 'Unarchive' : 'Archive' }}
            </button>

            <button
                type="button"
                class="menu-item danger"
                @click="contextDelete"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                </svg>
                Delete Conversation
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
    nextTick
} from 'vue';

import EmojiPicker from 'vue3-emoji-picker';
import 'vue3-emoji-picker/css';

import {
    getAdminChats,
    getAdminConversation,
    sendAdminChatMessage,
    markAdminChatAsRead,
    sendAdminTyping,
    deleteAdminChatMessage,
    getCannedResponses,
    searchAdminChat,
    assignConversation,
    blockConversation,
    clearConversation,
    archiveConversation,
    deleteConversation,
    toggleStarMessage,
} from '../services/chat';

import { useAdminChatChannel } from '../composables/useChatChannel';
import { authState } from '../stores/auth';

import VoicePlayer from './VoicePlayer.vue';
import ImageLightbox from './ImageLightbox.vue';

// ⭐ Call widget
import CallWidget from './CallWidget.vue';


/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const conversations = ref([]);
const selectedUser = ref(null);
const selectedConversationId = ref(null);
const selectedUserId = ref(null);

const messages = ref([]);
const newMessage = ref('');
const loadingConversations = ref(false);
const loadingMessages = ref(false);
const sending = ref(false);
const messagesContainer = ref(null);
const fileInput = ref(null);
const selectedFile = ref(null);

const showEmojiPicker = ref(false);
const showCannedPicker = ref(false);
const cannedResponses = ref([]);
const userIsTyping = ref(false);
const replyTo = ref(null);

const assigning = ref(false);
const blocking = ref(false);

const searchQuery = ref('');
const searchResults = ref([]);

const lightboxOpen = ref(false);
const lightboxSrc = ref('');

const activeMenu = ref(null);
const activeMenuMessage = ref(null);
const menuStyle = ref({ top: '0px', left: '0px' });

const showHeaderMenu = ref(false);

const userContextMenu = ref({
    show: false,
    x: 0,
    y: 0,
    conversation: null,
});

// ⭐ Call
const callWidgetRef = ref(null);
const currentUser = computed(() => authState.user);

let pollingTimer = null;
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

const { connected } = useAdminChatChannel({
    onMessage: (payload) => {
        // Only push message if it belongs to the currently selected user
        const belongsToSelected =
            Number(payload.user_id) === Number(selectedUserId.value);

        if (belongsToSelected) {
            if (!messages.value.some((m) => m.id === payload.id)) {
                messages.value.push(payload);
                scrollToBottom();
            }

            if (selectedConversationId.value) {
                markAdminChatAsRead(selectedConversationId.value).catch(() => {});
            }
        }

        loadConversations();
    },

    onNewConversation: () => {
        loadConversations();
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
        if (payload.deleter_type === 'user') {
            messages.value = messages.value.filter(
                (m) => m.id !== payload.id
            );
        }
    },

    onStarred: (payload) => {
        const msg = messages.value.find((m) => m.id === payload.id);
        if (msg) msg.is_starred = payload.is_starred;
    },

    onBlocked: () => {
        loadConversations();
    },

    onTyping: (payload) => {
        if (
            payload.sender_type === 'user' &&
            Number(payload.user_id) === Number(selectedUserId.value)
        ) {
            userIsTyping.value = true;

            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                userIsTyping.value = false;
            }, 3000);
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

const selectedConversation = computed(() =>
    conversations.value.find(
        (c) => Number(c.id) === Number(selectedConversationId.value)
    ) || null
);

const assignedToMe = computed(() => {
    const conv = selectedConversation.value;
    if (!conv?.assigned_to) return false;
    return Number(conv.assigned_to) === Number(authState.user?.id);
});

const conversationIsBlocked = computed(() => {
    return !!selectedConversation.value?.is_blocked;
});

const conversationIsArchived = computed(() => {
    return !!selectedConversation.value?.is_archived;
});

const contextIsBlocked = computed(() => {
    if (!userContextMenu.value.conversation) return false;
    return !!userContextMenu.value.conversation.is_blocked;
});

const contextIsArchived = computed(() => {
    if (!userContextMenu.value.conversation) return false;
    return !!userContextMenu.value.conversation.is_archived;
});


/*
|--------------------------------------------------------------------------
| 📞 Call
|--------------------------------------------------------------------------
*/

const startAudioCall = () => {
    if (!selectedUser.value?.id) {
        alert('Select a user first.');
        return;
    }

    if (!callWidgetRef.value) {
        alert('Call system not ready. Please refresh the page.');
        return;
    }

    callWidgetRef.value.startCall(selectedUser.value.id, 'audio');
};


/*
|--------------------------------------------------------------------------
| Load
|--------------------------------------------------------------------------
*/

const loadConversations = async () => {
    try {
        loadingConversations.value = true;

        const response = await getAdminChats();

        if (response.data?.success) {
            conversations.value =
                response.data.chats
                || response.data.conversations?.data
                || response.data.conversations
                || [];
        }
    } catch (error) {
        console.error('Failed to load conversations:', error);
    } finally {
        loadingConversations.value = false;
    }
};

const loadConversation = async (conversationId, showLoading = true) => {
    try {
        if (showLoading) loadingMessages.value = true;

        const response = await getAdminConversation(conversationId);

        if (response.data?.success) {
            messages.value = response.data.messages || [];

            if (!selectedUser.value) {
                const conversation = conversations.value.find(
                    (item) => Number(item.id) === Number(conversationId)
                );
                selectedUser.value = conversation?.user || null;
            }

            await scrollToBottom();
            await markAdminChatAsRead(conversationId).catch(() => {});
        }
    } catch (error) {
        console.error('Failed to load conversation:', error);
    } finally {
        if (showLoading) loadingMessages.value = false;
    }
};

const loadCanned = async () => {
    try {
        const response = await getCannedResponses();
        if (response.data?.success) {
            cannedResponses.value = response.data.responses || [];
        }
    } catch (error) {
        console.error('Canned responses error:', error);
    }
};


/*
|--------------------------------------------------------------------------
| Select / toggle
|--------------------------------------------------------------------------
*/

const selectConversation = async (conversation) => {
    selectedConversationId.value = conversation.id;
    selectedUserId.value = conversation.user_id;
    selectedUser.value = conversation.user || null;

    searchQuery.value = '';
    searchResults.value = [];

    await loadConversation(conversation.id);
};

const jumpToConversation = async (result) => {
    if (!result.user_id) return;

    const conversation = conversations.value.find(
        (c) => Number(c.user_id) === Number(result.user_id)
    );

    if (conversation) {
        selectedConversationId.value = conversation.id;
        selectedUserId.value = conversation.user_id;
        selectedUser.value = conversation.user || null;

        searchQuery.value = '';
        searchResults.value = [];

        await loadConversation(conversation.id);
    }
};

const toggleCannedPicker = () => {
    showCannedPicker.value = !showCannedPicker.value;
    showEmojiPicker.value = false;
};

const pickCanned = (canned) => {
    newMessage.value = canned.body;
    showCannedPicker.value = false;
};

const onEmojiSelect = (emoji) => {
    newMessage.value += emoji.i || emoji.emoji || '';
};


/*
|--------------------------------------------------------------------------
| Typing
|--------------------------------------------------------------------------
*/

const onInputTyping = () => {
    if (!selectedConversationId.value) return;
    if (typingDebounce) return;

    typingDebounce = setTimeout(() => {
        typingDebounce = null;
    }, 2000);

    sendAdminTyping(selectedConversationId.value).catch(() => {});
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
    const text = newMessage.value.trim();
    const file = selectedFile.value;

    if (!text && !file) return;
    if (!selectedConversationId.value || sending.value) return;

    try {
        sending.value = true;

        const response = await sendAdminChatMessage(
            selectedConversationId.value,
            text,
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
            showCannedPicker.value = false;
            replyTo.value = null;

            await scrollToBottom();
            await loadConversations();
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Send failed.');
    } finally {
        sending.value = false;
    }
};


/*
|--------------------------------------------------------------------------
| Header 3-dot menu
|--------------------------------------------------------------------------
*/

const toggleHeaderMenu = () => {
    showHeaderMenu.value = !showHeaderMenu.value;
};

const closeHeaderMenu = () => {
    showHeaderMenu.value = false;
};

const handleAssignFromMenu = async () => {
    closeHeaderMenu();
    await toggleAssign();
};

const handleBlockFromMenu = async () => {
    closeHeaderMenu();
    await toggleBlock();
};

const handleClearChat = async () => {
    closeHeaderMenu();

    if (!selectedConversationId.value) return;

    if (!confirm('Clear all messages in this conversation? This cannot be undone.')) {
        return;
    }

    try {
        await clearConversation(selectedConversationId.value);
        messages.value = [];
        await loadConversations();
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to clear chat.');
    }
};

const handleArchive = async () => {
    closeHeaderMenu();

    if (!selectedConversationId.value) return;

    try {
        await archiveConversation(selectedConversationId.value);
        await loadConversations();
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to archive.');
    }
};

const handleDeleteConversation = async () => {
    closeHeaderMenu();

    if (!selectedConversationId.value) return;

    if (!confirm('Delete this entire conversation? This cannot be undone.')) {
        return;
    }

    try {
        await deleteConversation(selectedConversationId.value);

        messages.value = [];
        selectedUser.value = null;
        selectedConversationId.value = null;
        selectedUserId.value = null;

        await loadConversations();
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to delete conversation.');
    }
};


/*
|--------------------------------------------------------------------------
| User context menu
|--------------------------------------------------------------------------
*/

const openUserContextMenu = (event, conversation) => {
    userContextMenu.value = {
        show: true,
        x: event.clientX,
        y: event.clientY,
        conversation,
    };
};

const closeUserContextMenu = () => {
    userContextMenu.value.show = false;
};

const contextBlock = async () => {
    const conv = userContextMenu.value.conversation;
    closeUserContextMenu();

    if (!conv) return;

    try {
        await blockConversation(conv.id);
        await loadConversations();
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to block.');
    }
};

const contextArchive = async () => {
    const conv = userContextMenu.value.conversation;
    closeUserContextMenu();

    if (!conv) return;

    try {
        await archiveConversation(conv.id);
        await loadConversations();
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to archive.');
    }
};

const contextDelete = async () => {
    const conv = userContextMenu.value.conversation;
    closeUserContextMenu();

    if (!conv) return;

    if (!confirm(`Delete conversation with ${conv.user?.name || 'this user'}? This cannot be undone.`)) {
        return;
    }

    try {
        await deleteConversation(conv.id);

        if (Number(selectedConversationId.value) === Number(conv.id)) {
            messages.value = [];
            selectedUser.value = null;
            selectedConversationId.value = null;
            selectedUserId.value = null;
        }

        await loadConversations();
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to delete.');
    }
};


/*
|--------------------------------------------------------------------------
| Message context menu
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

    menuStyle.value = {
        top: y + 'px',
        left: x + 'px',
    };
};

const closeMessageMenu = () => {
    activeMenu.value = null;
    activeMenuMessage.value = null;
};

const closeAllMenus = () => {
    closeMessageMenu();
    closeHeaderMenu();
    closeUserContextMenu();
};


/*
|--------------------------------------------------------------------------
| Reply / Star / Delete
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

const deleteForMe = async () => {
    const message = activeMenuMessage.value;
    if (!message) return;
    closeMessageMenu();
    try {
        await deleteAdminChatMessage(message.id, 'me');
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
        await deleteAdminChatMessage(message.id, 'all');
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
        searchResults.value = [];
        return;
    }

    try {
        const response = await searchAdminChat(searchQuery.value);
        if (response.data?.success) {
            searchResults.value = response.data.messages || [];
        }
    } catch (error) {
        console.error('Search failed:', error);
    }
};

const clearSearch = () => {
    searchQuery.value = '';
    searchResults.value = [];
};


/*
|--------------------------------------------------------------------------
| Assign / Block
|--------------------------------------------------------------------------
*/

const toggleAssign = async () => {
    if (!selectedConversationId.value || assigning.value) return;

    assigning.value = true;

    try {
        await assignConversation(selectedConversationId.value);
        await loadConversations();
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to assign.');
    } finally {
        assigning.value = false;
    }
};

const toggleBlock = async () => {
    if (!selectedConversationId.value || blocking.value) return;

    blocking.value = true;

    try {
        let reason = '';
        if (!conversationIsBlocked.value) {
            reason = prompt('Reason for blocking (optional)?') || '';
        }

        await blockConversation(selectedConversationId.value, reason);
        await loadConversations();
    } catch (error) {
        alert(error.response?.data?.message || 'Unable to block.');
    } finally {
        blocking.value = false;
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
        liveBars.value = Array.from({ length: 20 }, () =>
            Math.floor(Math.random() * 70) + 30
        );
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
        alert('Not supported.');
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
    if (!selectedConversationId.value) return;

    let ext = 'webm';
    if (mimeType.includes('mp4')) ext = 'm4a';
    else if (mimeType.includes('ogg')) ext = 'ogg';
    else if (mimeType.includes('wav')) ext = 'wav';
    else if (mimeType.includes('mpeg')) ext = 'mp3';

    const filename = `voice-${Date.now()}.${ext}`;
    const file = new File([blob], filename, { type: mimeType });

    try {
        sending.value = true;

        const response = await sendAdminChatMessage(
            selectedConversationId.value,
            '',
            file,
            replyTo.value?.id || null
        );

        if (response.data?.success && response.data?.message) {
            const msg = response.data.message;
            if (!messages.value.some((m) => m.id === msg.id)) {
                messages.value.push(msg);
            }
            replyTo.value = null;
            await scrollToBottom();
            await loadConversations();
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

const getInitials = (user) => {
    if (!user?.name) return '?';
    const parts = user.name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
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

const startFallbackPolling = () => {
    stopPolling();
    pollingTimer = setInterval(async () => {
        await loadConversations();
        if (selectedConversationId.value) {
            await loadConversation(selectedConversationId.value, false);
        }
    }, 5000);
};

const stopPolling = () => {
    if (pollingTimer) {
        clearInterval(pollingTimer);
        pollingTimer = null;
    }
};

const handleScrollOrResize = () => {
    closeAllMenus();
};


/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    await loadConversations();
    await loadCanned();

    setTimeout(() => {
        if (!connected.value) startFallbackPolling();
    }, 1500);

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

/* =========================================================
   MAIN LAYOUT
========================================================= */

.admin-chat {
    width: 100%;
    height: 100%;
    display: grid;
    grid-template-columns: 320px 1fr;
    background: #ffffff;
    overflow: hidden;
}

/* =========================================================
   LEFT PANEL
========================================================= */

.chat-users {
    display: flex;
    flex-direction: column;
    border-right: 1px solid #e5e7eb;
    min-width: 0;
    height: 100%;
    overflow: hidden;
}

.users-header {
    height: 70px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.users-header h2 {
    margin: 0 0 3px;
    font-size: 16px;
    font-weight: 700;
    color: #111827;
}

.users-header p {
    margin: 0;
    font-size: 12px;
    color: #6b7280;
}

.refresh-button {
    width: 34px;
    height: 34px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.refresh-button:hover { background: #f3f4f6; }

.users-search {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.users-search input {
    flex: 1;
    height: 34px;
    padding: 0 12px;
    border: 1px solid #d1d5db;
    border-radius: 17px;
    outline: none;
    font-size: 13px;
    min-width: 0;
}

.users-search input:focus { border-color: #4f46e5; }

.users-search button {
    width: 24px;
    height: 24px;
    border: none;
    border-radius: 50%;
    background: #dc2626;
    color: white;
    font-size: 14px;
    cursor: pointer;
    line-height: 1;
}

.search-results {
    flex: 1 1 auto;
    overflow-y: auto;
    min-height: 0;
}

.search-results-header {
    padding: 8px 14px;
    font-size: 11px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #f1f5f9;
}

.search-result-item {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 10px 14px;
    border: none;
    border-bottom: 1px solid #f1f5f9;
    background: transparent;
    text-align: left;
    cursor: pointer;
}

.search-result-item:hover { background: #f8fafc; }
.search-result-item strong { font-size: 12px; color: #4f46e5; }
.search-result-item span {
    font-size: 12px;
    color: #6b7280;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-list { flex: 1 1 auto; min-height: 0; overflow-y: auto; }

.user-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: none;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
    transition: background 0.15s ease;
}

.user-item:hover { background: #f8fafc; }
.user-item.active { background: #eef2ff; }

.user-avatar,
.selected-user-avatar {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #4f46e5;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
}

.user-info { min-width: 0; flex: 1; }

.user-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.user-name-row strong {
    color: #111827;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-info p {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.assign-badge {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 6px;
    background: #fef3c7;
    color: #92400e;
    font-size: 10px;
    font-weight: 700;
    border-radius: 4px;
}

.blocked-badge {
    display: inline-block;
    margin-top: 4px;
    margin-left: 4px;
    padding: 2px 6px;
    background: #fee2e2;
    color: #991b1b;
    font-size: 10px;
    font-weight: 700;
    border-radius: 4px;
}

.archive-badge {
    display: inline-block;
    margin-top: 4px;
    margin-left: 4px;
    padding: 2px 6px;
    background: #e0e7ff;
    color: #4338ca;
    font-size: 10px;
    font-weight: 700;
    border-radius: 4px;
}

.unread-count {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: #ef4444;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
}

/* =========================================================
   RIGHT PANEL
========================================================= */

.conversation-panel {
    min-width: 0;
    display: flex;
    flex-direction: column;
    background: #f8fafc;
    height: 100%;
    overflow: hidden;
    position: relative;
}

.conversation-header {
    height: 70px;
    padding: 0 12px 0 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.conversation-header-info {
    flex: 1;
    min-width: 0;
}

.conversation-header h3 {
    margin: 0 0 3px;
    font-size: 15px;
    font-weight: 700;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-header span {
    font-size: 12px;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}

/* 📞 Header call button */
.header-call-btn {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 50%;
    background: #ecfdf5;
    color: #10b981;
    cursor: pointer;
    transition: background 0.15s ease, transform 0.15s ease;
}

.header-call-btn:hover {
    background: #d1fae5;
    transform: scale(1.05);
}

/* Header 3-dot menu */
.header-menu-wrapper {
    position: relative;
    flex-shrink: 0;
}

.header-menu-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    transition: background 0.15s ease;
}

.header-menu-btn:hover {
    background: #f3f4f6;
    color: #111827;
}

.header-menu-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 6px;
    min-width: 220px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    z-index: 200;
    overflow: hidden;
    padding: 4px 0;
}

.header-menu-dropdown .menu-item {
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
    transition: background 0.15s ease;
    text-align: left;
    font-weight: 500;
    white-space: nowrap;
}

.header-menu-dropdown .menu-item:hover {
    background: #f3f4f6;
}

.header-menu-dropdown .menu-item.danger {
    color: #dc2626;
}

.header-menu-dropdown .menu-item.danger:hover {
    background: #fee2e2;
}

.header-menu-dropdown .menu-item svg {
    flex-shrink: 0;
}

/* Messages */
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
.user-row { justify-content: flex-start; }
.admin-row { justify-content: flex-end; }
.system-row { justify-content: center; }

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

.message-bubble-wrapper { position: relative; max-width: 68%; }

.message-bubble {
    padding: 8px 12px;
    border-radius: 12px;
    word-break: break-word;
}

.user-message {
    background: #ffffff;
    color: #1f2937;
    border: 1px solid #e5e7eb;
    border-bottom-left-radius: 4px;
}

.admin-message {
    background: #4f46e5;
    color: #ffffff;
    border-bottom-right-radius: 4px;
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

.admin-message .message-reply-quote {
    background: rgba(255, 255, 255, 0.15);
}

.quote-sender { font-weight: 700; }
.quote-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.message-text { font-size: 14px; line-height: 1.4; white-space: pre-wrap; }
.message-time { font-size: 10px; opacity: 0.65; }

.message-meta {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    margin-top: 4px;
}

.star-indicator {
    color: #f59e0b;
    font-size: 11px;
}

.admin-message .star-indicator {
    color: #fcd34d;
}

.read-tick {
    display: inline-flex;
    align-items: center;
    color: #94a3b8;
}

.read-tick.read { color: #4f46e5; }
.admin-message .read-tick { color: rgba(255, 255, 255, 0.6); }
.admin-message .read-tick.read { color: #ffffff; }

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
    transition: background 0.15s ease;
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

.admin-message .attachment-file { background: rgba(255, 255, 255, 0.15); }
.user-message .attachment-file { background: #f1f5f9; }

.file-icon { font-size: 16px; }
.file-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-size { opacity: 0.7; font-size: 10px; }

.file-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
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
    font-weight: 500;
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
    padding: 8px 14px;
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

.reply-preview-content strong {
    color: #4f46e5;
    font-weight: 700;
    font-size: 11px;
}

.reply-preview-content span {
    color: #6b7280;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

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

.canned-picker {
    position: absolute;
    bottom: 70px;
    left: 12px;
    right: 12px;
    max-height: 300px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    z-index: 100;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.canned-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
    font-weight: 700;
    color: #111827;
}

.canned-header button {
    border: none;
    background: transparent;
    font-size: 20px;
    cursor: pointer;
    line-height: 1;
}

.canned-list {
    overflow-y: auto;
    max-height: 240px;
}

.canned-item {
    display: flex;
    flex-direction: column;
    gap: 3px;
    width: 100%;
    padding: 10px 14px;
    border: none;
    border-bottom: 1px solid #f1f5f9;
    background: transparent;
    text-align: left;
    cursor: pointer;
    transition: background 0.15s ease;
}

.canned-item:hover { background: #f8fafc; }
.canned-item strong { font-size: 13px; color: #111827; }
.canned-item span { font-size: 12px; color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.canned-empty { padding: 20px; text-align: center; color: #6b7280; font-size: 13px; }

.reply-area {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 12px;
    background: #ffffff;
    border-top: 1px solid #e5e7eb;
    flex-shrink: 0;
    position: relative;
}

.reply-area input[type="text"] {
    flex: 1;
    height: 42px;
    padding: 0 16px;
    border: 1px solid #d1d5db;
    border-radius: 21px;
    outline: none;
    font-size: 14px;
    color: #1f2937;
    min-width: 0;
}

.reply-area input[type="text"]:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.10);
}

.emoji-button,
.attach-button,
.mic-button,
.canned-button {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #4f46e5;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease;
    font-size: 20px;
}

.emoji-button:hover:not(:disabled),
.attach-button:hover:not(:disabled),
.mic-button:hover:not(:disabled),
.canned-button:hover:not(:disabled) {
    background: #eef2ff;
}

.emoji-button:disabled,
.attach-button:disabled,
.mic-button:disabled,
.canned-button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.send-button {
    min-width: 76px;
    height: 42px;
    padding: 0 18px;
    flex-shrink: 0;
    border: none;
    border-radius: 21px;
    background: #4f46e5;
    color: #ffffff;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: background 0.15s ease;
}

.send-button:hover:not(:disabled) { background: #4338ca; }
.send-button:disabled { opacity: 0.5; cursor: not-allowed; }

.recording-bar {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    height: 42px;
    padding: 0 8px 0 6px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 21px;
    font-size: 13px;
    min-width: 0;
}

.recording-cancel {
    width: 30px;
    height: 30px;
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

.recording-cancel:hover { background: #fee2e2; }

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
    width: 34px;
    height: 34px;
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

.recording-stop:hover:not(:disabled) { background: #b91c1c; }
.recording-stop:disabled { opacity: 0.5; cursor: not-allowed; }

.no-selected-user,
.no-chats,
.empty-messages,
.loading-users,
.loading-messages {
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #6b7280;
}

.no-selected-user { flex: 1; flex-direction: column; }
.big-chat-icon { font-size: 55px; margin-bottom: 12px; }
.no-selected-user h3 { margin: 0 0 5px; color: #374151; }
.no-selected-user p { margin: 0; font-size: 13px; }

.no-chats { flex: 1; flex-direction: column; }
.no-chat-icon { font-size: 40px; margin-bottom: 10px; }
.no-chats p { margin: 0; font-size: 13px; }

.loading-users { flex: 1; }
.loading-messages, .empty-messages { height: 100%; flex: 1; }

.user-list::-webkit-scrollbar,
.messages-container::-webkit-scrollbar,
.search-results::-webkit-scrollbar {
    width: 6px;
}

.user-list::-webkit-scrollbar-thumb,
.messages-container::-webkit-scrollbar-thumb,
.search-results::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

@media (max-width: 900px) {
    .admin-chat { grid-template-columns: 260px 1fr; }
    .message-bubble-wrapper { max-width: 80%; }
}

@media (max-width: 650px) {
    .admin-chat {
        grid-template-columns: 1fr;
    }

    .chat-users {
        max-height: 220px;
        border-right: none;
        border-bottom: 1px solid #e5e7eb;
    }

    .message-bubble-wrapper { max-width: 85%; }
    .send-button { min-width: 64px; padding: 0 12px; }
}

</style>