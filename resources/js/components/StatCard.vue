<template>
    <div class="bg-white rounded-xl border border-surface-200 p-5 press-scale
                hover:shadow-md hover:-translate-y-0.5
                transition-all duration-200 ease-out-quart">
        <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
                <p class="text-sm text-surface-600 font-medium truncate">{{ label }}</p>
                <p class="text-3xl font-bold text-surface-900 mt-1 leading-tight tracking-tight">
                    {{ formattedValue }}
                </p>
                <div v-if="trend !== null" class="flex items-center gap-1 mt-2">
                    <span
                        class="inline-flex items-center gap-0.5 text-xs font-medium px-1.5 py-0.5 rounded-full"
                        :class="trendUp
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'bg-rose-50 text-rose-700'"
                    >
                        <i
                            :class="['pi text-[10px]', trendUp ? 'pi-arrow-up' : 'pi-arrow-down']"
                        ></i>
                        {{ Math.abs(trend) }}%
                    </span>
                    <span class="text-xs text-surface-400">vs last period</span>
                </div>
            </div>
            <div
                class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ml-4"
                :class="iconBgClass"
            >
                <i :class="`pi ${icon} text-lg`" :style="{ color: iconColor }"></i>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    icon: { type: String, default: 'pi-chart-bar' },
    iconColor: { type: String, default: '#06c4a7' },
    iconBg: { type: String, default: 'bg-primary-50' },
    trend: { type: Number, default: null },
    format: { type: String, default: 'number' },
    prefix: { type: String, default: '' },
    suffix: { type: String, default: '' },
});

const trendUp = computed(() => props.trend >= 0);

const iconBgClass = computed(() => props.iconBg);

const formattedValue = computed(() => {
    const v = props.value;
    if (props.format === 'currency') {
        return `$${Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
    }
    if (props.format === 'percent') {
        return `${v}%`;
    }
    if (typeof v === 'number') {
        return `${props.prefix}${v.toLocaleString()}${props.suffix}`;
    }
    return `${props.prefix}${v}${props.suffix}`;
});
</script>
