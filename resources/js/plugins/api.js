import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
    withCredentials: true,
});

// Request interceptor: inject auth token
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        if (import.meta.env.DEV) {
            console.debug(`[API] ${config.method?.toUpperCase()} ${config.url}`, config.params || config.data || '');
        }

        return config;
    },
    (error) => Promise.reject(error),
);

// Response interceptor: handle errors globally
api.interceptors.response.use(
    (response) => {
        if (import.meta.env.DEV) {
            console.debug(`[API] ${response.status} ${response.config.url}`, response.data);
        }
        return response;
    },
    (error) => {
        const status = error.response?.status;

        if (status === 401) {
            // Token expired or invalid - clear and redirect
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '/login';
            return Promise.reject(error);
        }

        if (status === 403) {
            // Show toast for forbidden
            const event = new CustomEvent('api:forbidden', {
                detail: { message: error.response?.data?.message || 'You do not have permission to perform this action.' },
            });
            window.dispatchEvent(event);
        }

        if (status === 422) {
            // Validation errors - let caller handle
            return Promise.reject(error);
        }

        if (status >= 500) {
            const event = new CustomEvent('api:server-error', {
                detail: { message: 'A server error occurred. Please try again.' },
            });
            window.dispatchEvent(event);
        }

        return Promise.reject(error);
    },
);

export default api;
