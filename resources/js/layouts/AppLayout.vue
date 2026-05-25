<template>
    <div class="flex h-screen bg-surface-50 overflow-hidden" :class="{ 'dark-mode': isDarkMode }">
        <!-- Sidebar -->
        <aside
            class="flex flex-col bg-surface-950 text-white transition-all duration-300 ease-out-quart flex-shrink-0 z-20"
            :class="sidebarCollapsed ? 'w-16' : 'w-64'"
        >
            <!-- Logo -->
            <div class="flex items-center h-16 px-4 border-b border-white/[0.08] flex-shrink-0">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-600 flex-shrink-0">
                    <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <span v-if="!sidebarCollapsed" class="ml-3 font-semibold text-white truncate tracking-tight">
                    Cyclos<span class="text-primary-400">.ai</span>
                </span>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4 scrollbar-thin">
                <div v-for="group in navGroups" :key="group.label" class="mb-6">
                    <p
                        v-if="!sidebarCollapsed && group.label"
                        class="px-4 text-[11px] font-semibold text-surface-500 uppercase tracking-wider mb-2"
                    >
                        {{ group.label }}
                    </p>
                    <router-link
                        v-for="item in group.items"
                        :key="item.name"
                        :to="item.to"
                        class="flex items-center px-4 py-2.5 text-sm transition-all duration-120 ease-out-quart group relative mx-2 rounded-lg"
                        :class="isActive(item)
                            ? 'bg-primary-600/20 text-primary-300'
                            : 'text-surface-400 hover:bg-white/[0.06] hover:text-white'"
                        :title="sidebarCollapsed ? item.label : ''"
                    >
                        <!-- Active indicator bar -->
                        <span
                            v-if="isActive(item)"
                            class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-5 rounded-full bg-primary-400"
                        ></span>
                        <i :class="`pi ${item.icon} text-base flex-shrink-0`"></i>
                        <span v-if="!sidebarCollapsed" class="ml-3 truncate">{{ item.label }}</span>
                        <span
                            v-if="item.badge"
                            class="ml-auto bg-rose-500 text-white text-xs rounded-full px-1.5 py-0.5 min-w-[1.25rem] text-center"
                            :class="{ hidden: sidebarCollapsed }"
                        >
                            {{ item.badge }}
                        </span>
                    </router-link>
                </div>
            </nav>

            <!-- Collapse toggle -->
            <div class="border-t border-white/[0.08] p-2">
                <button
                    @click="toggleSidebar"
                    class="w-full flex items-center justify-center p-2 rounded-lg text-surface-500 hover:text-white hover:bg-white/[0.06] transition-colors duration-120 ease-out-quart"
                >
                    <i :class="`pi ${sidebarCollapsed ? 'pi-chevron-right' : 'pi-chevron-left'}`"></i>
                </button>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex flex-col flex-1 min-w-0 overflow-hidden">
            <!-- Top navbar -->
            <header class="h-16 bg-white border-b border-surface-200 flex items-center px-6 flex-shrink-0 z-10">
                <!-- Search -->
                <div class="flex-1 max-w-md">
                    <SearchBar />
                </div>

                <div class="flex items-center gap-3 ml-auto">
                    <DarkModeToggle v-model="isDarkMode" />
                    <NotificationBell />

                    <!-- User menu -->
                    <div class="flex items-center gap-2 cursor-pointer press-scale" @click="toggleUserMenu">
                        <Avatar
                            :label="authStore.fullName.charAt(0)"
                            shape="circle"
                            class="bg-primary-600 text-white"
                            size="normal"
                        />
                        <div v-if="!sidebarCollapsed" class="hidden md:block text-right">
                            <p class="text-sm font-medium text-surface-900 leading-tight">{{ authStore.fullName }}</p>
                            <p class="text-xs text-surface-500 capitalize">{{ authStore.role }}</p>
                        </div>
                        <i class="pi pi-chevron-down text-surface-400 text-xs"></i>
                    </div>

                    <Menu ref="userMenu" :model="userMenuItems" :popup="true" />
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-6">
                    <router-view />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import Avatar from 'primevue/avatar';
import Menu from 'primevue/menu';
import { useAuthStore } from '@/stores/auth';
import { getNavigationForRole } from '@/config/navigation';
import SearchBar from '@/components/SearchBar.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import DarkModeToggle from '@/components/DarkModeToggle.vue';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const sidebarCollapsed = ref(false);
const isDarkMode = ref(false);
const userMenu = ref(null);

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
}

function toggleUserMenu(event) {
    userMenu.value.toggle(event);
}

function isActive(item) {
    return route.path === item.to || route.path.startsWith(item.to + '/');
}

const userMenuItems = computed(() => [
    {
        label: authStore.fullName,
        items: [
            {
                label: 'Profile',
                icon: 'pi pi-user',
                command: () => router.push({ name: 'settings-organization' }),
            },
            {
                label: 'Settings',
                icon: 'pi pi-cog',
                command: () => router.push({ name: 'settings' }),
            },
            { separator: true },
            {
                label: 'Logout',
                icon: 'pi pi-sign-out',
                command: async () => {
                    await authStore.logout();
                    router.push({ name: 'login' });
                },
            },
        ],
    },
]);

const navGroups = computed(() => getNavigationForRole(authStore.role));

onMounted(async () => {
    if (!authStore.user) {
        await authStore.fetchProfile().catch(() => {});
    }
});
</script>
