<template>
    <div>
        <PageHeader title="QuickBooks Online" subtitle="Sync invoices and payments with QuickBooks Online" />

        <!-- Connection card -->
        <div class="bg-white rounded-xl border border-surface-200 p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                         :class="store.isConnected ? 'bg-[#2CA01C]/10' : 'bg-surface-100'">
                        <!-- QuickBooks / dollar icon -->
                        <svg class="w-6 h-6" :class="store.isConnected ? 'text-[#2CA01C]' : 'text-surface-400'"
                             viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm.5 14.5v1h-1v-1c-1.38-.25-2.5-1.24-2.5-2.5h1.5c0 .69.67 1 1 1s1-.31 1-1c0-.73-.69-1-1-1-1.66 0-3-1.01-3-2.5 0-1.26 1.12-2.25 2.5-2.5V7h1v1c1.38.25 2.5 1.24 2.5 2.5h-1.5c0-.69-.67-1-1-1s-1 .31-1 1c0 .73.69 1 1 1 1.66 0 3 1.01 3 2.5 0 1.26-1.12 2.25-2.5 2.5z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-surface-900 tracking-tight">QuickBooks Online</h3>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="w-2 h-2 rounded-full"
                                  :class="store.isConnected ? 'bg-emerald-500' : 'bg-surface-300'"></span>
                            <span class="text-sm" :class="store.isConnected ? 'text-emerald-600' : 'text-surface-500'">
                                {{ store.isConnected ? 'Connected' : 'Not connected' }}
                            </span>
                            <span v-if="store.status?.company_name" class="text-xs text-surface-400 ml-2">
                                {{ store.status.company_name }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <template v-if="store.isConnected">
                        <Button label="Disconnect" icon="pi pi-times" size="small" outlined severity="danger"
                                @click="handleDisconnect" />
                    </template>
                    <Button v-else-if="store.isConfigured"
                            label="Connect QuickBooks" icon="pi pi-link" size="small"
                            :loading="store.connecting"
                            style="background-color: #2CA01C; border-color: #2CA01C;"
                            @click="store.connect()" />
                </div>
            </div>

            <!-- Not configured notice -->
            <div v-if="!store.isConfigured && !store.loading"
                 class="mt-5 pt-5 border-t border-surface-100">
                <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <i class="pi pi-exclamation-triangle text-amber-500 mt-0.5"></i>
                    <div class="text-sm text-amber-800">
                        <p class="font-semibold mb-1">Server configuration required</p>
                        <p>Add <code class="bg-amber-100 px-1 rounded text-xs font-mono">QUICKBOOKS_CLIENT_ID</code> and
                           <code class="bg-amber-100 px-1 rounded text-xs font-mono">QUICKBOOKS_CLIENT_SECRET</code> to
                           your server environment, then register the redirect URI in your Intuit app:</p>
                        <p class="mt-1 font-mono text-xs bg-amber-100 rounded px-2 py-1 inline-block">
                            https://demo.cyclos.ai/quickbooks/callback
                        </p>
                    </div>
                </div>
            </div>

            <!-- Connected details -->
            <div v-if="store.isConnected" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5 pt-5 border-t border-surface-100">
                <div>
                    <p class="text-xs text-surface-500 uppercase tracking-wider">Company</p>
                    <p class="text-sm font-semibold text-surface-900 tracking-tight mt-0.5">
                        {{ store.status?.company_name || '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-surface-500 uppercase tracking-wider">Environment</p>
                    <Tag value="Production" severity="success" class="mt-1" />
                </div>
                <div>
                    <p class="text-xs text-surface-500 uppercase tracking-wider">Realm ID</p>
                    <p class="text-sm font-mono text-surface-700 mt-0.5">
                        {{ maskedRealmId }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-surface-500 uppercase tracking-wider">Last Sync</p>
                    <p class="text-sm text-surface-700 mt-0.5">
                        {{ store.status?.last_sync_at ? formatDate(store.status.last_sync_at) : 'Never' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-surface-500 uppercase tracking-wider">Token Expires</p>
                    <p class="text-sm mt-0.5"
                       :class="isTokenExpiringSoon ? 'text-amber-600 font-semibold' : 'text-surface-700'">
                        {{ store.status?.token_expires_at ? formatDate(store.status.token_expires_at) : '—' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Loading state -->
        <div v-if="store.loading" class="flex justify-center py-20">
            <ProgressSpinner />
        </div>

        <!-- Not connected empty state -->
        <div v-if="!store.isConnected && !store.loading && store.isConfigured"
             class="bg-white rounded-xl border border-dashed border-surface-300 p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-[#2CA01C]/10 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-[#2CA01C]" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm.5 14.5v1h-1v-1c-1.38-.25-2.5-1.24-2.5-2.5h1.5c0 .69.67 1 1 1s1-.31 1-1c0-.73-.69-1-1-1-1.66 0-3-1.01-3-2.5 0-1.26 1.12-2.25 2.5-2.5V7h1v1c1.38.25 2.5 1.24 2.5 2.5h-1.5c0-.69-.67-1-1-1s-1 .31-1 1c0 .73.69 1 1 1 1.66 0 3 1.01 3 2.5 0 1.26-1.12 2.25-2.5 2.5z"/>
                </svg>
            </div>
            <h3 class="text-surface-700 font-semibold mb-1">Connect QuickBooks Online</h3>
            <p class="text-sm text-surface-500 mb-4 max-w-md mx-auto">
                Push invoices directly to QuickBooks and sync payment status back into Cyclos.
            </p>
            <Button label="Connect QuickBooks" icon="pi pi-link"
                    :loading="store.connecting"
                    style="background-color: #2CA01C; border-color: #2CA01C;"
                    @click="store.connect()" />
        </div>

        <ConfirmDialog />
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import ProgressSpinner from 'primevue/progressspinner';
import ConfirmDialog from 'primevue/confirmdialog';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import PageHeader from '@/components/PageHeader.vue';
import { useQuickBooksStore } from '@/stores/quickbooks';
import dayjs from 'dayjs';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const store = useQuickBooksStore();

const maskedRealmId = computed(() => {
    const id = store.status?.realm_id;
    if (!id) return '—';
    const s = String(id);
    return s.length > 6 ? `${'*'.repeat(s.length - 4)}${s.slice(-4)}` : '****';
});

const isTokenExpiringSoon = computed(() => {
    const exp = store.status?.token_expires_at;
    if (!exp) return false;
    return dayjs(exp).diff(dayjs(), 'day') <= 7;
});

function formatDate(d) {
    return d ? dayjs(d).format('MMM D, YYYY h:mm A') : '';
}

function handleDisconnect() {
    confirm.require({
        message: 'Disconnect from QuickBooks? Invoice sync will stop.',
        header: 'Confirm Disconnect',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await store.disconnect();
                toast.add({ severity: 'info', summary: 'Disconnected', detail: 'QuickBooks has been disconnected', life: 3000 });
            } catch {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to disconnect', life: 5000 });
            }
        },
    });
}

onMounted(async () => {
    const qb = route.query.qb;
    const qbError = route.query.qb_error;

    if (qb === 'connected') {
        toast.add({ severity: 'success', summary: 'Connected', detail: 'QuickBooks connected successfully', life: 4000 });
    } else if (qbError) {
        toast.add({ severity: 'error', summary: 'Connection Failed', detail: decodeURIComponent(qbError), life: 6000 });
    }

    // Clean query params without adding a history entry
    if (qb || qbError) {
        router.replace({ query: {} });
    }

    await store.fetchStatus();
});
</script>
