<template>
    <div>
        <PageHeader title="Billing" subtitle="Manage your subscription and billing details" />

        <!-- Route query toast handler (success / cancelled) -->
        <Toast />

        <div class="max-w-5xl space-y-8">

            <!-- Loading skeleton -->
            <template v-if="billingStore.loading">
                <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-4 animate-pulse">
                    <div class="h-4 bg-gray-200 rounded w-1/4"></div>
                    <div class="h-8 bg-gray-200 rounded w-1/3"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-4 animate-pulse">
                    <div class="h-4 bg-gray-200 rounded w-1/4"></div>
                    <div class="h-6 bg-gray-200 rounded w-full"></div>
                    <div class="h-6 bg-gray-200 rounded w-full"></div>
                </div>
            </template>

            <template v-else>

                <!-- Current plan card -->
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Current Plan</h3>

                    <!-- No active plan -->
                    <template v-if="!currentPlan">
                        <p class="text-gray-500 text-sm">You are not on a paid plan.</p>
                        <Button
                            label="View Plans"
                            icon="pi pi-arrow-down"
                            outlined
                            size="small"
                            class="mt-4"
                            @click="scrollToPlans"
                        />
                    </template>

                    <template v-else>
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="text-xl font-bold text-gray-900">{{ currentPlan.name }}</p>
                                    <Tag
                                        :value="subscriptionStatusLabel"
                                        :severity="subscriptionStatusSeverity"
                                        class="text-xs"
                                    />
                                </div>
                                <p class="text-sm text-gray-500 mt-1 capitalize">
                                    Billed {{ current.subscription?.billing_cycle ?? 'monthly' }}
                                </p>
                                <p v-if="renewalDate" class="text-xs text-gray-400 mt-1">
                                    {{ current.subscription?.canceled_at ? 'Cancels' : 'Renews' }}
                                    {{ renewalDate }}
                                </p>
                                <!-- Trial notice -->
                                <p v-if="trialActive" class="text-xs text-amber-600 font-medium mt-1">
                                    Trial ends {{ trialEndsAt }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-blue-600">
                                    ${{ billingCycle === 'yearly' ? currentPlan.price_yearly : currentPlan.price_monthly }}
                                    <span class="text-sm font-normal text-gray-500">/{{ billingCycle === 'yearly' ? 'yr' : 'mo' }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap gap-3">
                            <Button
                                v-if="current.subscription"
                                label="Manage billing"
                                icon="pi pi-external-link"
                                outlined
                                size="small"
                                :loading="portalLoading"
                                @click="handlePortal"
                            />
                            <Button
                                label="Change plan"
                                icon="pi pi-arrow-down"
                                text
                                size="small"
                                @click="scrollToPlans"
                            />
                        </div>
                    </template>
                </div>

                <!-- Usage meters -->
                <div v-if="current" class="bg-white border border-gray-200 rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-5">Usage this period</h3>

                    <div class="space-y-5">
                        <!-- AI tokens -->
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 font-medium">AI Tokens</span>
                                <span :class="aiTokensOverage ? 'text-red-600 font-semibold' : 'text-gray-500'">
                                    {{ formatNumber(current.usage?.ai_tokens ?? 0) }}
                                    <span class="text-gray-400"> / {{ formatNumber(currentPlan?.included_ai_tokens ?? 0) }}</span>
                                </span>
                            </div>
                            <ProgressBar
                                :value="aiTokensPercent"
                                :class="aiTokensOverage ? 'overage-bar' : ''"
                                :pt="{ value: { class: aiTokensOverage ? 'bg-red-500' : aiTokensWarning ? 'bg-amber-500' : 'bg-blue-500' } }"
                                show-value="false"
                                style="height: 8px"
                            />
                            <p v-if="aiTokensOverage" class="text-xs text-red-500 mt-1">Over included limit</p>
                            <p v-else-if="aiTokensWarning" class="text-xs text-amber-500 mt-1">Approaching limit</p>
                        </div>

                        <!-- API calls (external) -->
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 font-medium">External API Calls</span>
                                <span :class="apiCallsOverage ? 'text-red-600 font-semibold' : 'text-gray-500'">
                                    {{ formatNumber(current.usage?.api_calls_external ?? 0) }}
                                    <span class="text-gray-400"> / {{ formatNumber(currentPlan?.included_api_calls ?? 0) }}</span>
                                </span>
                            </div>
                            <ProgressBar
                                :value="apiCallsPercent"
                                :pt="{ value: { class: apiCallsOverage ? 'bg-red-500' : apiCallsWarning ? 'bg-amber-500' : 'bg-blue-500' } }"
                                show-value="false"
                                style="height: 8px"
                            />
                            <p v-if="apiCallsOverage" class="text-xs text-red-500 mt-1">Over included limit</p>
                            <p v-else-if="apiCallsWarning" class="text-xs text-amber-500 mt-1">Approaching limit</p>
                        </div>

                        <!-- Total API calls analytics stat -->
                        <div class="pt-2 border-t border-gray-100 flex items-center gap-2">
                            <i class="pi pi-chart-bar text-gray-400 text-sm"></i>
                            <span class="text-sm text-gray-500">
                                Total API calls this period:
                                <span class="font-semibold text-gray-800">{{ formatNumber(current.usage?.api_calls_total ?? 0) }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Plans grid -->
                <div ref="plansSection">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-700">Available Plans</h3>
                        <!-- Billing cycle toggle -->
                        <div class="flex items-center gap-2 text-sm">
                            <span :class="billingCycle === 'monthly' ? 'text-gray-900 font-medium' : 'text-gray-400'">Monthly</span>
                            <ToggleSwitch v-model="yearlyToggle" />
                            <span :class="billingCycle === 'yearly' ? 'text-gray-900 font-medium' : 'text-gray-400'">
                                Yearly <span class="text-green-600 text-xs font-medium">Save ~17%</span>
                            </span>
                        </div>
                    </div>

                    <div v-if="billingStore.plans.length === 0" class="text-sm text-gray-400 py-6 text-center">
                        No plans available at this time.
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="plan in billingStore.plans"
                            :key="plan.id"
                            class="bg-white border rounded-xl p-5 flex flex-col gap-4 transition-shadow hover:shadow-md"
                            :class="isCurrentPlan(plan) ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-200'"
                        >
                            <div>
                                <p class="font-bold text-gray-900 text-base">{{ plan.name }}</p>
                                <p class="text-2xl font-bold text-blue-600 mt-2">
                                    ${{ billingCycle === 'yearly' ? plan.price_yearly : plan.price_monthly }}
                                    <span class="text-sm font-normal text-gray-500">/{{ billingCycle === 'yearly' ? 'yr' : 'mo' }}</span>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ formatNumber(plan.included_ai_tokens) }} AI tokens &bull;
                                    {{ formatNumber(plan.included_api_calls) }} API calls
                                </p>
                            </div>

                            <ul v-if="plan.features?.length" class="space-y-1 flex-1">
                                <li
                                    v-for="feature in plan.features"
                                    :key="feature"
                                    class="flex items-start gap-2 text-sm text-gray-600"
                                >
                                    <i class="pi pi-check text-green-500 text-xs mt-0.5 flex-shrink-0"></i>
                                    {{ feature }}
                                </li>
                            </ul>

                            <div v-if="isCurrentPlan(plan)">
                                <Tag value="Current plan" severity="info" class="w-full justify-center text-xs" />
                            </div>
                            <div v-else>
                                <Button
                                    :label="upgradingPlanId === plan.id ? '' : planButtonLabel(plan)"
                                    :loading="upgradingPlanId === plan.id"
                                    size="small"
                                    class="w-full"
                                    @click="handleCheckout(plan)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import dayjs from 'dayjs';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import ProgressBar from 'primevue/progressbar';
import ToggleSwitch from 'primevue/toggleswitch';
import Toast from 'primevue/toast';
import PageHeader from '@/components/PageHeader.vue';
import { useBillingStore } from '@/stores/billing';

const billingStore = useBillingStore();
const route = useRoute();
const router = useRouter();
const toast = useToast();

const yearlyToggle = ref(false);
const billingCycle = computed(() => (yearlyToggle.value ? 'yearly' : 'monthly'));
const plansSection = ref(null);
const portalLoading = ref(false);
const upgradingPlanId = ref(null);

// Derived current data
const current = computed(() => billingStore.current);
const currentPlan = computed(() => current.value?.plan ?? null);

const subscriptionStatusLabel = computed(() => {
    const status = current.value?.subscription?.status;
    if (!status) return 'No subscription';
    return status.charAt(0).toUpperCase() + status.slice(1);
});

const subscriptionStatusSeverity = computed(() => {
    const status = current.value?.subscription?.status;
    if (status === 'active') return 'success';
    if (status === 'trialing') return 'info';
    if (status === 'past_due') return 'danger';
    if (status === 'canceled') return 'secondary';
    return 'secondary';
});

const renewalDate = computed(() => {
    const end = current.value?.subscription?.current_period_end;
    return end ? dayjs(end).format('MMM D, YYYY') : null;
});

const trialActive = computed(() => {
    const t = current.value?.trial_ends_at;
    return t ? dayjs(t).isAfter(dayjs()) : false;
});

const trialEndsAt = computed(() => {
    const t = current.value?.trial_ends_at;
    return t ? dayjs(t).format('MMM D, YYYY') : null;
});

// Usage percentages (capped at 100 for progress bar, raw for overage detection)
const aiTokensRaw = computed(() => {
    const used = current.value?.usage?.ai_tokens ?? 0;
    const included = currentPlan.value?.included_ai_tokens ?? 0;
    return included > 0 ? (used / included) * 100 : 0;
});
const aiTokensPercent = computed(() => Math.min(aiTokensRaw.value, 100));
const aiTokensOverage = computed(() => aiTokensRaw.value > 100);
const aiTokensWarning = computed(() => !aiTokensOverage.value && aiTokensRaw.value >= 80);

const apiCallsRaw = computed(() => {
    const used = current.value?.usage?.api_calls_external ?? 0;
    const included = currentPlan.value?.included_api_calls ?? 0;
    return included > 0 ? (used / included) * 100 : 0;
});
const apiCallsPercent = computed(() => Math.min(apiCallsRaw.value, 100));
const apiCallsOverage = computed(() => apiCallsRaw.value > 100);
const apiCallsWarning = computed(() => !apiCallsOverage.value && apiCallsRaw.value >= 80);

// Helpers
function formatNumber(n) {
    if (n === null || n === undefined) return '0';
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000) return n.toLocaleString();
    return String(n);
}

function isCurrentPlan(plan) {
    return currentPlan.value?.id === plan.id;
}

function planButtonLabel(plan) {
    const currentPrice = billingCycle.value === 'yearly'
        ? currentPlan.value?.price_yearly ?? 0
        : currentPlan.value?.price_monthly ?? 0;
    const planPrice = billingCycle.value === 'yearly' ? plan.price_yearly : plan.price_monthly;
    return planPrice > currentPrice ? 'Upgrade' : 'Switch';
}

function scrollToPlans() {
    plansSection.value?.scrollIntoView({ behavior: 'smooth' });
}

async function handlePortal() {
    portalLoading.value = true;
    try {
        await billingStore.openPortal();
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not open billing portal.', life: 4000 });
    } finally {
        portalLoading.value = false;
    }
}

async function handleCheckout(plan) {
    upgradingPlanId.value = plan.id;
    try {
        await billingStore.checkout(plan.id, billingCycle.value);
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not start checkout. Please try again.', life: 4000 });
        upgradingPlanId.value = null;
    }
}

onMounted(async () => {
    // Handle redirect back from Stripe
    const billing = route.query.billing;
    if (billing === 'success') {
        toast.add({ severity: 'success', summary: 'Subscription updated', detail: 'Your plan has been activated.', life: 5000 });
    } else if (billing === 'cancelled') {
        toast.add({ severity: 'info', summary: 'Checkout cancelled', detail: 'No changes were made to your subscription.', life: 4000 });
    }
    if (billing) {
        const query = { ...route.query };
        delete query.billing;
        router.replace({ query });
    }

    await Promise.all([billingStore.fetchCurrent(), billingStore.fetchPlans()]);
});
</script>
