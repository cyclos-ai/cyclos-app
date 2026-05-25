<template>
    <div class="relative">
        <Button
            icon="pi pi-bell"
            text
            rounded
            class="relative"
            @click="toggle"
        >
            <Badge
                v-if="unreadCount > 0"
                :value="unreadCount > 99 ? '99+' : unreadCount"
                severity="danger"
                class="absolute -top-1 -right-1 text-xs"
            />
        </Button>

        <div
            v-if="isOpen"
            class="absolute right-0 top-full mt-2 w-80 bg-white border border-surface-200 rounded-xl shadow-xl z-50"
        >
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-surface-100">
                <h3 class="text-sm font-semibold text-surface-900">Notifications</h3>
                <Button
                    v-if="unreadCount"
                    label="Mark all read"
                    text
                    size="small"
                    severity="secondary"
                    @click="markAllRead"
                />
            </div>

            <!-- List -->
            <div class="max-h-96 overflow-y-auto divide-y divide-surface-50">
                <div
                    v-for="n in notifications"
                    :key="n.uuid"
                    class="flex items-start gap-3 px-4 py-3 hover:bg-surface-50 cursor-pointer transition-colors"
                    :class="{ 'bg-primary-50': !n.read_at }"
                    @click="handleNotification(n)"
                >
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                        :class="notificationBg(n.type)"
                    >
                        <i :class="`pi ${notificationIcon(n.type)} text-xs`" :style="{ color: notificationColor(n.type) }"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-surface-900 leading-tight">{{ n.title }}</p>
                        <p class="text-xs text-surface-500 mt-0.5 line-clamp-2">{{ n.body }}</p>
                        <p class="text-xs text-surface-400 mt-1">{{ formatTime(n.created_at) }}</p>
                    </div>
                    <div v-if="!n.read_at" class="w-2 h-2 rounded-full bg-primary-500 flex-shrink-0 mt-2"></div>
                </div>

                <div v-if="!notifications.length" class="py-8 text-center text-surface-400">
                    <i class="pi pi-bell text-2xl mb-2 block"></i>
                    <p class="text-sm">No notifications</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t border-surface-100 px-4 py-2">
                <Button label="View all notifications" text size="small" class="w-full" />
            </div>
        </div>

        <!-- Backdrop -->
        <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import api from '@/plugins/api';

dayjs.extend(relativeTime);

const isOpen = ref(false);
const notifications = ref([]);

const unreadCount = computed(() => notifications.value.filter(n => !n.read_at).length);

function toggle() {
    isOpen.value = !isOpen.value;
    if (isOpen.value) fetchNotifications();
}

async function fetchNotifications() {
    try {
        const response = await api.get('/notifications', { params: { per_page: 20 } });
        notifications.value = response.data.data || response.data;
    } catch {
        // silent
    }
}

async function markAllRead() {
    await api.post('/notifications/mark-all-read');
    notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString() }));
}

function handleNotification(n) {
    if (!n.read_at) {
        api.patch(`/notifications/${n.uuid}/read`).catch(() => {});
        const idx = notifications.value.findIndex(x => x.uuid === n.uuid);
        if (idx !== -1) notifications.value[idx] = { ...n, read_at: new Date().toISOString() };
    }
    isOpen.value = false;
}

const typeMap = {
    demurrage_alarm:  { icon: 'pi-clock',        bg: 'bg-orange-50', color: '#f97316' },
    lfd_approaching:  { icon: 'pi-exclamation-triangle', bg: 'bg-yellow-50', color: '#eab308' },
    vessel_arrived:   { icon: 'pi-map-marker',   bg: 'bg-sky-50',       color: '#0ea5e9' },
    container_status: { icon: 'pi-box',          bg: 'bg-primary-50',  color: '#06c4a7' },
    invoice_due:      { icon: 'pi-receipt',      bg: 'bg-red-50',    color: '#ef4444' },
};

function notificationIcon(type) { return typeMap[type]?.icon || 'pi-bell'; }
function notificationBg(type) { return typeMap[type]?.bg || 'bg-surface-50'; }
function notificationColor(type) { return typeMap[type]?.color || '#6b7280'; }
function formatTime(date) { return dayjs(date).fromNow(); }

onMounted(fetchNotifications);
</script>
