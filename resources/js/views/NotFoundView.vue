<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
        <div class="text-center max-w-md">
            <div class="text-8xl font-black text-gray-200 mb-4 select-none">404</div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Page not found</h1>
            <p class="text-gray-500 mb-8">The page you're looking for doesn't exist or has been moved.</p>
            <div class="flex gap-3 justify-center">
                <Button label="Go Back" icon="pi pi-arrow-left" outlined @click="$router.back()" />
                <Button label="Dashboard" icon="pi pi-home" :to="{ name: 'dashboard' }" as="router-link" />
            </div>
        </div>
    </div>
</template>

<script setup>
import Button from 'primevue/button';
</script>
