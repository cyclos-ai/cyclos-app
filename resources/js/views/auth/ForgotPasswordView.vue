<template>
    <AuthLayout>
        <div v-if="!sent">
            <h2 class="text-xl font-semibold tracking-tight text-surface-900 mb-1">Reset your password</h2>
            <p class="text-sm text-surface-500 mb-6">Enter your email and we'll send you a reset link.</p>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Email</label>
                    <InputText
                        v-model="email"
                        type="email"
                        placeholder="you@company.com"
                        class="w-full"
                        :class="{ 'p-invalid': error }"
                    />
                    <small v-if="error" class="p-error">{{ error }}</small>
                </div>

                <Button type="submit" label="Send reset link" class="w-full" :loading="loading" />
            </form>
        </div>

        <div v-else class="text-center">
            <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="pi pi-check text-green-600 text-xl"></i>
            </div>
            <h2 class="text-xl font-semibold tracking-tight text-surface-900 mb-2">Check your email</h2>
            <p class="text-sm text-surface-500 mb-6">We've sent a password reset link to <strong>{{ email }}</strong></p>
            <Button label="Back to login" outlined class="w-full" @click="$router.push('/login')" />
        </div>

        <div class="mt-4 text-center">
            <router-link to="/login" class="text-sm text-primary-600 hover:text-primary-700">
                <i class="pi pi-arrow-left text-xs mr-1"></i>Back to login
            </router-link>
        </div>
    </AuthLayout>
</template>

<script setup>
import { ref } from 'vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const email = ref('');
const error = ref('');
const loading = ref(false);
const sent = ref(false);

async function handleSubmit() {
    if (!email.value) { error.value = 'Email is required'; return; }
    loading.value = true;
    try {
        await authStore.forgotPassword(email.value);
        sent.value = true;
    } catch (err) {
        error.value = err.response?.data?.message || 'Something went wrong.';
    } finally {
        loading.value = false;
    }
}
</script>
