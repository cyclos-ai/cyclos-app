<template>
    <div class="relative w-full h-full min-h-[200px]">
        <Bar v-if="type === 'bar'" :data="data" :options="mergedOptions" />
        <Line v-else-if="type === 'line'" :data="data" :options="mergedOptions" />
        <Pie v-else-if="type === 'pie'" :data="data" :options="mergedOptions" />
        <Doughnut v-else-if="type === 'doughnut'" :data="data" :options="mergedOptions" />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Bar, Line, Pie, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
);

const props = defineProps({
    type: {
        type: String,
        default: 'bar', // bar | line | pie | doughnut
    },
    data: {
        type: Object,
        required: true,
    },
    options: {
        type: Object,
        default: () => ({}),
    },
});

const defaultOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                boxWidth: 12,
                font: { size: 11 },
            },
        },
        tooltip: {
            backgroundColor: '#1e293b',
            titleFont: { size: 12 },
            bodyFont: { size: 11 },
            padding: 10,
            cornerRadius: 8,
        },
    },
    scales: props.type === 'bar' || props.type === 'line' ? {
        x: {
            grid: { display: false },
            ticks: { font: { size: 11 } },
        },
        y: {
            grid: { color: '#f1f5f9' },
            ticks: { font: { size: 11 } },
        },
    } : undefined,
};

const mergedOptions = computed(() => ({
    ...defaultOptions,
    ...props.options,
    plugins: {
        ...defaultOptions.plugins,
        ...props.options?.plugins,
    },
}));
</script>
