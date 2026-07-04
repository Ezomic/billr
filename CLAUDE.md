# Billr — Project Context

Billr is a freelancer invoicing SaaS. Freelancers register, create a workspace, manage clients and projects, track time, and generate invoices. Clients can log in to a portal to view their invoices.

## Dev commands

```bash
# PHP binary (Herd — always use this, 'php' is not in PATH)
~/Library/Application\ Support/Herd/bin/php84 artisan <command>

# Node/npm (also not in PATH by default)
export PATH="$HOME/Library/Application Support/Herd/config/nvm/versions/node/v22.23.1/bin:$HOME/Library/Application Support/Herd/bin:$PATH"
npm run dev    # Vite dev server (hot reload)
npm run build  # Production build

# Database
php84 artisan migrate:fresh --seed   # Reset + seed test data
php84 artisan migrate                # Run pending migrations

# Routes
php84 artisan route:list
```

## Dev accounts (after seeding)

| Account | Email | Password | Role |
|---|---|---|---|
| Freelancer | `dev@billr.test` | `password` | Freelancer (workspace owner) |
| Client portal | `portal@acme.example` | `password` | Client (sees Acme Corp invoices) |

Hit `http://billr.test/dev-login` to bypass the login form.

## Domain model

```
users               — freelancers and client portal users (type: freelancer|client)
workspaces          — one per freelancer/agency; owner_id → users
workspace_user      — pivot (role: owner|member)
invitations         — workspace member invites + client portal invites (token-based)
clients             — belong to workspace; currency overrides workspace default
client_user         — pivot: which client portal users can see which clients
projects            — belong to client; type: hourly|fixed; rates stored in cents
time_entries        — belong to project + user; duration_minutes computed on stop
invoices            — belong to workspace + client; amounts in cents; status: draft|sent|paid|overdue|void
invoice_lines       — line items; quantity = minutes for hourly, 1 for fixed
invoice_time_entries — pivot marking which entries are billed
```

## Architecture conventions (project-specific)

- **Actions** (`app/Actions/`) — one `handle()` method, injected via DI
  - `CreateWorkspace` — creates workspace, attaches owner, sets `current_workspace_id`
  - `RegisterFreelancer` — creates user + workspace
  - `AcceptWorkspaceInvitation` / `AcceptClientInvitation` — token-based onboarding
  - `CreateInvoiceFromTimeEntries` — builds invoice + lines from selected time entries
- **Controllers** are thin: validate via Form Request, call Action or Eloquent, return Inertia response
- **Authorization** is inline `abort_unless()` checks scoped to `current_workspace_id` — no Policies yet
- All monetary values stored and computed in **cents** (integers); formatted in Vue with `Intl.NumberFormat`
- `CarbonImmutable` is used for all dates (`Date::use(CarbonImmutable::class)` in `AppServiceProvider`)

## Frontend structure

```
resources/js/
  app.ts                  — Inertia bootstrap, ZiggyVue, Toaster
  config/nav.ts           — Sidebar nav items (label, route, icon)
  composables/
    useFlash.ts           — Watches shared flash props, fires vue-sonner toasts
  layouts/
    AppLayout.vue         — Authenticated shell (sidebar + mobile drawer + Toaster)
    AuthLayout.vue        — Centered card for login/register
    SettingsLayout.vue    — AppLayout + left settings nav (Profile/Workspace/Members)
    PortalLayout.vue      — Minimal header + content for client portal
  pages/
    auth/Login.vue        — Login form + dev-login button (local env only)
    auth/Register.vue     — Register with workspace name
    auth/AcceptInvitation.vue — Handles both workspace and client invitations
    Dashboard.vue
    clients/Index.vue     — Table + create/edit modal
    projects/Index.vue    — Table + create/edit modal
    time/Index.vue        — Live timer + manual entry + entry table
    invoices/Index.vue    — Invoice list with status badges
    invoices/Create.vue   — Pick client → select unbilled entries → preview totals → create
    invoices/Show.vue     — Invoice document view + mark sent/paid actions
    settings/Profile.vue
    settings/Workspace.vue
    settings/Members.vue
    portal/Dashboard.vue  — Client invoice list
    portal/Invoice.vue    — Client invoice detail + print
  components/
    AppSidebar.vue        — Nav + WorkspaceSwitcher + UserMenu
    WorkspaceSwitcher.vue — Shows current workspace (dropdown stub for multi-workspace)
    UserMenu.vue          — Avatar, name, email, logout, settings link
    PageHeader.vue        — Title + description + right slot for actions
    StatusBadge.vue       — Coloured badge for invoice status
    ui/                   — shadcn-vue components (New York style, do not edit directly)
  types/index.ts          — User, Workspace, SharedProps interfaces
```

## Shared Inertia props (available on every page via `usePage<SharedProps>()`)

```ts
auth.user      — { id, name, email, type }
auth.workspace — current Workspace (freelancers only)
flash.success  — string | null  (shown via useFlash → Sonner toast)
flash.error    — string | null
```

## What is built

- [x] Auth: register (creates workspace), login, logout, invitation accept (workspace + client)
- [x] App shell: sidebar, workspace switcher, user menu, mobile drawer
- [x] Clients: CRUD (modal), soft delete
- [x] Projects: CRUD (modal), hourly/fixed types
- [x] Time tracking: live timer, manual entry, edit/delete, pagination
- [x] Invoices: create from unbilled time entries, view as document, mark sent/paid/delete
- [x] Settings: profile, workspace, members with invite
- [x] Client portal: invoice list + detail with print
- [x] Test data seeder (3 clients, 4 projects, 17 time entries, 3 invoices in various states)

## Scheduler

`invoices:mark-overdue` runs daily at 06:00 via `routes/console.php` (`Schedule::command(...)->dailyAt('06:00')`). It bulk-updates `sent` invoices where `due_at < today` to `overdue`.

A launchd agent keeps the scheduler alive locally:
`~/Library/LaunchAgents/nl.thijssensoftware.billr.scheduler.plist` — uses `schedule:work`, `KeepAlive: true`. Logs to `~/Library/Logs/billr-scheduler.log`.

## What is NOT built yet (good next steps)

- [ ] PDF generation (e.g. `spatie/browsershot` or `barryvdh/laravel-dompdf`)
- [ ] Email sending (invite emails, invoice emails to clients)
- [ ] Dashboard with real stats (total revenue, outstanding invoices, hours this month)
- [ ] Multi-workspace switching (WorkspaceSwitcher dropdown is stubbed)
- [ ] Recurring invoices
- [ ] Client portal: approve timesheets before invoicing
- [ ] Stripe payment links on invoices
- [ ] PHPStan / Larastan setup (level 7 per global CLAUDE.md)
- [ ] Pest feature tests

## Key files to know

| File | Purpose |
|---|---|
| `routes/web.php` | All 45 routes |
| `app/Providers/AppServiceProvider.php` | CarbonImmutable, Gate definitions |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shared props |
| `database/seeders/DatabaseSeeder.php` | Full test data |
| `resources/js/types/index.ts` | TypeScript interfaces |
| `resources/css/app.css` | Tailwind v4 + shadcn CSS variables |
| `components.json` | shadcn-vue config (New York, neutral, CSS vars) |

## Linear

Team: **BILLR** — `47312f00-9ae4-4b4e-8700-56de594c92b5`

Branch format: `feature/billr-{number}-{description}` or `fix/billr-{number}-{description}`

Follow the full workflow in `~/.claude/CLAUDE.md`. See parent context in `~/Projects/billr/CLAUDE.md`.
