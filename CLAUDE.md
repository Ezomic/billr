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
                       payment_terms_days is the default due-date offset
                       send_payment_reminders switches overdue chasing off
workspace_user       — pivot (role: owner|member)
invitations          — workspace member invites + client portal invites (token-based)
clients              — belong to workspace; currency and payment_terms_days both
                       override the workspace default
                       portal_token authenticates the tokened timesheet approval page
client_user          — pivot: which client portal users can see which clients
projects             — belong to client; type: hourly|fixed; rates stored in cents
                       status: active|archived (archived stops new time, not billing)
time_entries         — belong to project + user; duration_minutes computed on stop
                       client_approved set from the approval portal
                       external_source/external_ref dedupe API-ingested entries
invoices             — belong to workspace + client; amounts in cents
                       status: draft|sent|paid|overdue|void
                       stripe_payment_link / stripe_session_id
                       recurring_invoice_id + recurring_period when generated
invoice_lines        — line items; quantity = minutes when unit is 'hours', else a count
invoice_time_entries — pivot marking which entries are billed
invoice_projects     — pivot marking which fixed-price projects are billed
invoice_reminders    — one row per invoice per overdue milestone already mailed
recurring_invoices   — retainer schedules; interval monthly|quarterly|yearly,
                       status active|paused, next_run_on drives generation
recurring_invoice_lines — the lines each generated invoice repeats
personal_access_tokens — Sanctum, for the time-entry ingest API
```

### Invoice invariants

- Numbers are `INV-{year}-{0000}`, allocated **per workspace** by `AllocateInvoiceNumber`
  and nowhere else. The unique index is `(workspace_id, number)`. The sequence is read off
  the highest existing number **including soft-deleted rows**, inside a transaction with a
  row lock. Never derive it from a row count: soft deletes desync it and the insert fails.
- A time entry or fixed-price project can only be billed once. The filters live in
  `CreateInvoiceFromTimeEntries`, not only in the picker queries, so a replayed request
  cannot double-bill.
- `void` is terminal. Voiding releases the attached time entries so the hours can be
  rebilled; sending, settling and payment-link generation all refuse a voided invoice.
- Only a `draft` invoice accepts line edits, notes and date changes.
- A time entry that is on an invoice cannot be edited or deleted. Void the invoice first,
  which detaches the entries and makes them editable and billable again.
- Copying an invoice deliberately does **not** copy `invoice_time_entries` or
  `invoice_projects`. A copy is a fresh manual charge, not a second claim on the source's
  billable work.
- Generated recurring invoices are unique on `(recurring_invoice_id, recurring_period)`.
  That index, not the command, is what stops a repeated scheduler run double-billing.
- Reminders are unique on `(invoice_id, days_overdue)` for the same reason: the command
  claims the milestone by inserting before it mails.
- Money is always integer cents, end to end.

## Architecture conventions (project-specific)

- **Actions** (`app/Actions/`) — one `handle()` method, injected via DI
  - `CreateWorkspace`, `RegisterFreelancer`
  - `AcceptWorkspaceInvitation` / `AcceptClientInvitation` — token-based onboarding
  - `CreateInvoiceFromTimeEntries` — builds an invoice from time entries **and**
    fixed-price projects (the name is narrower than what it does now)
  - `AllocateInvoiceNumber` — the single source of invoice numbering
  - `CopyInvoice` — clones lines into a new draft without the billing pivots
  - `GenerateInvoiceFromSchedule` — one recurring period into one draft invoice
  - `IngestExternalTimeEntry` — the API ingest path
  - `SendClientPortalAccess` — issues the portal token and mails the link
- **Services** (`app/Services/`) — infrastructure: `StripeService` (payment links +
  webhook signature verification, client built lazily), `InvoicePdfRenderer`, `CsvExporter`,
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

Three daily commands, ordered so each sees the previous one's work:

| Time | Command | What |
|---|---|---|
| 05:45 | `invoices:generate-recurring` | Due schedules become draft invoices |
| 06:00 | `invoices:mark-overdue` | `sent` invoices past `due_at` become `overdue` |
| 06:15 | `invoices:send-reminders` | Emails the client at 3, 7 and 14 days past due |

The order matters: generation runs first so a new invoice is dated correctly and is never
flagged overdue by the same morning's sweep, and reminders run last so an invoice that
went overdue this morning is eligible the same day.

`mark-overdue` deliberately touches only `sent`, so `void` and `paid` are left alone. Both
generation and reminders take `--dry-run`.

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
    invoices/             — Index (filters + pagination), Create (pick client → entries +
                            fixed-price projects), Show (document, actions, draft editor)
    recurring/Index.vue   — Retainer schedules + line editor
    settings/             — Profile, Workspace, Members
    portal/               — Dashboard, Invoice
  components/             — AppSidebar, WorkspaceSwitcher, PortalSwitcher, UserMenu,
                            PageHeader, StatusBadge, Pagination, AppIcon,
                            ui/ (shadcn-vue, do not edit)
  types/index.ts          — User, Workspace, SharedProps
```

Blade is still used where Inertia is not: `pdf/invoice` (the PDF document),
`portal/approval` (the tokened timesheet page), and the three mail views.

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
- [x] Projects: CRUD, hourly and fixed-price, archive and restore
- [x] Time tracking: live timer, manual entry, edit/delete, pagination, CSV export
- [x] Owners can view any workspace member's time (editing stays with its owner)
- [x] Time entry ingest API (Sanctum, deduped)
- [x] Client timesheet approval portal (token, per-entry selection, throttled)
- [x] Invoices: build from time entries and fixed-price projects, manual draft lines,
      editable notes and dates on drafts, send by email with PDF attached, PDF download,
      Stripe payment links, mark sent/paid, void, copy, delete drafts
- [x] Invoice list: status/client filters, number search, pagination, CSV export
- [x] Configurable payment terms (workspace default, per-client override)
- [x] Recurring invoices: schedules with pause/resume, idempotent daily generation
- [x] Stripe webhook settlement (verifies payment_status, amount and currency; handles
      delayed payment methods)
- [x] Overdue detection and payment reminders (scheduled commands)
- [x] Client portal: invoice list, detail, print, PDF download, Pay now via Stripe
- [x] Dashboard stats (outstanding, paid this month, overdue count)
- [x] Settings: profile, workspace, members with invite
- [x] Pint, PHPStan level 10, Pest, CI on every PR

## Known gaps

- API token management in the UI (BILLR-29)
- Policies instead of the repeated inline `abort_unless()` in each controller
- `CreateInvoiceFromTimeEntries` also builds fixed-price lines now, so its name is
  narrower than what it does
- Credit notes: `void` is the only way to cancel an issued invoice

## Key files to know

| File | Purpose |
|---|---|
| `routes/web.php` | App + portal routes (79 total across web and api) |
| `routes/api.php` | Sanctum time-entry ingest |
| `routes/console.php` | Overdue schedule |
| `app/Actions/CreateInvoiceFromTimeEntries.php` | Invoice building from time and fixed-price work |
| `app/Actions/AllocateInvoiceNumber.php` | The only place invoice numbers are issued |
| `app/Providers/AppServiceProvider.php` | CarbonImmutable, gates |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shared props |
| `database/seeders/DatabaseSeeder.php` | Full test data |
| `resources/js/types/index.ts` | TypeScript interfaces |
| `resources/css/app.css` | Tailwind v4 + shadcn CSS variables |
| `components.json` | shadcn-vue config (New York, neutral, CSS vars) |
