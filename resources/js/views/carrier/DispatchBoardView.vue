<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Dispatch Board</h1>
            <p class="text-sm text-gray-500 mt-1">Real-time view of all active dispatches</p>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-16">
            <i class="pi pi-spin pi-spinner text-3xl text-blue-500"></i>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Pending Pickup column -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>
                    <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Pending Pickup</h2>
                    <span class="ml-auto bg-yellow-100 text-yellow-700 text-xs font-medium px-2 py-0.5 rounded-full">
                        {{ byStatus('pending').length }}
                    </span>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="item in byStatus('pending')"
                        :key="item.uuid"
                        class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm"
                    >
                        <p class="font-semibold text-gray-900 text-sm">{{ item.container_number }}</p>
                        <p class="text-xs text-gray-500 mt-1">Driver: {{ item.driver_name || 'Unassigned' }}</p>
                        <p class="text-xs text-gray-400 mt-1 truncate">{{ item.pickup_location }}</p>
                        <p class="text-xs text-gray-400 truncate">→ {{ item.delivery_location }}</p>
                    </div>
                    <div v-if="byStatus('pending').length === 0" class="text-center py-6 text-gray-300 text-sm">
                        No pending pickups
                    </div>
                </div>
            </div>

            <!-- In Transit column -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                    <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">In Transit</h2>
                    <span class="ml-auto bg-blue-100 text-blue-700 text-xs font-medium px-2 py-0.5 rounded-full">
                        {{ byStatus('in_transit').length }}
                    </span>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="item in byStatus('in_transit')"
                        :key="item.uuid"
                        class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm"
                    >
                        <p class="font-semibold text-gray-900 text-sm">{{ item.container_number }}</p>
                        <p class="text-xs text-gray-500 mt-1">Driver: {{ item.driver_name || 'Unassigned' }}</p>
                        <p class="text-xs text-gray-400 mt-1 truncate">{{ item.pickup_location }}</p>
                        <p class="text-xs text-gray-400 truncate">→ {{ item.delivery_location }}</p>
                    </div>
                    <div v-if="byStatus('in_transit').length === 0" class="text-center py-6 text-gray-300 text-sm">
                        No active transits
                    </div>
                </div>
            </div>

            <!-- Delivering column -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
                    <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Delivering</h2>
                    <span class="ml-auto bg-green-100 text-green-700 text-xs font-medium px-2 py-0.5 rounded-full">
                        {{ byStatus('picked_up').length }}
                    </span>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="item in byStatus('picked_up')"
                        :key="item.uuid"
                        class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm"
                    >
                        <p class="font-semibold text-gray-900 text-sm">{{ item.container_number }}</p>
                        <p class="text-xs text-gray-500 mt-1">Driver: {{ item.driver_name || 'Unassigned' }}</p>
                        <p class="text-xs text-gray-400 mt-1 truncate">{{ item.pickup_location }}</p>
                        <p class="text-xs text-gray-400 truncate">→ {{ item.delivery_location }}</p>
                    </div>
                    <div v-if="byStatus('picked_up').length === 0" class="text-center py-6 text-gray-300 text-sm">
                        None delivering
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCarrierStore } from '@/stores/carrier';

const carrierStore = useCarrierStore();
const loading = ref(false);

function byStatus(status) {
    return carrierStore.assignments.filter(a => a.status === status);
}

onMounted(async () => {
    loading.value = true;
    try {
        await carrierStore.fetchAssignments();
    } finally {
        loading.value = false;
    }
});
</script>
