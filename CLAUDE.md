# Billr — Project Context

Billr is a freelancer invoicing SaaS. Freelancers register or sign in through Thijssensoftware ID,
create a workspace, manage clients and projects, track time, and generate invoices. Clients get a
login for the invoice portal, and a separate token link for approving timesheets.

Production: `billr.thijssensoftware.nl`.

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

# Quality gate — all three must pass before a PR
php84 vendor/bin/pint
php84 vendor/bin/phpstan analyse --memory-limit=512M   # level 10
php84 artisan test
```

CI runs the same three on every PR (`.github/workflows/tests.yml`). Deploys are manual
(`.github/workflows/deploy.yml`).

## Dev accounts (after seeding)

| Account | Email | Password | Role |
|---|---|---|---|
| Freelancer | `dev@billr.test` | `password` | Freelancer (workspace owner) |
| Client portal | `portal@acme.example` | `password` | Client (sees Acme Corp invoices) |

Hit `http://billr.test/dev-login` to bypass the login form (local env only).

## Domain model

```
users                — freelancers and client portal users (type: freelancer|client)
                       password is nullable: SSO users never set one
workspaces           — one per freelancer/agency; owner_id → users
                       require_client_approval gates invoicing on client sign-off
workspace_user       — pivot (role: owner|member)
invitations          — workspace member invites + client portal invites (token-based)
clients              — belong to workspace; currency overrides workspace default
                       portal_token authenticates the tokened timesheet approval page
client_user          — pivot: which client portal users can see which clients
projects             — belong to client; type: hourly|fixed; rates stored in cents
time_entries         — belong to project + user; duration_minutes computed on stop
                       client_approved set from the approval portal
                       external_source/external_ref dedupe API-ingested entries
invoices             — belong to workspace + client; amounts in cents
                       status: draft|sent|paid|overdue|void
                       stripe_payment_link / stripe_session_id
invoice_lines        — line items; quantity = minutes when unit is 'hours', else a count
invoice_time_entries — pivot marking which entries are billed
invoice_projects     — pivot marking which fixed-price projects are billed
personal_access_tokens — Sanctum, for the time-entry ingest API
```

### Invoice invariants

- Numbers are `INV-{year}-{0000}`, allocated **per workspace**. The unique index is
  `(workspace_id, number)`. The sequence is read off the highest existing number
  **including soft-deleted rows**, inside a transaction with a row lock. Never derive it
  from a row count: soft deletes desync it and the insert fails.
- A time entry or fixed-price project can only be billed once. The filters live in
  `CreateInvoiceFromTimeEntries`, not only in the picker queries, so a replayed request
  cannot double-bill.
- `void` is terminal. Voiding releases the attached time entries so the hours can be
  rebilled; sending, settling and payment-link generation all refuse a voided invoice.
- Only a `draft` invoice accepts line edits.
- Money is always integer cents, end to end.

## Architecture conventions (project-specific)

- **Actions** (`app/Actions/`) — one `handle()` method, injected via DI
  - `CreateWorkspace`, `RegisterFreelancer`
  - `AcceptWorkspaceInvitation` / `AcceptClientInvitation` — token-based onboarding
  - `CreateInvoiceFromTimeEntries` — builds an invoice from time entries **and**
    fixed-price projects (the name is narrower than what it does now)
  - `IngestExternalTimeEntry` — the API ingest path
  - `SendClientPortalAccess` — issues the portal token and mails the link
- **Services** (`app/Services/`) — infrastructure: `StripeService` (payment links +
  webhook signature verification, client built lazily), `InvoicePdfRenderer`,
  `Portal\IdPortalClient`
- **Controllers** are thin: validate via Form Request, call Action or Eloquent, return Inertia
- **Authorization** is inline `abort_unless()` scoped to `current_workspace_id`, plus two
  gates in `AppServiceProvider` (`access-workspace`, `access-portal`). No Policies yet.
- `CarbonImmutable` for all dates (`Date::use(...)` in `AppServiceProvider`)
- `EnsureWorkspace` middleware redirects a workspace-less freelancer to the creation screen.
  The workspace routes sit deliberately outside it, or the user is stranded.

## Auth

Three ways in:

- **Thijssensoftware ID SSO** — `auth/sso/redirect` and `auth/sso/callback`, provided by the
  `thijssensoftware/id-client` package. Links an existing user by email; provisioning is
  disabled, so an unknown user or one without billr access is denied.
- **Local email + password** — `LoginController`, for accounts that have a password.
- **Invitation tokens** — workspace member and client portal invites.

The timesheet approval portal at `/client-portal/{token}` is unauthenticated by design and
throttled, since the token is the only credential.

## API

`routes/api.php`, Sanctum tokens issued with
`php84 artisan billr:token {email} [--name=] [--ability=]`:

- `POST /api/time-entries` — ingest an external time entry (ability `time-entries:create`,
  throttled). Deduped on `external_source` + `external_ref`.

Token management from the UI is not built (BILLR-29).

## Scheduled work

`Schedule::command(MarkInvoicesOverdue::class)->dailyAt('06:00')` — moves `sent` invoices past
`due_at` to `overdue`. It deliberately touches only `sent`, so `void` and `paid` are left alone.

## Frontend structure

```
resources/js/
  app.ts                  — Inertia bootstrap, ZiggyVue, Toaster
  config/nav.ts           — Sidebar nav items
  composables/useFlash.ts — Watches shared flash props, fires vue-sonner toasts
  layouts/                — AppLayout, AuthLayout, SettingsLayout, PortalLayout
  pages/
    auth/                 — Login, Register, AcceptInvitation
    Home.vue, Dashboard.vue
    workspaces/Create.vue
    clients/              — Index (table + modal), Show
    projects/Index.vue    — Table + create/edit modal
    time/Index.vue        — Live timer + manual entry + entry table
    invoices/             — Index, Create (pick client → entries + fixed-price projects),
                            Show (document, actions, draft line editor)
    settings/             — Profile, Workspace, Members
    portal/               — Dashboard, Invoice
  components/             — AppSidebar, WorkspaceSwitcher, PortalSwitcher, UserMenu,
                            PageHeader, StatusBadge, AppIcon, ui/ (shadcn-vue, do not edit)
  types/index.ts          — User, Workspace, SharedProps
```

Blade is still used where Inertia is not: `pdf/invoice` (the PDF document),
`portal/approval` (the tokened timesheet page), and the two mail views.

## Shared Inertia props (every page, via `usePage<SharedProps>()`)

```ts
auth.user      — { id, name, email, type }
auth.workspace — current Workspace (freelancers only)
flash.success  — string | null  (shown via useFlash → Sonner toast)
flash.error    — string | null
```

## What is built

- [x] Auth: SSO, local login, register, invitation accept (workspace + client)
- [x] App shell: sidebar, workspace switcher, portal switcher, user menu, mobile drawer
- [x] Clients: CRUD, soft delete, portal access links
- [x] Projects: CRUD, hourly and fixed-price
- [x] Time tracking: live timer, manual entry, edit/delete, pagination
- [x] Time entry ingest API (Sanctum, deduped)
- [x] Client timesheet approval portal (token, per-entry selection, throttled)
- [x] Invoices: build from time entries and fixed-price projects, manual draft lines,
      send by email with PDF attached, PDF download, Stripe payment links, mark sent/paid,
      void, delete drafts
- [x] Stripe webhook settlement (verifies payment_status, amount and currency; handles
      delayed payment methods)
- [x] Overdue detection (scheduled command)
- [x] Client portal: invoice list, detail, print, PDF download
- [x] Dashboard stats (outstanding, paid this month, overdue count)
- [x] Settings: profile, workspace, members with invite
- [x] Pint, PHPStan level 10, Pest, CI on every PR

## Known gaps

- API token management in the UI (BILLR-29)
- Recurring invoices
- Policies instead of the repeated inline `abort_unless()` in each controller
- `TimeEntryController::start()` deletes a running timer instead of stopping it
- Time entries stay editable after they have been invoiced

## Key files to know

| File | Purpose |
|---|---|
| `routes/web.php` | App + portal routes (66 total across web and api) |
| `routes/api.php` | Sanctum time-entry ingest |
| `routes/console.php` | Overdue schedule |
| `app/Actions/CreateInvoiceFromTimeEntries.php` | Invoice building + number allocation |
| `app/Providers/AppServiceProvider.php` | CarbonImmutable, gates |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shared props |
| `database/seeders/DatabaseSeeder.php` | Full test data |
| `resources/js/types/index.ts` | TypeScript interfaces |
| `resources/css/app.css` | Tailwind v4 + shadcn CSS variables |
| `components.json` | shadcn-vue config (New York, neutral, CSS vars) |
