<template>
    <Tag :value="label" :severity="severity" :class="sizeClass" />
</template>

<script setup>
import { computed } from 'vue';
import Tag from 'primevue/tag';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
    size: {
        type: String,
        default: 'normal', // normal | small | large
    },
});

const statusMap = {
    active:          { label: 'Active',          severity: 'info' },
    in_transit:      { label: 'In Transit',       severity: 'info' },
    on_vessel:       { label: 'On Vessel',        severity: 'secondary' },
    at_terminal:     { label: 'At Terminal',      severity: 'warn' },
    delivered:       { label: 'Delivered',        severity: 'success' },
    empty_return:    { label: 'Empty Return',     severity: 'success' },
    not_tracking:    { label: 'Not Tracking',     severity: 'secondary' },
    customs_hold:    { label: 'Customs Hold',     severity: 'danger' },
    delayed:         { label: 'Delayed',          severity: 'danger' },
    // Invoice statuses
    pending:         { label: 'Pending',          severity: 'warn' },
    ok_to_pay:       { label: 'OK to Pay',        severity: 'success' },
    paid:            { label: 'Paid',             severity: 'success' },
    disputed:        { label: 'Disputed',         severity: 'danger' },
    void:            { label: 'Void',             severity: 'secondary' },
    // Tracking statuses
    processing:      { label: 'Processing',       severity: 'info' },
    failed:          { label: 'Failed',           severity: 'danger' },
    // Generic
    open:            { label: 'Open',             severity: 'info' },
    closed:          { label: 'Closed',           severity: 'secondary' },
    cancelled:       { label: 'Cancelled',        severity: 'secondary' },
};

const mapped = computed(() => statusMap[props.status] || { label: props.status, severity: 'secondary' });
const label = computed(() => mapped.value.label);
const severity = computed(() => mapped.value.severity);

const sizeClass = computed(() => ({
    'text-xs px-1.5 py-0.5': props.size === 'small',
    'text-sm':                props.size === 'normal',
    'text-base px-3 py-1':   props.size === 'large',
}));
</script>
