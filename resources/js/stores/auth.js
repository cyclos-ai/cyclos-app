import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/plugins/api';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(null);
    const initialized = ref(false);

    const isAuthenticated = computed(() => !!token.value && !!user.value);
    const fullName = computed(() => user.value ? `${user.value.first_name} ${user.value.last_name}` : '');
    const role = computed(() => user.value?.role || null);
    const organizationUuid = computed(() => user.value?.organization_uuid || null);
    const isAdmin = computed(() => role.value === 'super_admin');
    const isShipper = computed(() => ['shipper_admin', 'shipper_user', 'shipper_viewer'].includes(role.value));
    const isCarrier = computed(() => ['drayage_admin', 'drayage_dispatcher', 'drayage_driver'].includes(role.value));
    const approvalStatus = computed(() => user.value?.approval_status || null);

    async function initializeAuth() {
        const storedToken = localStorage.getItem('auth_token');
        const storedUser = localStorage.getItem('auth_user');

        if (storedToken && storedUser) {
            token.value = storedToken;
            try {
                user.value = JSON.parse(storedUser);
            } catch {
                user.value = null;
            }
        }
        initialized.value = true;
    }

    async function login(email, password, remember = false) {
        const response = await api.post('/auth/login', { email, password, remember });
        const { access_token, user: userData } = response.data;

        token.value = access_token;
        user.value = userData;

        localStorage.setItem('auth_token', access_token);
        localStorage.setItem('auth_user', JSON.stringify(userData));

        return userData;
    }

    async function logout() {
        try {
            await api.post('/auth/logout');
        } catch {
            // ignore errors on logout
        } finally {
            token.value = null;
            user.value = null;
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
        }
    }

    async function fetchProfile() {
        const response = await api.get('/auth/me');
        user.value = response.data;
        localStorage.setItem('auth_user', JSON.stringify(response.data));
        return response.data;
    }

    async function updateProfile(data) {
        const response = await api.put('/auth/profile', data);
        user.value = response.data;
        localStorage.setItem('auth_user', JSON.stringify(response.data));
        return response.data;
    }

    async function changePassword(data) {
        const response = await api.put('/auth/password', data);
        return response.data;
    }

    async function forgotPassword(email) {
        const response = await api.post('/auth/forgot-password', { email });
        return response.data;
    }

    async function resetPassword(data) {
        const response = await api.post('/auth/reset-password', data);
        return response.data;
    }

    return {
        user,
        token,
        initialized,
        isAuthenticated,
        fullName,
        role,
        organizationUuid,
        isAdmin,
        isShipper,
        isCarrier,
        approvalStatus,
        initializeAuth,
        login,
        logout,
        fetchProfile,
        updateProfile,
        changePassword,
        forgotPassword,
        resetPassword,
    };
});
