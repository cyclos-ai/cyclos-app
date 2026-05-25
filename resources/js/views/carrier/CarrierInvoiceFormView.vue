<template>
    <div>
        <div class="mb-6 flex items-center gap-3">
            <router-link :to="{ name: 'carrier-invoices' }" class="text-gray-400 hover:text-gray-600">
                <i class="pi pi-arrow-left text-lg"></i>
            </router-link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Submit Invoice</h1>
                <p class="text-sm text-gray-500 mt-0.5">Create a new carrier invoice</p>
            </div>
        </div>

        <div class="max-w-2xl">
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Assignment selector -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Assignment</h2>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Container / Assignment</label>
                        <Select
                            v-model="form.assignment_uuid"
                            :options="assignments"
                            option-label="container_number"
                            option-value="uuid"
                            placeholder="Select a container"
                            class="w-full"
                            :class="{ 'p-invalid': errors.assignment_uuid }"
                        />
                        <small v-if="errors.assignment_uuid" class="p-error">{{ errors.assignment_uuid }}</small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount ($)</label>
                        <InputNumber
                            v-model="form.amount"
                            mode="currency"
                            currency="USD"
                            locale="en-US"
                            class="w-full"
                            :class="{ 'p-invalid': errors.amount }"
                            placeholder="0.00"
                        />
                        <small v-if="errors.amount" class="p-error">{{ errors.amount }}</small>
                    </div>
                </div>

                <!-- Line items -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Line Items</h2>
                        <Button
                            type="button"
                            label="Add Line"
                            icon="pi pi-plus"
                            size="small"
                            text
                            @click="addLineItem"
                        />
                    </div>
                    <div
                        v-for="(item, index) in form.line_items"
                        :key="index"
                        class="flex items-center gap-3"
                    >
                        <InputText
                            v-model="item.description"
                            placeholder="Description"
                            class="flex-1"
                        />
                        <InputNumber
                            v-model="item.amount"
                            mode="currency"
                            currency="USD"
                            locale="en-US"
                            placeholder="0.00"
                            class="w-36"
                        />
                        <Button
                            type="button"
                            icon="pi pi-times"
                            text
                            severity="danger"
                            size="small"
                            rounded
                            @click="removeLineItem(index)"
                        />
                    </div>
                    <div v-if="form.line_items.length === 0" class="text-sm text-gray-400 text-center py-4">
                        No line items added
                    </div>
                </div>

                <!-- Notes -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Notes</h2>
                    <Textarea
                        v-model="form.notes"
                        rows="3"
                        placeholder="Additional notes or comments..."
                        class="w-full"
                    />
                </div>

                <Message v-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>
                <Message v-if="successMessage" severity="success" :closable="false">{{ successMessage }}</Message>

                <div class="flex items-center gap-3">
                    <Button
                        type="submit"
                        label="Submit Invoice"
                        icon="pi pi-send"
                        :loading="submitting"
                    />
                    <router-link :to="{ name: 'carrier-invoices' }">
                        <Button type="button" label="Cancel" text />
                    </router-link>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Message from 'primevue/message';
import { useCarrierStore } from '@/stores/carrier';

const router = useRouter();
const carrierStore = useCarrierStore();

const submitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const assignments = ref([]);

const form = reactive({
    assignment_uuid: null,
    amount: null,
    line_items: [],
    notes: '',
});

const errors = reactive({
    assignment_uuid: '',
    amount: '',
});

function addLineItem() {
    form.line_items.push({ description: '', amount: null });
}

function removeLineItem(index) {
    form.line_items.splice(index, 1);
}

function validate() {
    errors.assignment_uuid = '';
    errors.amount = '';
    let valid = true;
    if (!form.assignment_uuid) {
        errors.assignment_uuid = 'Please select a container';
        valid = false;
    }
    if (!form.amount || form.amount <= 0) {
        errors.amount = 'Please enter a valid amount';
        valid = false;
    }
    return valid;
}

async function handleSubmit() {
    if (!validate()) return;

    submitting.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        await carrierStore.submitInvoice({ ...form });
        successMessage.value = 'Invoice submitted successfully.';
        setTimeout(() => router.push({ name: 'carrier-invoices' }), 1500);
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Failed to submit invoice. Please try again.';
    } finally {
        submitting.value = false;
    }
}

onMounted(async () => {
    assignments.value = await carrierStore.fetchAssignments();
});
</script>
