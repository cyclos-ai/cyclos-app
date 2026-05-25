<template>
    <AuthLayout>
        <h2 class="text-xl font-semibold text-surface-900 mb-1 tracking-tight">Welcome back</h2>
        <p class="text-sm text-surface-500 mb-6">Sign in to your account</p>

        <form @submit.prevent="handleLogin" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Email</label>
                <InputText
                    v-model="form.email"
                    type="email"
                    placeholder="you@company.com"
                    class="w-full"
                    :class="{ 'p-invalid': errors.email }"
                    autocomplete="email"
                />
                <small v-if="errors.email" class="p-error">{{ errors.email }}</small>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Password</label>
                <Password
                    v-model="form.password"
                    placeholder="••••••••"
                    class="w-full"
                    input-class="w-full"
                    :feedback="false"
                    toggle-mask
                    :class="{ 'p-invalid': errors.password }"
                    autocomplete="current-password"
                />
                <small v-if="errors.password" class="p-error">{{ errors.password }}</small>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.remember" input-id="remember" binary />
                    <label for="remember" class="text-sm text-surface-600 cursor-pointer">Remember me</label>
                </div>
                <router-link
                    to="/forgot-password"
                    class="text-sm text-primary-600 hover:text-primary-700 font-medium"
                >
                    Forgot password?
                </router-link>
            </div>

            <Message v-if="errorMessage" severity="error" :closable="false" class="text-sm">
                {{ errorMessage }}
            </Message>

            <Button
                type="submit"
                label="Sign in"
                class="w-full"
                :loading="loading"
            />
        </form>

        <p class="text-center text-sm text-surface-500 mt-4">
            Don't have an account?
            <router-link to="/register" class="text-primary-600 hover:text-primary-700 font-medium">Register</router-link>
        </p>

        <!-- SSO -->
        <div v-if="showSso" class="mt-4">
            <Divider><span class="text-xs text-surface-400">or</span></Divider>
            <Button
                label="Continue with SSO"
                icon="pi pi-building"
                outlined
                class="w-full"
                @click="handleSso"
            />
        </div>
    </AuthLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import Message from 'primevue/message';
import Divider from 'primevue/divider';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const loading = ref(false);
const errorMessage = ref('');
const showSso = ref(false);

const form = reactive({
    email: '',
    password: '',
    remember: false,
});

const errors = reactive({
    email: '',
    password: '',
});

function validate() {
    errors.email = '';
    errors.password = '';
    let valid = true;

    if (!form.email) {
        errors.email = 'Email is required';
        valid = false;
    } else if (!/\S+@\S+\.\S+/.test(form.email)) {
        errors.email = 'Please enter a valid email';
        valid = false;
    }

    if (!form.password) {
        errors.password = 'Password is required';
        valid = false;
    }

    return valid;
}

async function handleLogin() {
    if (!validate()) return;

    loading.value = true;
    errorMessage.value = '';

    try {
        await authStore.login(form.email, form.password, form.remember);
        const redirect = route.query.redirect || '/';
        router.push(redirect);
    } catch (err) {
        const status = err.response?.status;
        if (status === 401) {
            errorMessage.value = 'Invalid email or password.';
        } else if (status === 403) {
            errorMessage.value = err.response?.data?.message || 'Account access denied.';
        } else if (status === 422) {
            const validationErrors = err.response?.data?.errors || {};
            if (validationErrors.email) errors.email = validationErrors.email[0];
            if (validationErrors.password) errors.password = validationErrors.password[0];
        } else {
            errorMessage.value = 'Unable to sign in. Please try again.';
        }
    } finally {
        loading.value = false;
    }
}

function handleSso() {
    window.location.href = '/auth/sso';
}
</script>
