# Open Questions

## Role-Based Views & Onboarding - 2026-05-23

- [ ] How are containers "assigned" to a carrier? -- The carrier portal queries need a column or relation (e.g., `assigned_carrier_tenant_id` on the containers table in the tenant DB) to scope containers to a carrier. Need to confirm this column exists or needs to be added.
- [ ] Should self-registered carriers create a new tenant or join an existing one? -- Currently each user belongs to a `tenant_id`. Self-registration needs a decision: does a new carrier get their own tenant (new company), or do they request to join an existing tenant? The plan assumes new tenant creation for new companies.
- [ ] Temp password delivery for admin invites -- The plan stubs email sending. Should the admin see the temp password in a dialog to share manually, or should there be a "set password" link flow via email? For MVP, plan uses the dialog approach.
- [ ] Role granularity at registration -- Self-registration defaults to `shipper_user` or `drayage_dispatcher`. Should admins be able to upgrade them to `shipper_admin` / `drayage_admin` after approval, or is that a separate operation in User Management?
- [ ] Existing `roleOptions` mismatch in UserManagementView -- The current invite dialog uses roles `admin`, `manager`, `member`, `read_only` which don't match the `UserRole` enum values (`shipper_admin`, `shipper_user`, `shipper_viewer`, etc.). This needs to be reconciled during implementation.
- [ ] Dispatch Board data model -- The DispatchBoardView assumes there's a concept of "driver assignments" within a carrier's operations. Need to confirm if there's a drivers table or driver assignment column in the tenant DB, or if this needs to be modeled.
- [ ] Carrier invoice submission -- Does a carrier submit invoices against the existing `drayage_invoices` table, or is there a separate `carrier_invoices` table? The plan assumes reusing the existing drayage invoice model with a `submitted_by_carrier` flag.
