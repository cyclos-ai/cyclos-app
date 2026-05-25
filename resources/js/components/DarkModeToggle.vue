<template>
    <button
        class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors"
        :title="modelValue ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggle"
    >
        <i :class="`pi ${modelValue ? 'pi-sun' : 'pi-moon'}`"></i>
    </button>
</template>

<script setup>
import { watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function toggle() {
    emit('update:modelValue', !props.modelValue);
}

watch(() => props.modelValue, (isDark) => {
    document.documentElement.classList.toggle('dark-mode', isDark);
    localStorage.setItem('dark-mode', isDark ? '1' : '0');
}, { immediate: true });
</script>
