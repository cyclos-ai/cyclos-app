<template>
    <div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden"
         style="background: linear-gradient(145deg, #0d524a 0%, #161e1e 55%, #293434 100%)">
        <!-- Subtle grid pattern overlay -->
        <div class="absolute inset-0 opacity-[0.04]"
             style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 32px 32px;">
        </div>

        <div class="w-full max-w-md relative z-10">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-primary-500 flex items-center justify-center shadow-lg shadow-primary-500/25">
                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-semibold text-white tracking-tight">Cyclos<span class="text-primary-400">.ai</span></span>
                </div>
                <p class="text-sm text-surface-400">Container Lifecycle Management</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-2xl shadow-black/10 p-8 animate-scale-in">
                <slot />
            </div>

            <!-- Footer -->
            <p class="text-center text-xs text-surface-500 mt-6">
                &copy; {{ new Date().getFullYear() }} Cyclos.ai. All rights reserved.
            </p>
        </div>
    </div>
</template>
