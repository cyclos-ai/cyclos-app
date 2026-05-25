<template>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">Filters</h3>
            <Button
                v-if="rules.length"
                label="Clear All"
                text
                size="small"
                severity="secondary"
                @click="clearAll"
            />
        </div>

        <!-- Rules -->
        <div class="space-y-2 mb-3">
            <div
                v-for="(rule, index) in rules"
                :key="index"
                class="flex items-center gap-2"
            >
                <!-- AND/OR connector -->
                <span v-if="index > 0" class="text-xs text-gray-400 w-8 text-center flex-shrink-0">
                    {{ connector }}
                </span>
                <div v-else class="w-8 flex-shrink-0"></div>

                <!-- Field -->
                <Select
                    v-model="rule.field"
                    :options="fields"
                    option-label="label"
                    option-value="value"
                    placeholder="Field"
                    class="flex-1 min-w-0"
                    size="small"
                    @change="onFieldChange(rule)"
                />

                <!-- Operator -->
                <Select
                    v-model="rule.operator"
                    :options="operatorsFor(rule.field)"
                    option-label="label"
                    option-value="value"
                    placeholder="Is"
                    class="w-32 flex-shrink-0"
                    size="small"
                />

                <!-- Value -->
                <component
                    :is="valueComponent(rule)"
                    v-model="rule.value"
                    v-bind="valueProps(rule)"
                    class="flex-1 min-w-0"
                    size="small"
                    @keyup.enter="$emit('apply', buildFilters())"
                />

                <Button
                    icon="pi pi-times"
                    text
                    size="small"
                    severity="secondary"
                    rounded
                    @click="removeRule(index)"
                />
            </div>
        </div>

        <!-- Add rule + apply -->
        <div class="flex items-center gap-2">
            <Button
                label="Add Filter"
                icon="pi pi-plus"
                text
                size="small"
                severity="secondary"
                @click="addRule"
            />
            <div v-if="rules.length > 1" class="flex items-center gap-1">
                <span class="text-xs text-gray-500">Match</span>
                <SelectButton
                    v-model="connector"
                    :options="['AND', 'OR']"
                    size="small"
                />
            </div>
            <Button
                v-if="rules.length"
                label="Apply"
                size="small"
                class="ml-auto"
                @click="$emit('apply', buildFilters())"
            />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';

const props = defineProps({
    fields: {
        type: Array,
        default: () => [],
        // [{ label, value, type: 'text'|'number'|'date'|'select', options: [] }]
    },
});

const emit = defineEmits(['apply', 'clear']);

const rules = ref([]);
const connector = ref('AND');

const operatorsByType = {
    text:   [
        { label: 'contains', value: 'like' },
        { label: 'equals', value: 'eq' },
        { label: 'starts with', value: 'starts' },
        { label: 'is empty', value: 'empty' },
        { label: 'is not empty', value: 'not_empty' },
    ],
    number: [
        { label: '=', value: 'eq' },
        { label: '≠', value: 'neq' },
        { label: '>', value: 'gt' },
        { label: '≥', value: 'gte' },
        { label: '<', value: 'lt' },
        { label: '≤', value: 'lte' },
    ],
    date: [
        { label: 'is', value: 'eq' },
        { label: 'before', value: 'lt' },
        { label: 'after', value: 'gt' },
        { label: 'between', value: 'between' },
    ],
    select: [
        { label: 'is', value: 'eq' },
        { label: 'is not', value: 'neq' },
    ],
};

function fieldDef(fieldValue) {
    return props.fields.find(f => f.value === fieldValue);
}

function operatorsFor(fieldValue) {
    const def = fieldDef(fieldValue);
    return operatorsByType[def?.type || 'text'] || operatorsByType.text;
}

function valueComponent(rule) {
    const def = fieldDef(rule.field);
    if (def?.type === 'date') return DatePicker;
    if (def?.type === 'select') return Select;
    return InputText;
}

function valueProps(rule) {
    const def = fieldDef(rule.field);
    if (def?.type === 'select') {
        return { options: def.options || [], optionLabel: 'label', optionValue: 'value', placeholder: 'Select...' };
    }
    if (def?.type === 'date') {
        return { dateFormat: 'yy-mm-dd', showIcon: true };
    }
    return { placeholder: 'Value...' };
}

function addRule() {
    rules.value.push({ field: props.fields[0]?.value || '', operator: 'eq', value: '' });
}

function removeRule(index) {
    rules.value.splice(index, 1);
}

function onFieldChange(rule) {
    rule.operator = operatorsFor(rule.field)[0]?.value || 'eq';
    rule.value = '';
}

function clearAll() {
    rules.value = [];
    emit('clear');
}

function buildFilters() {
    return {
        connector: connector.value,
        rules: rules.value.filter(r => r.field && (r.value !== '' || r.operator === 'empty' || r.operator === 'not_empty')),
    };
}
</script>
