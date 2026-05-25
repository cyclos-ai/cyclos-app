<template>
    <div>
        <div class="mb-6 flex items-center gap-3">
            <router-link :to="{ name: 'carrier-drayage' }" class="text-gray-400 hover:text-gray-600">
                <i class="pi pi-arrow-left text-lg"></i>
            </router-link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Drayage Execution</h1>
                <p v-if="assignment" class="text-sm text-gray-500 mt-0.5">Container {{ assignment.container_number }}</p>
            </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-16">
            <i class="pi pi-spin pi-spinner text-3xl text-blue-500"></i>
        </div>

        <div v-else-if="assignment" class="space-y-6">
            <!-- Container info card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Container Details</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-gray-400">Container #</p>
                        <p class="text-sm font-medium text-gray-900">{{ assignment.container_number }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">MBL</p>
                        <p class="text-sm font-medium text-gray-900">{{ assignment.mbl_number || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Pickup</p>
                        <p class="text-sm font-medium text-gray-900">{{ assignment.pickup_location || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Delivery</p>
                        <p class="text-sm font-medium text-gray-900">{{ assignment.delivery_location || '—' }}</p>
                    </div>
                </div>
            </div>

            <!-- Step timeline -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-6">Execution Steps</h2>
                <div class="relative">
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                    <div class="space-y-6">
                        <div
                            v-for="(step, index) in steps"
                            :key="step.key"
                            class="relative flex items-start gap-4 pl-10"
                        >
                            <div
                                class="absolute left-0 w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                :class="stepCircleClass(step.key)"
                            >
                                <i :class="`pi ${step.icon} text-sm`"></i>
                            </div>
                            <div class="flex-1 pt-1">
                                <p class="font-medium text-gray-900 text-sm">{{ step.label }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ step.description }}</p>
                            </div>
                            <div class="pt-1">
                                <Button
                                    v-if="isNextStep(step.key)"
                                    :label="`Mark ${step.label}`"
                                    size="small"
                                    :loading="advancing"
                                    @click="advanceStep(step.key)"
                                />
                                <span v-else-if="isCompleted(step.key)" class="text-xs text-green-600 font-medium flex items-center gap-1">
                                    <i class="pi pi-check"></i> Done
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Message v-if="successMessage" severity="success" :closable="false">{{ successMessage }}</Message>
        </div>

        <div v-else class="text-center py-16 text-gray-400">Assignment not found.</div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Button from 'primevue/button';
import Message from 'primevue/message';
import { useCarrierStore } from '@/stores/carrier';

const route = useRoute();
const carrierStore = useCarrierStore();

const loading = ref(false);
const advancing = ref(false);
const assignment = ref(null);
const successMessage = ref('');

const steps = [
    { key: 'pending', label: 'Pending', icon: 'pi-clock', description: 'Awaiting pickup appointment' },
    { key: 'picked_up', label: 'Picked Up', icon: 'pi-box', description: 'Container picked up from terminal' },
    { key: 'in_transit', label: 'In Transit', icon: 'pi-send', description: 'En route to delivery location' },
    { key: 'delivered', label: 'Delivered', icon: 'pi-check-circle', description: 'Delivered to destination' },
    { key: 'empty_returned', label: 'Empty Returned', icon: 'pi-refresh', description: 'Empty container returned' },
];

const stepOrder = steps.map(s => s.key);

function currentIndex() {
    const idx = stepOrder.indexOf(assignment.value?.current_step || 'pending');
    return idx === -1 ? 0 : idx;
}

function isCompleted(key) {
    return stepOrder.indexOf(key) < currentIndex();
}

function isNextStep(key) {
    return stepOrder.indexOf(key) === currentIndex() + 1;
}

function stepCircleClass(key) {
    const idx = stepOrder.indexOf(key);
    const cur = currentIndex();
    if (idx < cur) return 'bg-green-100 text-green-600';
    if (idx === cur) return 'bg-blue-100 text-blue-600';
    return 'bg-gray-100 text-gray-400';
}

async function advanceStep(nextKey) {
    advancing.value = true;
    successMessage.value = '';
    try {
        // API call will be wired when backend is ready
        // await api.post(`/carrier/assignments/${assignment.value.uuid}/step`, { step: nextKey });
        assignment.value.current_step = nextKey;
        successMessage.value = `Step updated to "${steps.find(s => s.key === nextKey)?.label}".`;
    } finally {
        advancing.value = false;
    }
}

onMounted(async () => {
    loading.value = true;
    try {
        await carrierStore.fetchAssignments();
        assignment.value = carrierStore.assignments.find(a => a.uuid === route.params.uuid) || null;
    } finally {
        loading.value = false;
    }
});
</script>
