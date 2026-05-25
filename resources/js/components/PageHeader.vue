<template>
    <div class="mb-6">
        <!-- Breadcrumbs -->
        <Breadcrumb v-if="breadcrumbs?.length" :model="breadcrumbs" class="mb-2 p-0 bg-transparent border-none text-sm" />

        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold text-surface-900 truncate tracking-tight">{{ title }}</h1>
                <p v-if="subtitle" class="text-sm text-surface-500 mt-1">{{ subtitle }}</p>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2 flex-shrink-0">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>

<script setup>
import Breadcrumb from 'primevue/breadcrumb';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    breadcrumbs: { type: Array, default: null },
});
</script>
