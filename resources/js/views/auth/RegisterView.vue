<template>
    <AuthLayout>
        <h2 class="text-xl font-semibold text-surface-900 mb-1 tracking-tight">Create an account</h2>
        <p class="text-sm text-surface-500 mb-6">Request access to Cyclos.ai</p>

        <!-- Success state -->
        <div v-if="submitted" class="text-center py-4">
            <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                <i class="pi pi-check-circle text-green-600 text-3xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-surface-900 mb-2 tracking-tight">Registration submitted</h3>
            <p class="text-sm text-surface-500">Your account is pending approval. You will receive an email once approved.</p>
            <router-link to="/login" class="mt-4 inline-block text-sm text-primary-600 hover:text-primary-700 font-medium">
                Back to sign in
            </router-link>
        </div>

        <!-- Registration form -->
        <form v-else @submit.prevent="handleRegister" class="space-y-5">
            <!-- Step 1: Role picker -->
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-2">I am a...</label>
                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-colors cursor-pointer"
                        :class="form.role_group === 'shipper'
                            ? 'border-primary-600 bg-primary-50 text-primary-700'
                            : 'border-surface-200 text-surface-600 hover:border-surface-300'"
                        @click="form.role_group = 'shipper'"
                    >
                        <i class="pi pi-building text-2xl"></i>
                        <span class="text-sm font-medium">Shipper</span>
                        <span class="text-xs text-center opacity-70">I ship goods and manage freight</span>
                    </button>
                    <button
                        type="button"
                        class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-colors cursor-pointer"
                        :class="form.role_group === 'carrier'
                            ? 'border-primary-600 bg-primary-50 text-primary-700'
                            : 'border-surface-200 text-surface-600 hover:border-surface-300'"
                        @click="form.role_group = 'carrier'"
                    >
                        <i class="pi pi-truck text-2xl"></i>
                        <span class="text-sm font-medium">Carrier</span>
                        <span class="text-xs text-center opacity-70">I transport and deliver containers</span>
                    </button>
                </div>
                <small v-if="errors.role_group" class="p-error">{{ errors.role_group }}</small>
            </div>

            <!-- Step 2: Account details -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">First Name</label>
                    <InputText
                        v-model="form.first_name"
                        class="w-full"
                        placeholder="Jane"
                        :class="{ 'p-invalid': errors.first_name }"
                    />
                    <small v-if="errors.first_name" class="p-error">{{ errors.first_name }}</small>
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Last Name</label>
                    <InputText
                        v-model="form.last_name"
                        class="w-full"
                        placeholder="Doe"
                        :class="{ 'p-invalid': errors.last_name }"
                    />
                    <small v-if="errors.last_name" class="p-error">{{ errors.last_name }}</small>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Email</label>
                <InputText
                    v-model="form.email"
                    type="email"
                    class="w-full"
                    placeholder="you@company.com"
                    autocomplete="email"
                    :class="{ 'p-invalid': errors.email }"
                />
                <small v-if="errors.email" class="p-error">{{ errors.email }}</small>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Password</label>
                <Password
                    v-model="form.password"
                    class="w-full"
                    input-class="w-full"
                    placeholder="••••••••"
                    toggle-mask
                    :class="{ 'p-invalid': errors.password }"
                />
                <small v-if="errors.password" class="p-error">{{ errors.password }}</small>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Confirm Password</label>
                <Password
                    v-model="form.password_confirmation"
                    class="w-full"
                    input-class="w-full"
                    placeholder="••••••••"
                    :feedback="false"
                    toggle-mask
                    :class="{ 'p-invalid': errors.password_confirmation }"
                />
                <small v-if="errors.password_confirmation" class="p-error">{{ errors.password_confirmation }}</small>
            </div>

            <!-- Step 3: Company -->
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Company Name</label>
                <InputText
                    v-model="form.company_name"
                    class="w-full"
                    placeholder="Acme Logistics"
                    :class="{ 'p-invalid': errors.company_name }"
                />
                <small v-if="errors.company_name" class="p-error">{{ errors.company_name }}</small>
            </div>

            <Message v-if="errorMessage" severity="error" :closable="false" class="text-sm">
                {{ errorMessage }}
            </Message>

            <Button
                type="submit"
                label="Request Access"
                class="w-full"
                :loading="loading"
            />
        </form>

        <p class="text-center text-sm text-surface-500 mt-4">
            Already have an account?
            <router-link to="/login" class="text-primary-600 hover:text-primary-700 font-medium">Sign in</router-link>
        </p>
    </AuthLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Message from 'primevue/message';
import AuthLayout from '@/layouts/AuthLayout.vue';
import api from '@/plugins/api';

const loading = ref(false);
const submitted = ref(false);
const errorMessage = ref('');

const form = reactive({
    role_group: '',
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_name: '',
});

const errors = reactive({
    role_group: '',
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_name: '',
});

function validate() {
    Object.keys(errors).forEach(k => (errors[k] = ''));
    let valid = true;

    if (!form.role_group) { errors.role_group = 'Please select your role'; valid = false; }
    if (!form.first_name) { errors.first_name = 'Required'; valid = false; }
    if (!form.last_name) { errors.last_name = 'Required'; valid = false; }
    if (!form.email || !/\S+@\S+\.\S+/.test(form.email)) { errors.email = 'Valid email required'; valid = false; }
    if (!form.password || form.password.length < 8) { errors.password = 'Password must be at least 8 characters'; valid = false; }
    if (form.password !== form.password_confirmation) { errors.password_confirmation = 'Passwords do not match'; valid = false; }
    if (!form.company_name) { errors.company_name = 'Required'; valid = false; }

    return valid;
}

async function handleRegister() {
    if (!validate()) return;

    loading.value = true;
    errorMessage.value = '';

    try {
        await api.post('/auth/register', { ...form });
        submitted.value = true;
    } catch (err) {
        const status = err.response?.status;
        if (status === 422) {
            const validationErrors = err.response?.data?.errors || {};
            Object.keys(validationErrors).forEach(key => {
                if (errors[key] !== undefined) {
                    errors[key] = validationErrors[key][0];
                }
            });
        } else {
            errorMessage.value = err.response?.data?.message || 'Registration failed. Please try again.';
        }
    } finally {
        loading.value = false;
    }
}
</script>
