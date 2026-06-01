/**
 * Role-aware navigation configuration for Cyclos.ai
 *
 * Role groups:
 *   'admin'   → super_admin
 *   'shipper' → shipper_admin, shipper_user, shipper_viewer
 *   'carrier' → drayage_admin, drayage_dispatcher, drayage_driver
 */

const ROLE_MAP = {
    admin: ['super_admin'],
    shipper: ['shipper_admin', 'shipper_user', 'shipper_viewer'],
    carrier: ['drayage_admin', 'drayage_dispatcher', 'drayage_driver'],
};

function matchesRole(userRole, allowedGroups) {
    if (!allowedGroups || allowedGroups.length === 0) return true;
    return allowedGroups.some(group => ROLE_MAP[group]?.includes(userRole));
}

export const navigationGroups = [
    {
        label: '',
        items: [
            { label: 'Dashboard', icon: 'pi-home', to: '/', name: 'dashboard', roles: ['shipper', 'admin'] },
            { label: 'Dashboard', icon: 'pi-home', to: '/carrier/dashboard', name: 'carrier-dashboard', roles: ['carrier'] },
        ],
    },
    {
        label: 'My Work',
        roles: ['carrier'],
        items: [
            { label: 'My Assignments', icon: 'pi-list', to: '/carrier/assignments', name: 'carrier-assignments' },
            { label: 'Dispatch Board', icon: 'pi-th-large', to: '/carrier/dispatch', name: 'carrier-dispatch' },
            { label: 'Drayage Execution', icon: 'pi-check-circle', to: '/carrier/drayage', name: 'carrier-drayage' },
            { label: 'Submit Invoice', icon: 'pi-receipt', to: '/carrier/invoices', name: 'carrier-invoices' },
        ],
    },
    {
        label: 'Drayage',
        roles: ['shipper', 'admin'],
        items: [
            { label: 'Overview', icon: 'pi-chart-line', to: '/scheduling/overview', name: 'scheduling-overview' },
            { label: 'Inbound', icon: 'pi-inbox', to: '/scheduling/inbound', name: 'scheduling-inbound' },
        ],
    },
    {
        label: 'Ocean Freight',
        roles: ['shipper', 'admin'],
        items: [
            { label: 'Containers', icon: 'pi-box', to: '/containers', name: 'containers' },
            { label: 'Vessels', icon: 'pi-send', to: '/vessels', name: 'vessels' },
            { label: 'MBLs', icon: 'pi-file', to: '/mbls', name: 'mbls' },
            { label: 'Bookings', icon: 'pi-bookmark', to: '/bookings', name: 'bookings' },
        ],
    },
    {
        label: 'Air & Tracking',
        roles: ['shipper', 'admin'],
        items: [
            { label: 'Air Shipments', icon: 'pi-send', to: '/air-shipments', name: 'air-shipments' },
            { label: 'Tracking Requests', icon: 'pi-map-marker', to: '/tracking-requests', name: 'tracking-requests' },
        ],
    },
    {
        label: 'Charges',
        roles: ['shipper', 'admin'],
        items: [
            { label: 'Demurrage', icon: 'pi-clock', to: '/demurrage', name: 'demurrage' },
            { label: 'Detention', icon: 'pi-calendar-times', to: '/detention', name: 'detention' },
        ],
    },
    {
        label: 'Finance',
        roles: ['shipper', 'admin'],
        items: [
            { label: 'Ocean Invoices', icon: 'pi-receipt', to: '/ocean-invoices', name: 'ocean-invoices' },
            { label: 'Drayage Invoices', icon: 'pi-receipt', to: '/drayage-invoices', name: 'drayage-invoices' },
            { label: 'Purchase Orders', icon: 'pi-shopping-cart', to: '/purchase-orders', name: 'purchase-orders' },
        ],
    },
    {
        label: 'Supply Chain',
        roles: ['shipper', 'admin'],
        items: [
            { label: 'SKUs', icon: 'pi-tag', to: '/skus', name: 'skus' },
            { label: 'Factories', icon: 'pi-building', to: '/factories', name: 'factories' },
            { label: 'Vendors', icon: 'pi-users', to: '/vendors', name: 'vendors' },
            { label: 'Distribution Centers', icon: 'pi-map', to: '/distribution-centers', name: 'distribution-centers' },
        ],
    },
    {
        label: 'Rail',
        roles: ['shipper', 'admin'],
        items: [
            { label: 'Rail Map',       icon: 'pi-map',  to: '/rail/map',       name: 'rail-map' },
            { label: 'Rail Shipments', icon: 'pi-list', to: '/rail/shipments', name: 'rail-shipments' },
        ],
    },
    {
        label: 'Visibility',
        roles: ['shipper', 'admin'],
        items: [
            { label: 'Map', icon: 'pi-map', to: '/map', name: 'map' },
            { label: 'Calendar', icon: 'pi-calendar', to: '/calendar', name: 'calendar' },
            { label: 'Reports', icon: 'pi-chart-bar', to: '/reports', name: 'reports' },
        ],
    },
    {
        label: 'Admin',
        items: [
            { label: 'CSV Upload', icon: 'pi-upload', to: '/uploads/csv', name: 'uploads-csv', roles: ['shipper', 'admin'] },
            { label: 'Onboarding', icon: 'pi-user-plus', to: '/admin/onboarding', name: 'admin-onboarding', roles: ['admin'] },
            { label: 'Carrier Management', icon: 'pi-truck', to: '/settings/carrier-management', name: 'settings-carrier-management', roles: ['shipper', 'admin'] },
            { label: 'Integrations', icon: 'pi-link', to: '/settings/carrier-integrations', name: 'settings-carrier-integrations', roles: ['shipper', 'admin'] },
            { label: 'Automations', icon: 'pi-bolt', to: '/settings/n8n', name: 'settings-n8n', roles: ['shipper', 'admin'] },
            { label: 'Settings', icon: 'pi-cog', to: '/settings', name: 'settings' },
        ],
    },
];

export function getNavigationForRole(userRole) {
    if (!userRole) return [];

    return navigationGroups
        .filter(group => matchesRole(userRole, group.roles))
        .map(group => ({
            ...group,
            items: group.items.filter(item => matchesRole(userRole, item.roles)),
        }))
        .filter(group => group.items.length > 0);
}
