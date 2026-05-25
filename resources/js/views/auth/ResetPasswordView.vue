<template>
    <AuthLayout>
        <h2 class="text-xl font-semibold tracking-tight text-surface-900 mb-1">Set new password</h2>
        <p class="text-sm text-surface-500 mb-6">Choose a strong password for your account.</p>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">New Password</label>
                <Password
                    v-model="form.password"
                    placeholder="At least 8 characters"
                    class="w-full"
                    input-class="w-full"
                    toggle-mask
                    :class="{ 'p-invalid': errors.password }"
                />
                <small v-if="errors.password" class="p-error">{{ errors.password }}</small>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Confirm Password</label>
                <Password
                    v-model="form.password_confirmation"
                    placeholder="Repeat password"
                    class="w-full"
                    input-class="w-full"
                    :feedback="false"
                    toggle-mask
                    :class="{ 'p-invalid': errors.password_confirmation }"
                />
                <small v-if="errors.password_confirmation" class="p-error">{{ errors.password_confirmation }}</small>
            </div>

            <Message v-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>

            <Button type="submit" label="Reset Password" class="w-full" :loading="loading" />
        </form>
    </AuthLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Message from 'primevue/message';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const loading = ref(false);
const errorMessage = ref('');
const form = reactive({ password: '', password_confirmation: '' });
const errors = reactive({ password: '', password_confirmation: '' });

async function handleSubmit() {
    errors.password = '';
    errors.password_confirmation = '';

    if (!form.password || form.password.length < 8) {
        errors.password = 'Password must be at least 8 characters';
        return;
    }
    if (form.password !== form.password_confirmation) {
        errors.password_confirmation = 'Passwords do not match';
        return;
    }

    loading.value = true;
    try {
        await authStore.resetPassword({
            token: route.query.token,
            email: route.query.email,
            password: form.password,
            password_confirmation: form.password_confirmation,
        });
        router.push({ name: 'login' });
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Failed to reset password.';
    } finally {
        loading.value = false;
    }
}
</script>
