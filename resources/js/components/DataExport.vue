<template>
    <div class="inline-block">
        <Button
            label="Export"
            icon="pi pi-download"
            outlined
            size="small"
            @click="toggle"
        />
        <Menu ref="menu" :model="items" :popup="true" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import Menu from 'primevue/menu';

const props = defineProps({
    formats: {
        type: Array,
        default: () => ['csv', 'xlsx', 'pdf'],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['export']);

const menu = ref(null);

function toggle(event) {
    menu.value.toggle(event);
}

const formatLabels = {
    csv:  { label: 'Export CSV',  icon: 'pi pi-file' },
    xlsx: { label: 'Export Excel', icon: 'pi pi-file-excel' },
    pdf:  { label: 'Export PDF',  icon: 'pi pi-file-pdf' },
};

const items = props.formats.map(fmt => ({
    label: formatLabels[fmt]?.label || fmt.toUpperCase(),
    icon: formatLabels[fmt]?.icon || 'pi pi-file',
    command: () => emit('export', fmt),
}));
</script>
