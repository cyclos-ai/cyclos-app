import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const ROLE_MAP = {
    admin: ['super_admin'],
    shipper: ['shipper_admin', 'shipper_user', 'shipper_viewer'],
    carrier: ['drayage_admin', 'drayage_dispatcher', 'drayage_driver'],
};

const routes = [
    // Auth routes
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/auth/LoginView.vue'),
        meta: { requiresGuest: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('@/views/auth/RegisterView.vue'),
        meta: { requiresGuest: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('@/views/auth/ForgotPasswordView.vue'),
        meta: { requiresGuest: true },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('@/views/auth/ResetPasswordView.vue'),
        meta: { requiresGuest: true },
    },

    // Public carrier onboarding (no auth required)
    {
        path: '/carrier/onboard/:tenantSlug/:token',
        name: 'carrier-onboard',
        component: () => import('@/views/carrier-onboard/CarrierOnboardView.vue'),
        meta: { requiresGuest: false },
        props: true,
    },

    // App routes (require auth)
    {
        path: '/',
        component: () => import('@/layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            // Dashboard
            {
                path: '',
                name: 'dashboard',
                component: () => import('@/views/dashboard/DashboardView.vue'),
                beforeEnter: (to, from, next) => {
                    const authStore = useAuthStore();
                    if (authStore.isCarrier) {
                        next({ name: 'carrier-dashboard', replace: true });
                    } else {
                        next();
                    }
                },
            },

            // Containers
            {
                path: 'containers',
                name: 'containers',
                component: () => import('@/views/containers/ContainerListView.vue'),
            },
            {
                path: 'containers/not-tracking',
                name: 'containers-not-tracking',
                component: () => import('@/views/containers/ContainerListView.vue'),
                props: { defaultFilter: 'not_tracking' },
            },
            {
                path: 'containers/active',
                name: 'containers-active',
                component: () => import('@/views/containers/ContainerListView.vue'),
                props: { defaultFilter: 'active' },
            },
            {
                path: 'containers/:uuid',
                name: 'container-detail',
                component: () => import('@/views/containers/ContainerDetailView.vue'),
            },

            // Vessels
            {
                path: 'vessels',
                name: 'vessels',
                component: () => import('@/views/vessels/VesselListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'vessels/:uuid',
                name: 'vessel-detail',
                component: () => import('@/views/vessels/VesselDetailView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'vessels/:uuid/schedule',
                name: 'vessel-schedule',
                component: () => import('@/views/vessels/VesselDetailView.vue'),
                props: { defaultTab: 'schedule' },
                meta: { roles: ['shipper', 'admin'] },
            },

            // MBLs
            {
                path: 'mbls',
                name: 'mbls',
                component: () => import('@/views/mbls/MBLListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'mbls/:uuid',
                name: 'mbl-detail',
                component: () => import('@/views/mbls/MBLDetailView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Bookings
            {
                path: 'bookings',
                name: 'bookings',
                component: () => import('@/views/bookings/BookingListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'bookings/:uuid',
                name: 'booking-detail',
                component: () => import('@/views/bookings/BookingDetailView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Air Shipments
            {
                path: 'air-shipments',
                name: 'air-shipments',
                component: () => import('@/views/air/AirShipmentListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'air-shipments/:uuid',
                name: 'air-shipment-detail',
                component: () => import('@/views/air/AirShipmentDetailView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Tracking
            {
                path: 'track',
                name: 'track-container',
                component: () => import('@/views/tracking/TrackContainerView.vue'),
                meta: { requiresAuth: true },
            },
            {
                path: 'tracking-requests',
                name: 'tracking-requests',
                component: () => import('@/views/tracking/TrackingRequestView.vue'),
            },
            {
                path: 'tracking-requests/new',
                name: 'tracking-requests-new',
                component: () => import('@/views/tracking/TrackingRequestView.vue'),
                props: { showForm: true },
            },

            // Document Upload (OCR / PDF extraction)
            {
                path: 'documents/upload',
                name: 'documents-upload',
                component: () => import('@/views/documents/DocumentUploadView.vue'),
                meta: { requiresAuth: true },
            },

            // Demurrage
            {
                path: 'demurrage',
                name: 'demurrage',
                component: () => import('@/views/demurrage/DemurrageView.vue'),
            },
            {
                path: 'demurrage/alarms',
                name: 'demurrage-alarms',
                component: () => import('@/views/demurrage/DemurrageView.vue'),
                props: { defaultTab: 'alarms' },
            },
            {
                path: 'demurrage/calculator',
                name: 'demurrage-calculator',
                component: () => import('@/views/demurrage/DemurrageView.vue'),
                props: { defaultTab: 'calculator' },
            },

            // Detention
            {
                path: 'detention',
                name: 'detention',
                component: () => import('@/views/demurrage/DetentionView.vue'),
            },
            {
                path: 'detention/alarms',
                name: 'detention-alarms',
                component: () => import('@/views/demurrage/DetentionView.vue'),
                props: { defaultTab: 'alarms' },
            },

            // Invoices
            {
                path: 'ocean-invoices',
                name: 'ocean-invoices',
                component: () => import('@/views/invoices/OceanInvoiceListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'ocean-invoices/:uuid',
                name: 'ocean-invoice-detail',
                component: () => import('@/views/invoices/OceanInvoiceDetailView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'drayage-invoices',
                name: 'drayage-invoices',
                component: () => import('@/views/invoices/DrayageInvoiceListView.vue'),
            },
            {
                path: 'drayage-invoices/:uuid',
                name: 'drayage-invoice-detail',
                component: () => import('@/views/invoices/DrayageInvoiceDetailView.vue'),
            },

            // Purchase Orders
            {
                path: 'purchase-orders',
                name: 'purchase-orders',
                component: () => import('@/views/purchaseorders/PurchaseOrderListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'purchase-orders/:uuid',
                name: 'purchase-order-detail',
                component: () => import('@/views/purchaseorders/PurchaseOrderDetailView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // SKUs
            {
                path: 'skus',
                name: 'skus',
                component: () => import('@/views/skus/SKUListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Factories
            {
                path: 'factories',
                name: 'factories',
                component: () => import('@/views/factories/FactoryListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Vendors
            {
                path: 'vendors',
                name: 'vendors',
                component: () => import('@/views/vendors/VendorListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Distribution Centers
            {
                path: 'distribution-centers',
                name: 'distribution-centers',
                component: () => import('@/views/distribution/DistributionCenterListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Map
            {
                path: 'map',
                name: 'map',
                component: () => import('@/views/map/MapView.vue'),
            },

            // Rail
            {
                path: 'rail/map',
                name: 'rail-map',
                component: () => import('@/views/rail/RailMapView.vue'),
                meta: { requiresAuth: true },
            },
            {
                path: 'rail/shipments',
                name: 'rail-shipments',
                component: () => import('@/views/rail/RailShipmentListView.vue'),
                meta: { requiresAuth: true },
            },

            // Calendar
            {
                path: 'calendar',
                name: 'calendar',
                component: () => import('@/views/calendar/CalendarView.vue'),
            },

            // Reports
            {
                path: 'reports',
                name: 'reports',
                component: () => import('@/views/reports/ReportListView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'reports/builder',
                name: 'report-builder',
                component: () => import('@/views/reports/ReportBuilderView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'reports/:uuid',
                name: 'report-detail',
                component: () => import('@/views/reports/ReportDetailView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Scheduling (Drayage Overview)
            {
                path: 'scheduling/overview',
                name: 'scheduling-overview',
                component: () => import('@/views/scheduling/SchedulingOverviewView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'scheduling/inbound',
                name: 'scheduling-inbound',
                component: () => import('@/views/scheduling/InboundDrayVisibilityView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Drayage
            {
                path: 'drayage/:uuid',
                name: 'drayage-detail',
                component: () => import('@/views/drayage/DrayageStepView.vue'),
            },
            {
                path: 'scheduled-drops',
                name: 'scheduled-drops',
                component: () => import('@/views/drayage/ScheduledDropsView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Uploads
            {
                path: 'uploads/csv',
                name: 'uploads-csv',
                component: () => import('@/views/uploads/CSVUploadView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Settings
            {
                path: 'settings',
                name: 'settings',
                redirect: { name: 'settings-organization' },
            },
            {
                path: 'settings/organization',
                name: 'settings-organization',
                component: () => import('@/views/settings/OrganizationSettingsView.vue'),
            },
            {
                path: 'settings/users',
                name: 'settings-users',
                component: () => import('@/views/settings/UserManagementView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'settings/sso',
                name: 'settings-sso',
                component: () => import('@/views/settings/SSOSettingsView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'settings/custom-columns',
                name: 'settings-custom-columns',
                component: () => import('@/views/settings/CustomColumnsView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'settings/webhooks',
                name: 'settings-webhooks',
                component: () => import('@/views/settings/WebhookSettingsView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'settings/carrier-contracts',
                name: 'settings-carrier-contracts',
                component: () => import('@/views/settings/CarrierContractSettingsView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'settings/carrier-integrations',
                name: 'settings-carrier-integrations',
                component: () => import('@/views/settings/CarrierIntegrationsView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'settings/n8n',
                name: 'settings-n8n',
                component: () => import('@/views/settings/N8nIntegrationView.vue'),
                meta: { title: 'Workflow Automation', roles: ['shipper', 'admin'] },
            },
            {
                path: 'settings/billing',
                name: 'settings-billing',
                component: () => import('@/views/settings/BillingSettingsView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },
            {
                path: 'settings/carrier-management',
                name: 'settings-carrier-management',
                component: () => import('@/views/settings/CarrierManagementView.vue'),
                meta: { roles: ['shipper', 'admin'] },
            },

            // Carrier routes
            {
                path: 'carrier/dashboard',
                name: 'carrier-dashboard',
                component: () => import('@/views/carrier/CarrierDashboardView.vue'),
                meta: { roles: ['carrier', 'admin'] },
            },
            {
                path: 'carrier/assignments',
                name: 'carrier-assignments',
                component: () => import('@/views/carrier/CarrierAssignmentsView.vue'),
                meta: { roles: ['carrier', 'admin'] },
            },
            {
                path: 'carrier/dispatch',
                name: 'carrier-dispatch',
                component: () => import('@/views/carrier/DispatchBoardView.vue'),
                meta: { roles: ['carrier', 'admin'] },
            },
            {
                path: 'carrier/drayage',
                name: 'carrier-drayage',
                component: () => import('@/views/carrier/DrayageExecutionView.vue'),
                meta: { roles: ['carrier', 'admin'] },
            },
            {
                path: 'carrier/drayage/:uuid',
                name: 'carrier-drayage-detail',
                component: () => import('@/views/carrier/DrayageExecutionDetailView.vue'),
                meta: { roles: ['carrier', 'admin'] },
            },
            {
                path: 'carrier/invoices',
                name: 'carrier-invoices',
                component: () => import('@/views/carrier/CarrierInvoiceListView.vue'),
                meta: { roles: ['carrier', 'admin'] },
            },
            {
                path: 'carrier/invoices/new',
                name: 'carrier-invoice-new',
                component: () => import('@/views/carrier/CarrierInvoiceFormView.vue'),
                meta: { roles: ['carrier', 'admin'] },
            },

            // Admin routes
            {
                path: 'admin/onboarding',
                name: 'admin-onboarding',
                component: () => import('@/views/admin/OnboardingView.vue'),
                meta: { roles: ['admin'] },
            },
        ],
    },

    // 404
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/views/NotFoundView.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        }
        return { top: 0 };
    },
});

// Navigation guards
router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    if (!authStore.initialized) {
        await authStore.initializeAuth();
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        next({ name: 'login', query: { redirect: to.fullPath } });
        return;
    }

    if (to.meta.requiresGuest && authStore.isAuthenticated) {
        next({ name: 'dashboard' });
        return;
    }

    // Role-based guard
    if (to.meta.roles && authStore.isAuthenticated) {
        const userRole = authStore.role;
        const allowed = to.meta.roles.some(group => ROLE_MAP[group]?.includes(userRole));
        if (!allowed) {
            if (['drayage_admin', 'drayage_dispatcher', 'drayage_driver'].includes(userRole)) {
                next({ name: 'carrier-dashboard', replace: true });
            } else {
                next({ name: 'dashboard', replace: true });
            }
            return;
        }
    }

    next();
});

export default router;
