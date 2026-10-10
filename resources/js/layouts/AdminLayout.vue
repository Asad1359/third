<template>
    <div class="admin-layout">

        <!-- SIDEBAR -->
        <aside
            class="sidebar"
            :class="{ 'sidebar-open': sidebarOpen }"
        >
            <div class="brand">
                <div class="brand-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <span>BrandX</span>
            </div>

            <div class="user-info">
                <div class="avatar">{{ userInitial }}</div>
                <div class="user-details">
                    <strong>{{ userName }}</strong>
                    <small>{{ userEmail }}</small>
                </div>
            </div>

            <nav class="sidebar-nav">
                <router-link :to="{ name: 'admin.dashboard' }" class="nav-item" @click="closeSidebar">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard</span>
                </router-link>
                <router-link :to="{ name: 'admin.users' }" class="nav-item" @click="closeSidebar">
                    <i class="fa-solid fa-users"></i>
                    <span>Users</span>
                </router-link>
                <router-link :to="{ name: 'admin.bags' }" class="nav-item" @click="closeSidebar">
                    <i class="fa-solid fa-box-open"></i>
                    <span>Bags</span>
                </router-link>
                <router-link :to="{ name: 'admin.chats' }" class="nav-item" @click="closeSidebar">
                    <i class="fa-solid fa-comments"></i>
                    <span>Customer Chats</span>
                </router-link>
                <router-link to="/dashboard/groups" class="nav-item" @click="closeSidebar">
                    <i class="fa-solid fa-users"></i>
                    <span>Groups</span>
                </router-link>
                <router-link :to="{ name: 'admin.profile' }" class="nav-item" @click="closeSidebar">
                    <i class="fa-solid fa-user"></i>
                    <span>Profile</span>
                </router-link>
                <router-link :to="{ name: 'admin.theme' }" class="nav-item" @click="closeSidebar">
                    <i class="fa-solid fa-palette"></i>
                    <span>Theme</span>
                </router-link>
                <router-link :to="{ name: 'admin.change-password' }" class="nav-item" @click="closeSidebar">
                    <i class="fa-solid fa-lock"></i>
                    <span>Password</span>
                </router-link>
            </nav>

            <div class="sidebar-bottom">
                <button class="logout-btn" @click="handleLogout" :disabled="loggingOut">
                    <i class="fa-solid" :class="loggingOut ? 'fa-spinner fa-spin' : 'fa-right-from-bracket'"></i>
                    <span>{{ loggingOut ? 'Logging out...' : 'Logout' }}</span>
                </button>
            </div>
        </aside>

        <div v-if="sidebarOpen" class="sidebar-overlay" @click="closeSidebar"></div>

        <!-- MAIN -->
        <div class="main-wrapper">

            <header class="top-header">
                <div class="header-left">
                    <button class="menu-btn" @click="toggleSidebar">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div>
                        <h1>{{ pageTitle }}</h1>
                        <p>Admin Control Panel</p>
                    </div>
                </div>

                <div class="header-right">
                    <div class="header-user">
                        <div class="header-avatar">{{ userInitial }}</div>
                        <div class="header-user-info">
                            <strong>{{ userName }}</strong>
                            <small>Administrator</small>
                        </div>
                    </div>
                </div>
            </header>

            <main class="main-content">
                <router-view />
            </main>

        </div>

        <!-- 📞 Call widget (incoming + active call UI) -->
        <CallWidget :current-user="user" />

    </div>
</template>


<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { authState, loadUser, logout } from '../stores/auth';

// ⭐ Call widget
import CallWidget from '../components/CallWidget.vue';

const router = useRouter();
const route = useRoute();
const sidebarOpen = ref(false);
const loggingOut = ref(false);

const user = computed(() => authState.user || {});
const userName = computed(() => user.value.name || 'Admin');
const userEmail = computed(() => user.value.email || '');
const userInitial = computed(() => {
    const name = userName.value.trim();
    if (!name) return 'A';
    const parts = name.split(/\s+/);
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
});

const pageTitle = computed(() => {
    const titles = {
        'admin.dashboard': 'Dashboard',
        'admin.users': 'Users',
        'admin.bags': 'Bags',
        'admin.bags.create': 'Add Bag',
        'admin.bags.show': 'Bag Details',
        'admin.bags.edit': 'Edit Bag',
        'admin.chats': 'Customer Chats',
        'admin.profile': 'Profile',
        'admin.theme': 'Theme',
        'admin.change-password': 'Change Password',
    };
    return titles[route.name] || 'Dashboard';
});

function toggleSidebar() { sidebarOpen.value = !sidebarOpen.value; }
function closeSidebar() { sidebarOpen.value = false; }

async function handleLogout() {
    if (loggingOut.value) return;
    loggingOut.value = true;
    try {
        await logout();
    } catch (error) {
        console.error('Logout error:', error);
    } finally {
        loggingOut.value = false;
        sidebarOpen.value = false;
        router.push({ name: 'login' });
    }
}

onMounted(async () => {
    try {
        if (!authState.initialized) await loadUser();
    } catch (error) {
        console.error('Admin layout error:', error);
    }
});
</script>


<style scoped>

* { box-sizing: border-box; }

.admin-layout {
    height: 100vh;
    overflow: hidden;
    background: #f5f7fb;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background: #111827;
    color: white;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
}

.brand {
    height: 72px;
    padding: 0 22px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.brand-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #1d4ed8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}

.brand span { font-size: 21px; font-weight: 700; }

.user-info {
    padding: 22px 18px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.avatar,
.header-avatar {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    border-radius: 50%;
    background: #1d4ed8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
}

.user-details {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.user-details strong {
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-details small {
    margin-top: 3px;
    color: #9ca3af;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-nav {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 18px 12px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.sidebar-nav::-webkit-scrollbar { width: 5px; }
.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.10);
    border-radius: 10px;
}

.nav-item {
    height: 46px;
    flex-shrink: 0;
    padding: 0 15px;
    display: flex;
    align-items: center;
    gap: 13px;
    border-radius: 9px;
    color: #cbd5e1;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s ease, color 0.2s ease;
}

.nav-item i {
    width: 20px;
    flex-shrink: 0;
    text-align: center;
}

.nav-item:hover { background: #1f2937; color: white; }
.nav-item.router-link-active { background: #1d4ed8; color: white; }

.sidebar-bottom {
    margin-top: auto;
    flex-shrink: 0;
    padding: 15px 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.logout-btn {
    width: 100%;
    height: 45px;
    border: 0;
    border-radius: 9px;
    background: #1f2937;
    color: #fca5a5;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 13px;
    padding: 0 15px;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s ease;
}

.logout-btn:hover:not(:disabled) { background: #374151; }
.logout-btn:disabled { opacity: 0.7; cursor: not-allowed; }

/* =========================================================
   MAIN WRAPPER — FIXED HEIGHT
========================================================= */

.main-wrapper {
    margin-left: 250px;
    height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* =========================================================
   HEADER — FIXED
========================================================= */

.top-header {
    height: 72px;
    flex-shrink: 0;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    z-index: 900;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-left h1 {
    margin: 0;
    color: #111827;
    font-size: 20px;
    font-weight: 700;
}

.header-left p {
    margin: 3px 0 0;
    color: #6b7280;
    font-size: 12px;
}

.menu-btn {
    display: none;
    border: 0;
    background: #f3f4f6;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 18px;
}

.header-user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-avatar {
    width: 38px;
    height: 38px;
    font-size: 14px;
}

.header-user-info {
    display: flex;
    flex-direction: column;
}

.header-user-info strong {
    font-size: 13px;
    color: #111827;
}

.header-user-info small {
    color: #6b7280;
    font-size: 11px;
}

/* =========================================================
   MAIN CONTENT — FIXED (NO SCROLL AT LAYOUT LEVEL)
========================================================= */

.main-content {
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
    padding: 0;
    margin: 0;
    width: 100%;
    background: #f5f7fb;
    display: flex;
    flex-direction: column;
}

.sidebar-overlay { display: none; }

/* =========================================================
   DARK MODE
========================================================= */

:global(html.dark) .admin-layout { background: #0b0f17 !important; color: #e5e7eb !important; }
:global(html.dark) .sidebar { background: #0f172a !important; border-right: 1px solid #1f2937; }
:global(html.dark) .brand { border-bottom-color: #1f2937 !important; }
:global(html.dark) .brand span { color: #f9fafb !important; }
:global(html.dark) .brand-icon { background: #1d4ed8 !important; }
:global(html.dark) .user-info { border-bottom-color: #1f2937 !important; }
:global(html.dark) .avatar,
:global(html.dark) .header-avatar { background: #1d4ed8 !important; color: #ffffff !important; }
:global(html.dark) .user-details strong { color: #f9fafb !important; }
:global(html.dark) .user-details small { color: #94a3b8 !important; }
:global(html.dark) .nav-item { color: #cbd5e1 !important; }
:global(html.dark) .nav-item i { color: #94a3b8 !important; }
:global(html.dark) .nav-item:hover { background: #1f2937 !important; color: #ffffff !important; }
:global(html.dark) .nav-item:hover i { color: #ffffff !important; }
:global(html.dark) .nav-item.router-link-active { background: #1d4ed8 !important; color: #ffffff !important; }
:global(html.dark) .nav-item.router-link-active i { color: #ffffff !important; }
:global(html.dark) .sidebar-bottom { border-top-color: #1f2937 !important; }
:global(html.dark) .logout-btn { background: #1f2937 !important; color: #fca5a5 !important; }
:global(html.dark) .logout-btn:hover { background: #334155 !important; }
:global(html.dark) .main-wrapper { background: #0b0f17 !important; }
:global(html.dark) .top-header { background: #111827 !important; border-bottom-color: #1f2937 !important; }
:global(html.dark) .header-left h1 { color: #f9fafb !important; }
:global(html.dark) .header-left p { color: #94a3b8 !important; }
:global(html.dark) .menu-btn { background: #1e293b !important; color: #e5e7eb !important; }
:global(html.dark) .header-user-info strong { color: #f9fafb !important; }
:global(html.dark) .header-user-info small { color: #94a3b8 !important; }
:global(html.dark) .main-content { background: #0b0f17 !important; color: #e5e7eb !important; }
:global(html.dark) .sidebar-overlay { background: rgba(0, 0, 0, 0.7) !important; }

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.sidebar-open { transform: translateX(0); }
    .main-wrapper { margin-left: 0; }
    .menu-btn { display: flex; align-items: center; justify-content: center; }
    .header-user-info { display: none; }
    .top-header { padding: 0 18px; }
    .sidebar-overlay {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 999;
    }
}

</style>