# Plan: Role-Based Views & Onboarding for Cyclos.ai

**Created:** 2026-05-23
**Scope:** Backend middleware, Vue router guards, dynamic sidebar, carrier views, registration/onboarding flows
**Estimated Complexity:** HIGH (5 steps, ~30 files to create/modify across frontend and backend)

---

## Context

Cyclos.ai is a Laravel 11 + Vue 3 + PrimeVue 4 multi-tenant SaaS app for container lifecycle management. It currently has:

- **UserRole enum** (`app/Domain/User/Enums/UserRole.php`) with 7 roles: `super_admin`, `shipper_admin`, `shipper_user`, `shipper_viewer`, `drayage_admin`, `drayage_dispatcher`, `drayage_driver`
- **Existing middleware:** `EnsureShipperRole` and `EnsureDrayageRole` (both check against UserRole enum and allow `super_admin` through)
- **Middleware aliases** in `bootstrap/app.php`: `tenant`, `tenant.scope`, `role`, `permission` (Spatie)
- **Auth store** (`resources/js/stores/auth.js`) already exposes a `role` computed property from `user.role`
- **Static sidebar navigation** in `AppLayout.vue` with hardcoded `navGroups` array (no role filtering)
- **Router** (`resources/js/router/index.js`) with `requiresAuth`/`requiresGuest` meta but no role-based guards
- **Users table** has `role` (string, defaults to 'user'), `is_active` (boolean), `email_verified_at` (nullable timestamp), `tenant_id` (FK)
- **Login** returns `role` in the user payload; no registration endpoint exists
- **UserManagement view** already has a working "Invite Member" dialog that calls `orgStore.inviteMember()`
- **API plugin** (`resources/js/plugins/api.js`) already handles 401 (redirect to login) and 403 (dispatches `api:forbidden` custom event)

### Terminology Mapping
The requirements use "admin/shipper/carrier" but the existing `UserRole` enum uses more granular terms:
- **Admin** = `super_admin`
- **Shipper** = `shipper_admin`, `shipper_user`, `shipper_viewer` (already grouped via `isShipper()`)
- **Carrier** = `drayage_admin`, `drayage_dispatcher`, `drayage_driver` (already grouped via `isDrayage()`)

This plan uses the existing enum values rather than introducing new roles.

---

## Work Objectives

1. Role-gated Vue router (carrier users cannot navigate to shipper pages and vice versa)
2. Dynamic sidebar that shows only role-appropriate navigation items
3. Carrier-specific views (Dashboard, Assignments, Dispatch Board, Drayage Execution, Invoice Submission)
4. Backend API middleware to protect endpoints by role group
5. Self-registration flow with admin approval + admin invite flow enhancements

---

## Guardrails

### Must Have
- Use existing `UserRole` enum and its `isShipper()`/`isDrayage()` helpers -- do NOT create new role values
- Role-based route guards on BOTH frontend (Vue router) and backend (Laravel middleware)
- Carrier users must ONLY see containers/loads assigned to them (scoped queries)
- Self-registered users default to `is_active = false` until admin approves
- All new Vue views use existing patterns: PrimeVue 4 components, `PageHeader`, `useToast`, `useConfirm`, Tailwind utility classes, Pinia stores

### Must NOT Have
- No Spatie permission granularity yet -- stick with role-based checks only
- No email sending implementation (stub the notification; just create accounts with temp passwords or mark pending)
- No changes to the existing tenant/multi-tenant architecture
- No breaking changes to existing shipper views or API responses

---

## Task Flow

```
Step 1 (Backend Foundation) --> Step 2 (Frontend Role Infrastructure) --> Step 3 (Carrier Views) --> Step 4 (Onboarding Flows) --> Step 5 (Wiring & Polish)
```

Steps 1 and 2 can be partially parallelized (backend vs frontend), but Step 3 depends on both. Step 4 is independent of Step 3. Step 5 ties everything together.

---

## Step 1: Backend Role Middleware & Registration API

### 1.1 Create `EnsureRole` generic middleware
**File:** `app/Http/Middleware/EnsureRole.php`

Create a single parameterized middleware that replaces the need for individual `EnsureShipperRole`/`EnsureDrayageRole` middleware. It accepts role group names as parameters.

```
Pattern: $request->user()->role checked against UserRole enum
Usage in routes: middleware('ensure.role:shipper') or middleware('ensure.role:drayage') or middleware('ensure.role:admin')
super_admin always passes through
```

Register alias in `bootstrap/app.php`:
```php
'ensure.role' => \App\Http\Middleware\EnsureRole::class,
```

### 1.2 Add `approval_status` column to users table
**File:** `database/migrations/central/YYYY_MM_DD_HHMMSS_add_approval_status_to_users_table.php`

```
$table->string('approval_status')->default('approved'); // 'pending', 'approved', 'rejected'
$table->text('rejection_reason')->nullable();
$table->uuid('approved_by')->nullable();
$table->timestamp('approved_at')->nullable();
```

Existing users get `approved` by default. New self-registered users get `pending`.

### 1.3 Add `company_name` to users table (for self-registration context)
Same migration or separate:
```
$table->string('company_name')->nullable();
```

### 1.4 Create Registration Controller
**File:** `app/Http/Controllers/Api/V1/Auth/RegistrationController.php`

Endpoints:
- `POST /api/v1/auth/register` -- public, creates user with `is_active = false`, `approval_status = 'pending'`
  - Validates: `first_name`, `last_name`, `email`, `password`, `password_confirmation`, `role` (must be one of `shipper_user`, `drayage_dispatcher`), `company_name`
  - Returns 201 with message about pending approval

### 1.5 Create Admin Onboarding Controller
**File:** `app/Http/Controllers/Api/V1/Admin/OnboardingController.php`

Endpoints (all require `super_admin` role):
- `GET /api/v1/admin/registrations` -- list pending registrations (paginated)
- `GET /api/v1/admin/registrations/{uuid}` -- show single registration
- `POST /api/v1/admin/registrations/{uuid}/approve` -- set `approval_status = 'approved'`, `is_active = true`
- `POST /api/v1/admin/registrations/{uuid}/reject` -- set `approval_status = 'rejected'`, accept optional `rejection_reason`
- `POST /api/v1/admin/invite` -- admin creates user directly (name, email, role, company_name, generates temp password, sets `is_active = true`, `approval_status = 'approved'`)

### 1.6 Update AuthController login
**File:** `app/Http/Controllers/Api/V1/Auth/AuthController.php`

Add check after `is_active` check:
- If `approval_status === 'pending'`, return 403 with message "Your registration is pending admin approval."
- If `approval_status === 'rejected'`, return 403 with message "Your registration has been declined."
- Include `approval_status` in the user payload returned by `login()` and `me()`

### 1.7 Apply role middleware to tenant routes
**File:** `routes/tenant.php`

Wrap shipper-only routes in `middleware('ensure.role:shipper')` group:
- Vessels, MBLs, Bookings, Air Shipments, Purchase Orders, SKUs, Factories, Vendors, Distribution Centers, Ocean Invoices, Reports, CSV Uploads, Custom Columns, Volume, Transit Times

Wrap drayage-only routes in `middleware('ensure.role:drayage')` group:
- Drayage Steps (already exists as a section)

Leave shared routes (Containers, Dashboard, Demurrage, Detention, Map, Calendar, Tracking, Drayage Invoices, Organizations, Webhooks, Carrier Contracts) accessible to both (just `auth:api`)

Wrap admin-only routes in `middleware('ensure.role:admin')`:
- New admin/onboarding endpoints
- User management operations (the `organizations.members.invite`, `organizations.members.remove`)

**Acceptance Criteria:**
- [ ] `EnsureRole` middleware returns 403 for wrong role group, passes `super_admin`
- [ ] `POST /api/v1/auth/register` creates a pending user and returns 201
- [ ] Login returns 403 with descriptive message for pending/rejected users
- [ ] `GET /api/v1/admin/registrations` returns only pending users (paginated)
- [ ] Approve/reject endpoints toggle `is_active` and `approval_status` correctly
- [ ] Admin invite endpoint creates an active, approved user with temp password
- [ ] Shipper-only API routes return 403 for drayage roles
- [ ] Drayage-only API routes return 403 for shipper roles

---

## Step 2: Frontend Role Infrastructure (Router Guards + Dynamic Sidebar)

### 2.1 Add role-awareness helpers to auth store
**File:** `resources/js/stores/auth.js`

Add computed properties:
```js
const isAdmin = computed(() => role.value === 'super_admin');
const isShipper = computed(() => ['shipper_admin', 'shipper_user', 'shipper_viewer'].includes(role.value));
const isCarrier = computed(() => ['drayage_admin', 'drayage_dispatcher', 'drayage_driver'].includes(role.value));
const approvalStatus = computed(() => user.value?.approval_status || null);
```

Export these new computed properties.

### 2.2 Add role meta to router routes
**File:** `resources/js/router/index.js`

Add `meta.roles` to each route definition. This is an array of role groups allowed to access the route. If absent, all authenticated users can access it.

```js
// Example:
{ path: 'vessels', name: 'vessels', component: ..., meta: { roles: ['shipper', 'admin'] } }
{ path: 'carrier/dashboard', name: 'carrier-dashboard', component: ..., meta: { roles: ['carrier', 'admin'] } }
```

Role group mapping in the guard:
- `'shipper'` matches `shipper_admin`, `shipper_user`, `shipper_viewer`
- `'carrier'` matches `drayage_admin`, `drayage_dispatcher`, `drayage_driver`
- `'admin'` matches `super_admin`

### 2.3 Add role-based navigation guard
**File:** `resources/js/router/index.js` (update existing `beforeEach`)

After the existing auth check, add:
```js
if (to.meta.roles) {
    const userRole = authStore.role;
    const allowed = to.meta.roles.some(group => {
        if (group === 'admin') return userRole === 'super_admin';
        if (group === 'shipper') return ['shipper_admin', 'shipper_user', 'shipper_viewer'].includes(userRole);
        if (group === 'carrier') return ['drayage_admin', 'drayage_dispatcher', 'drayage_driver'].includes(userRole);
        return false;
    });
    if (!allowed) {
        next({ name: 'dashboard' }); // redirect to their role's default page
        return;
    }
}
```

### 2.4 Create role-based navigation config
**File:** `resources/js/config/navigation.js` (NEW)

Extract sidebar navigation from `AppLayout.vue` into a config file. Each nav item gets a `roles` property:

```js
export const navigationGroups = [
    {
        label: '',
        items: [
            { label: 'Dashboard', icon: 'pi-home', to: '/', name: 'dashboard', roles: ['shipper', 'admin'] },
            { label: 'Dashboard', icon: 'pi-home', to: '/carrier/dashboard', name: 'carrier-dashboard', roles: ['carrier'] },
        ],
    },
    {
        label: 'Drayage',
        items: [...],
        roles: ['shipper', 'admin'],  // group-level role filter
    },
    {
        label: 'My Work',
        items: [
            { label: 'My Assignments', icon: 'pi-list', to: '/carrier/assignments', roles: ['carrier'] },
            { label: 'Dispatch Board', icon: 'pi-th-large', to: '/carrier/dispatch', roles: ['carrier'] },
            { label: 'Drayage Execution', icon: 'pi-check-circle', to: '/carrier/drayage', roles: ['carrier'] },
            { label: 'Submit Invoice', icon: 'pi-receipt', to: '/carrier/invoices', roles: ['carrier'] },
        ],
        roles: ['carrier'],
    },
    // ... existing groups with roles: ['shipper', 'admin']
    {
        label: 'Admin',
        items: [
            { label: 'CSV Upload', icon: 'pi-upload', to: '/uploads/csv', roles: ['shipper', 'admin'] },
            { label: 'Onboarding', icon: 'pi-user-plus', to: '/admin/onboarding', roles: ['admin'] },
            { label: 'Settings', icon: 'pi-cog', to: '/settings', roles: ['shipper', 'carrier', 'admin'] },
        ],
    },
];

export function getNavigationForRole(userRole) {
    // Filters groups and items based on role, returns only what the user should see
}
```

### 2.5 Update AppLayout to use dynamic nav
**File:** `resources/js/layouts/AppLayout.vue`

Replace the hardcoded `navGroups` constant with:
```js
import { getNavigationForRole } from '@/config/navigation';
const navGroups = computed(() => getNavigationForRole(authStore.role));
```

### 2.6 Add carrier routes to router
**File:** `resources/js/router/index.js`

Add new routes under the authenticated layout:
```js
// Carrier routes
{ path: 'carrier/dashboard', name: 'carrier-dashboard', component: () => import('@/views/carrier/CarrierDashboardView.vue'), meta: { roles: ['carrier', 'admin'] } },
{ path: 'carrier/assignments', name: 'carrier-assignments', component: () => import('@/views/carrier/CarrierAssignmentsView.vue'), meta: { roles: ['carrier', 'admin'] } },
{ path: 'carrier/dispatch', name: 'carrier-dispatch', component: () => import('@/views/carrier/DispatchBoardView.vue'), meta: { roles: ['carrier', 'admin'] } },
{ path: 'carrier/drayage', name: 'carrier-drayage', component: () => import('@/views/carrier/DrayageExecutionView.vue'), meta: { roles: ['carrier', 'admin'] } },
{ path: 'carrier/drayage/:uuid', name: 'carrier-drayage-detail', component: () => import('@/views/carrier/DrayageExecutionDetailView.vue'), meta: { roles: ['carrier', 'admin'] } },
{ path: 'carrier/invoices', name: 'carrier-invoices', component: () => import('@/views/carrier/CarrierInvoiceListView.vue'), meta: { roles: ['carrier', 'admin'] } },
{ path: 'carrier/invoices/new', name: 'carrier-invoice-new', component: () => import('@/views/carrier/CarrierInvoiceFormView.vue'), meta: { roles: ['carrier', 'admin'] } },

// Admin routes
{ path: 'admin/onboarding', name: 'admin-onboarding', component: () => import('@/views/admin/OnboardingView.vue'), meta: { roles: ['admin'] } },

// Registration (public)
{ path: '/register', name: 'register', component: () => import('@/views/auth/RegisterView.vue'), meta: { requiresGuest: true } },
```

### 2.7 Update default dashboard redirect per role
**File:** `resources/js/router/index.js`

Update the root path handler so carriers go to `/carrier/dashboard`:
```js
{
    path: '',
    name: 'dashboard',
    component: () => import('@/views/dashboard/DashboardView.vue'),
    beforeEnter: (to, from, next) => {
        const authStore = useAuthStore();
        if (authStore.isCarrier) {
            next({ name: 'carrier-dashboard' });
        } else {
            next();
        }
    },
},
```

**Acceptance Criteria:**
- [ ] Carrier user logging in is redirected to `/carrier/dashboard`
- [ ] Carrier user sees only carrier nav items in sidebar (My Assignments, Dispatch Board, Drayage Execution, Submit Invoice, Settings)
- [ ] Shipper user sees the existing full sidebar minus admin-only items
- [ ] Admin user sees everything (shipper + admin items)
- [ ] Navigating to a restricted URL (e.g., carrier going to `/vessels`) redirects to their dashboard
- [ ] Navigation config is extracted to `resources/js/config/navigation.js`
- [ ] `auth.js` store exports `isAdmin`, `isShipper`, `isCarrier` computed properties

---

## Step 3: Carrier-Specific Vue Views & Pinia Store

### 3.1 Create carrier Pinia store
**File:** `resources/js/stores/carrier.js` (NEW)

State and actions for carrier-specific data:
- `assignments` -- list of containers assigned to this carrier
- `dispatchBoard` -- active dispatches with driver assignments
- `fetchAssignments(filters)` -- calls `GET /api/v1/carrier/assignments`
- `fetchDispatchBoard()` -- calls `GET /api/v1/carrier/dispatch`
- `updateDrayageStep(uuid, step)` -- calls existing drayage step endpoints
- `submitInvoice(data)` -- calls `POST /api/v1/carrier/invoices`

### 3.2 Create Carrier API endpoints
**File:** `app/Http/Controllers/Api/V1/Carrier/CarrierPortalController.php` (NEW)

Endpoints (all behind `ensure.role:drayage` middleware):
- `GET /api/v1/carrier/assignments` -- containers where `assigned_carrier_id = auth user's tenant`. Paginated, filterable by status
- `GET /api/v1/carrier/assignments/{uuid}` -- single assignment detail
- `GET /api/v1/carrier/dispatch` -- active dispatches for this carrier
- `POST /api/v1/carrier/invoices` -- submit drayage invoice for a completed assignment
- `GET /api/v1/carrier/invoices` -- invoices submitted by this carrier
- `GET /api/v1/carrier/dashboard/stats` -- summary counts (pending pickup, in-transit, delivered, total assigned)

Routes registered in `routes/tenant.php` inside a `middleware('ensure.role:drayage')` group.

### 3.3 Create carrier Vue views
All views follow existing patterns: PrimeVue DataTable, PageHeader, useToast, Tailwind.

**`resources/js/views/carrier/CarrierDashboardView.vue`**
- Summary stat cards: Pending Pickup, In Transit, Delivered Today, Total Assigned
- Recent assignments table (last 10)
- Quick action buttons for common tasks

**`resources/js/views/carrier/CarrierAssignmentsView.vue`**
- DataTable of assigned containers with columns: Container #, MBL, Status, Pickup Location, Delivery Location, Appointment Date, Actions
- Status filter tabs: All, Pending, Picked Up, In Transit, Delivered
- Click row to open drayage execution detail

**`resources/js/views/carrier/DispatchBoardView.vue`**
- Card-based or Kanban-style view of active dispatches
- Group by status: Pending Pickup, In Transit, Delivering
- Each card shows container #, driver assigned, pickup/delivery location, ETA

**`resources/js/views/carrier/DrayageExecutionView.vue`**
- List of assigned containers that need drayage step updates
- Bulk action buttons for common step updates

**`resources/js/views/carrier/DrayageExecutionDetailView.vue`**
- Container detail with drayage step stepper/timeline
- Buttons to advance step: Mark Picked Up, Mark In Transit, Mark Delivered, Mark Empty Returned
- Calls existing `POST /api/v1/drayage/{uuid}/pickup`, `/delivered`, `/empty-return` endpoints

**`resources/js/views/carrier/CarrierInvoiceListView.vue`**
- DataTable of carrier's submitted invoices
- Status column: Draft, Submitted, Approved, Paid

**`resources/js/views/carrier/CarrierInvoiceFormView.vue`**
- Form to submit invoice for a completed drayage job
- Fields: Select container/assignment, amount, line items, notes, attachment upload
- Submit button calls `POST /api/v1/carrier/invoices`

**Acceptance Criteria:**
- [ ] Carrier dashboard shows 4 stat cards with real counts from the API
- [ ] Assignments list filters by status and only shows containers assigned to the carrier
- [ ] Dispatch board renders assigned loads in a grouped layout
- [ ] Drayage execution detail allows advancing steps (calls existing drayage step API)
- [ ] Invoice submission form creates a drayage invoice record
- [ ] All views use PrimeVue 4 components consistent with existing app style

---

## Step 4: Onboarding Flows (Registration + Admin Approval)

### 4.1 Create RegisterView
**File:** `resources/js/views/auth/RegisterView.vue` (NEW)

Uses `AuthLayout.vue` wrapper (same as LoginView). Multi-section form:

Section 1 - Choose Role:
- Two large selectable cards: "I'm a Shipper" / "I'm a Carrier"
- Shipper description: "Track containers, manage shipments, monitor charges"
- Carrier description: "Manage drayage assignments, dispatch drivers, submit invoices"

Section 2 - Account Details:
- First Name, Last Name, Email, Password, Confirm Password

Section 3 - Company Info:
- Company Name

Submit calls `POST /api/v1/auth/register`.
On success, show a "Registration Submitted" confirmation panel with message: "Your account is pending admin approval. You'll receive an email when your account is activated."

Link at bottom: "Already have an account? Sign in"

### 4.2 Add register link to LoginView
**File:** `resources/js/views/auth/LoginView.vue`

Add below the sign-in form:
```html
<p class="text-center text-sm text-gray-500 mt-4">
    Don't have an account?
    <router-link to="/register" class="text-blue-600 hover:text-blue-700 font-medium">Register</router-link>
</p>
```

### 4.3 Create Admin Onboarding View
**File:** `resources/js/views/admin/OnboardingView.vue` (NEW)

Two tabs:
**Tab 1 - Pending Approvals:**
- DataTable of pending registrations: Name, Email, Company, Role, Registered Date, Actions (Approve/Reject)
- Approve opens a confirm dialog
- Reject opens a dialog with optional reason text field
- Badge count on the tab header

**Tab 2 - Invite User:**
- Enhanced invite form (extends existing UserManagement pattern):
  - First Name, Last Name, Email, Company Name
  - Role Select: Shipper User, Shipper Admin, Drayage Dispatcher, Drayage Admin
  - Button: "Create Account"
  - On submit, calls `POST /api/v1/admin/invite`
  - Shows generated temp password in a dialog (user copies it to share)

### 4.4 Create admin Pinia store
**File:** `resources/js/stores/admin.js` (NEW)

State and actions:
- `pendingRegistrations` -- list of pending users
- `pendingCount` -- count for badge display
- `fetchPendingRegistrations()` -- `GET /api/v1/admin/registrations?status=pending`
- `approveRegistration(uuid)` -- `POST /api/v1/admin/registrations/{uuid}/approve`
- `rejectRegistration(uuid, reason)` -- `POST /api/v1/admin/registrations/{uuid}/reject`
- `inviteUser(data)` -- `POST /api/v1/admin/invite`

### 4.5 Add pending approval badge to sidebar
**File:** `resources/js/config/navigation.js`

The "Onboarding" nav item should support a dynamic badge. In `AppLayout.vue`, fetch the pending count on mount if the user is admin, and pass it to the nav item via a reactive ref.

### 4.6 Handle pending/rejected status in login flow
**File:** `resources/js/views/auth/LoginView.vue`

Update `handleLogin` error handler to detect 403 responses with approval-related messages and show appropriate UI:
- Pending: "Your registration is pending admin approval."
- Rejected: "Your registration has been declined. Contact support for assistance."

**Acceptance Criteria:**
- [ ] `/register` page shows role picker, account form, and company field
- [ ] Successful registration shows "pending approval" message
- [ ] Login page has "Register" link
- [ ] Login shows appropriate error for pending/rejected users
- [ ] Admin Onboarding page lists pending registrations with approve/reject actions
- [ ] Admin invite form creates an active user and shows temp password
- [ ] Sidebar "Onboarding" item shows badge with pending count (admin only)

---

## Step 5: Wiring, Settings Scoping & Polish

### 5.1 Scope Settings sidebar for carrier users
Carrier users visiting `/settings` should only see:
- Organization (their company profile)
- Profile (their own profile)

They should NOT see: User Management, SSO, Webhooks, Carrier Contracts, Billing, Custom Columns.

Implement by adding `meta.roles` to settings routes and filtering the settings sub-nav.

### 5.2 Add "Pending Approval" interstitial page
**File:** `resources/js/views/auth/PendingApprovalView.vue` (NEW)

If a user somehow has a token but `approval_status === 'pending'`, show a dedicated page:
- "Your account is pending approval" message
- "Check back later" / "Contact admin" guidance
- Logout button

Router guard: if authenticated and `approval_status !== 'approved'`, redirect to this page.

### 5.3 Ensure `api/v1/auth/me` returns `approval_status`
**File:** `app/Http/Controllers/Api/V1/Auth/AuthController.php`

Add `approval_status` to both `login()` and `me()` response payloads.

### 5.4 Smoke test checklist
Create a quick verification plan:
- Login as `super_admin` -- sees full sidebar + admin onboarding page
- Login as `shipper_user` -- sees shipper sidebar, no admin items, no carrier items
- Login as `drayage_dispatcher` -- sees carrier sidebar, redirected to carrier dashboard
- Attempt to navigate carrier user to `/vessels` -- gets redirected
- Attempt to hit `GET /api/v1/vessels` as carrier -- gets 403
- Register a new shipper account -- lands on pending page
- Admin approves -- user can now log in
- Admin rejects -- user sees rejection message on login

**Acceptance Criteria:**
- [ ] Carrier settings page shows only Organization and Profile
- [ ] Pending approval interstitial page shows for unapproved users
- [ ] `me` endpoint includes `approval_status` field
- [ ] All 8 smoke test scenarios pass

---

## Files Summary

### New Files (~20)
| File | Purpose |
|------|---------|
| `app/Http/Middleware/EnsureRole.php` | Generic parameterized role middleware |
| `database/migrations/central/..._add_approval_fields_to_users.php` | approval_status, company_name columns |
| `app/Http/Controllers/Api/V1/Auth/RegistrationController.php` | Public registration endpoint |
| `app/Http/Controllers/Api/V1/Admin/OnboardingController.php` | Admin approval + invite endpoints |
| `app/Http/Controllers/Api/V1/Carrier/CarrierPortalController.php` | Carrier-specific API endpoints |
| `resources/js/config/navigation.js` | Role-aware nav config (extracted from AppLayout) |
| `resources/js/stores/carrier.js` | Carrier Pinia store |
| `resources/js/stores/admin.js` | Admin Pinia store |
| `resources/js/views/auth/RegisterView.vue` | Self-registration page |
| `resources/js/views/auth/PendingApprovalView.vue` | Pending approval interstitial |
| `resources/js/views/admin/OnboardingView.vue` | Admin onboarding + approval panel |
| `resources/js/views/carrier/CarrierDashboardView.vue` | Carrier dashboard |
| `resources/js/views/carrier/CarrierAssignmentsView.vue` | Carrier assignments list |
| `resources/js/views/carrier/DispatchBoardView.vue` | Dispatch board |
| `resources/js/views/carrier/DrayageExecutionView.vue` | Drayage step management list |
| `resources/js/views/carrier/DrayageExecutionDetailView.vue` | Single container drayage execution |
| `resources/js/views/carrier/CarrierInvoiceListView.vue` | Carrier's submitted invoices |
| `resources/js/views/carrier/CarrierInvoiceFormView.vue` | Invoice submission form |

### Modified Files (~10)
| File | Change |
|------|--------|
| `bootstrap/app.php` | Register `ensure.role` middleware alias |
| `routes/tenant.php` | Wrap route groups in role middleware, add carrier/admin routes |
| `routes/api.php` | Add registration endpoint |
| `app/Http/Controllers/Api/V1/Auth/AuthController.php` | Add approval_status checks and response field |
| `resources/js/stores/auth.js` | Add isAdmin, isShipper, isCarrier, approvalStatus computed |
| `resources/js/router/index.js` | Add role meta, role guard, carrier/admin routes, register route |
| `resources/js/layouts/AppLayout.vue` | Use dynamic nav from config, add pending badge |
| `resources/js/views/auth/LoginView.vue` | Add register link, handle approval error messages |

---

## Success Criteria

1. A carrier user logging in sees only carrier-relevant pages and sidebar items
2. A shipper user sees the existing shipper views but not admin or carrier pages
3. An admin sees everything including the onboarding/approval panel
4. Self-registration creates a pending user who cannot log in until approved
5. Admin can approve/reject pending registrations and directly invite new users
6. Both frontend (router guard) and backend (middleware) enforce role access
7. No regressions to existing shipper functionality
