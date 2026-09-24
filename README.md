# Zyncwork

Internal enterprise project management platform — task management, time tracking, team capacity, and payroll-ready reporting for organizations with department-based teams.

Built with **Laravel 12 + Inertia.js + React** (MySQL 8).

---

## Features

### Task Management
- Strict status flow: `Backlog → To Do → In Progress → In Review → Completed`, enforced server-side
- Task dependencies (blocked tasks can't start until the dependency completes)
- Priority / impact / effort classification, estimated hours, due dates
- Role-aware permissions: employees work only their own tasks; managers manage their department; admins manage everything

### Time Tracking
- One active timer per user, duration computed **server-side only**
- Manual entries for forgotten timers
- Two-level approval: managers approve their department's entries, admins have org-wide oversight with bulk approve
- Approved entries are locked from employee editing

### Comments & Mentions
- Threaded comments on tasks with a 15-minute edit window
- `@mention` autocomplete (token format `@[Name](id)`) with in-app notifications
- Delete rights: author, department manager, or admin

### Notifications
- Custom pipeline driven by a single `NotificationCreated` event, stored in one `notifications` table (admin and user portals use separate model classes over the same table)
- Triggers: mentions, task assignment, task submitted for review, profile/password changes
- Bell dropdown in every portal, auto-refreshing every 60 s (visibility-gated polling)

### Dashboards & Reports
- Manager dashboard: team capacity bands (<60 % / 60–100 % / 100–120 % / >120 %) with overload alerts, department activity feed
- Burndown chart per project (ideal vs. actual, Chart.js) for managers and admins
- Admin time reports: totals, approved vs. pending, per-user / project / department breakdowns

### Exports
- **CSV**: streamed, chunked, UTF-8 BOM (Excel-safe), filterable
- **PDF**: DomPDF, formatted report with totals, date range required
- Managers are hard-clamped server-side to their own department regardless of request parameters

---

## Tech Stack & Conventions

| Layer | Choice |
| --- | --- |
| Backend | Laravel 12, Form Requests (validation), Policies (authorization), Service classes (business logic) |
| Frontend | Inertia.js + React (plain `.jsx`), Tailwind CSS, lucide-react icons, Chart.js |
| Auth / RBAC | Session auth, plain `role` column: `admin` / `manager` / `employee` |
| Database | MySQL 8 (tests run on in-memory SQLite), Eloquent and the query builder; the few raw expressions (`selectRaw`/`orderByRaw` for aggregates and ordering) must stay portable across MySQL and SQLite |

### Architecture

Feature-first backend structure:

```
app/
├── Core/                     # BaseController, BaseService, middleware (incl. HandleInertiaRequests)
└── Features/
    └── <FeatureName>/
        ├── Controllers/
        ├── Services/
        ├── Requests/
        ├── Policies/
        ├── Models/
        └── routes/web.php    # auto-loaded, may be prefix-mapped
```

Portal-first frontend structure:

```
resources/js/
├── Components/               # shared (comments stack, MentionTextarea, ui/)
└── pages/(portals)/
    ├── admin/                # each portal has its own layout.jsx
    ├── manager/
    └── employee/
```

Conventions worth knowing before touching code:

- **No `<form>` submits for new code** — buttons with `type="button"` + Inertia `router.*` calls (existing modals using `useForm` + `_method: "PUT"` spoofing for multipart are grandfathered)
- Layout imports are **relative** (`../layout`, `../../layout` by depth) so pages always pick up their own portal's shell
- Profile images are served through `ProfileImageController` (`/admin/profile-image/{f}` or `/user/profile-image/{f}` by viewer role) — **not** the public storage disk
- Shared prop `auth.user.profile_image` arrives as a full URL; user objects loaded by controllers carry bare filenames
- Route files with static + dynamic segments: static routes (`/export`, `/pending`) must register **before** `/{user}`-style catch-alls

---

## Getting Started

### Requirements
- PHP 8.2+, Composer
- Node 18+, npm
- MySQL 8

### Setup

```bash
git clone <repo-url> && cd zyncwork
composer install
npm install

cp .env.example .env
php artisan key:generate
# configure DB_* in .env

php artisan migrate --seed
```

### Run (three processes)

```bash
php artisan serve        # app
npm run dev              # Vite
php artisan queue:work   # REQUIRED — notifications are queued and silently stall without it
```

> ⚠️ The queue worker is not optional. Mentions, assignment notifications, and review alerts all dispatch through a queued listener. For local convenience you can set `QUEUE_CONNECTION=sync` instead.

### Test Accounts (seeded)

All seeded accounts use the password `12345678`.

| Role | Email | Notes |
| --- | --- | --- |
| Admin | admin@example.com | Full access, no department |
| Manager | manager@example.com | Manager of the seeded "General" department |
| Employee | emp@example.com | Member of "General" |

The seeder also creates a few sample projects in "General". There is no seeded inactive account; to test suspension, set `is_active = false` on a user. Suspension takes effect on that user's next request.

---

## Deployment Notes

- `npm run build` and test the built assets before shipping — stale builds crash on newer pages
- Run `php artisan queue:work` under a supervisor (systemd / Supervisor) in production
- `APP_DEBUG=false`, configure `MAIL_*` if email is enabled later
- The codebase is Linux-casing-safe (import paths verified against on-disk casing)

## Roadmap (Phase 2)

- Real-time notifications via Laravel Reverb (the `NotificationCreated` event is broadcast-ready — add `ShouldBroadcast`)
- Time-log rejection with reason + employee-visible feedback
- Admin per-user weekly timesheet view
- "Reply to your comment" notifications
- Improved Arabic glyph shaping in PDF exports
- Live "who's working now" timer presence on the manager dashboard
