# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Zyncwork is an internal project-management app with three portals: **admin**, **manager**, and **employee**. It covers projects, tasks, time tracking and approval, comments and mentions, notifications, dashboards, and CSV/PDF time reports.

Stack (from the lock files): PHP ^8.2, Laravel 12.56, inertia-laravel 3.0, @inertiajs/react 3.0, React 19.2 (plain `.jsx`, no TypeScript), Tailwind 4.2, Vite 7, Chart.js, lucide-react, and barryvdh/laravel-dompdf for PDFs. Dev and production use MySQL (`.env`, database `zyncwork_saas_db`). Tests also use MySQL, on a separate database, `zyncwork_saas_db_test`, set in `phpunit.xml` with `force="true"`. `tests/TestCase.php` refuses to boot against any other database, because `RefreshDatabase` drops every table. There is no CI, no Docker, and no JS linter or test runner.

The project was scaffolded from the "Laravel FeatureKit" starter. `.agent/agent.md` (and the longer `DOCS.md`) documents the kit's shared React components, hooks (`@/Hooks`), utilities (`@/Utils`), `GeneralMail`, and Tailwind tokens. Read the relevant section before writing a new UI primitive, hook, helper, or mailable. One of the kit's layout examples there is outdated for this project; see Frontend below.

## Commands

```bash
composer dev                                  # serve + queue:listen + pail + vite together
php artisan queue:work                        # needed if not using composer dev: notifications are queued
composer test                                 # config:clear + full suite (needs MySQL running and an existing zyncwork_saas_db_test)
php artisan test --filter=ProjectTimelineTest # one class (or --filter=test_method_name)
php artisan route:list --path=manager/projects  # check real URLs/names/middleware before touching routes
npm run build                                 # writes public/build/ (tracked in git)
php artisan migrate --seed                    # seeds 3 users: admin@/manager@/emp@example.com, password 12345678
```

The seeder creates no departments. Managers without a `department_id` fail most project and task policies, so set one before testing manager flows.

## Backend architecture

- **All code lives in `app/Features/<Feature>/`.** There is no `app/Http`, `app/Models`, or root `routes/` directory. `app/Core/` holds `BaseController` (JSON response helpers + `AuthorizesRequests`), `BaseService`, `BaseException`, `HandleInertiaRequests` (shared props), and `RoleMiddleware` (the `role:` alias). Controllers extend `BaseController` and services extend `BaseService`. Scaffold with `php artisan make:feature ...` (see `app/Console/Commands`).
- **Routes are auto-discovered by `bootstrap/app.php`.** Never register routes there.
  - If `Features/X/<Role>/routes/web.php` exists, the file gets prefix `/<role>` and name prefix `<role>.` automatically (e.g. `Projects/Manager` → `/manager/projects`, `manager.projects.*`).
  - Otherwise `Features/X/routes/web.php` is mounted at the root, and the file adds its own prefixes (e.g. `TaskManager` declares `prefix('manager/tasks')`).
  - All `api.php` files are empty. The app has no JSON API: it is Inertia pages plus a few JSON endpoints inside web routes.
- **Shared models are imported across features.** The canonical locations: `Auth\Models\User`, `TaskAdmin\Models\Task` (used by all three task features), `TimeLog\Models\TimeLog`, `Projects\Admin\Models\Project`, `Departments\Admin\Models\Department`, `Comment\Models\Comment`. The `Profile/*/Models/User` classes are thin subclasses.
- **Responses:** controllers use Inertia renders plus `redirect()->back()->with('success'|'error', ...)`. The flash message shows as a toast automatically. Validation lives in FormRequests.
- **Side effects go through events:**
  - `event(new ActivityLogged($actor, $action, $description, $category))` writes the audit log.
  - `NotificationCreated` and the queued listener `CreateNotificationRecord` write in-app notifications. Task notifications go through `Notification\Services\TaskNotifierService`.
  - Both listeners are registered in `AppServiceProvider`.

## Auth & authorization

- Session auth only. `users.role` is a plain string: `admin` | `manager` | `employee`. Access is enforced at two layers, and both matter:
  1. Route middleware: `role:admin,manager`. `AdminTaskController` applies its middleware in the constructor.
  2. Policies: `TaskPolicy`, `TimeLogPolicy`, `CommentPolicy`, `ProjectPolicy`, `SettingPolicy`. Every policy's `before()` grants admins everything. Some route groups have no role middleware and rely on the policy alone (e.g. `Projects/Manager` routes).
- Policies are auto-discovered only when they sit in the `Policies/` folder next to the model's `Models/` folder. Otherwise register them in `AppServiceProvider` (as is done for `Project` → `Projects/Manager/Policies/ProjectPolicy`).
- **Manager scoping** means `$user->department_id` equals the resource's department (`project.department_id`, or `task.project.department_id`). `departments.manager_id` is a separate field, used only for deciding who receives notifications. Every manager-facing query and export must clamp to the manager's own department on the server, ignoring any `department_id` sent in the request (see `TimeLogExportController`).
- **Email verification is enforced for every tenant user.** `User` implements `MustVerifyEmail`, and `Core/Middleware/EnsureEmailIsVerified` sits in the `web` group, not on individual routes. An unverified user can reach only the `verification.*` routes and `logout`: pages redirect to the verify screen, and every other request gets a 403. Changing a user's email, by any path, clears `email_verified_at` and sends a new link (in the `User` model's events). Users that existed before Phase 1C were marked verified by a migration.
- **Onboarding** (`Features/Onboarding`): public company signup (`/auth/register`) creates a tenant plus its first admin, who starts unverified. New users join an existing tenant only by invitation. Verified tenant admins invite (`admin.invitations.*`, `InvitationService`); the invitee accepts at `/invitations/{token}`, sets their own password, and is verified on acceptance. Tokens are stored as SHA-256 hashes, work once, and expire after 7 days (`config/onboarding.php`). There is no direct user creation (`admin.users.store` was removed).
- Suspension (`users.is_active=false`, or the user's tenant having `suspended_at` set) is checked at login (`LoginService`). `Core/Middleware/EnsureUserIsActive`, in the web group, also logs such a user out on their next request. It only checks tenant users (the `web` guard) and skips the platform host.
- **Platform admins** (`Features/Platform`, `platform` guard, `platform_admins` table) sign in on `PLATFORM_DOMAIN` only, and TOTP is required (`PlatformTwoFactorService`, `RequirePlatformTwoFactor`). Accounts, capabilities, password resets and 2FA resets are managed only with `php artisan platform:admin`.
  - `PLATFORM_DOMAIN` has no default. Production refuses to boot without it, and elsewhere the console isn't served when it's unset.
  - **Platform sessions are rechecked on every request.** `RequirePlatformTwoFactor` signs an admin out once they are deactivated, their password changes (a fingerprint stored at sign-in), or their 2FA is reset. `EnforceImpersonation` ends a support session once its admin is deactivated or loses `impersonate`. `--reset-password` and `--reset-2fa` also end that admin's support sessions. A session with no fingerprint is signed out, so tests that act as a platform admin use `CrossesPlatformHost::actingAs`, which stores one.
  - During a support session, `EnforceImpersonation` blocks the user-management routes (`USER_MANAGEMENT_ROUTES`) and the own-credential routes by route name. A new route that creates or changes users, roles or credentials must be added there.
  - Five wrong TOTP or recovery codes lock an account's code step for 15 minutes, from any IP. A wrong code ends the pending sign-in.
- **Rotating `APP_KEY` locks every platform admin out.** Their TOTP secrets (`platform_admins.two_factor_secret`) are encrypted with it and become unreadable. After a rotation, run `php artisan platform:admin <email> --reset-2fa` for each admin, and each re-enrolls at next sign-in. Rotation also invalidates all sessions and other encrypted values, so plan it.
- Profile images are stored on the private `local` disk (`storage/app/private`) and served through `Profile/Shared/Controllers/ProfileImageController` (`/admin/profile-image/{f}` or `/user/profile-image/{f}`). Never move uploads to the public disk. Keep the `image|mimes|max` validation on uploads.

## Domain rules (enforced on the server; keep the frontend in sync)

- **Task status** values are `backlog → todo → in_progress → in_review → completed`. The status enum is defined in the migration and as constants on `Task`. Also allowed: `in_review → in_progress` (sent back) and `completed → todo` (reactivate). Only managers/admins may do `in_review→completed`, `in_review→in_progress`, and `completed→todo`. A task can't start while its `depends_on_task_id` task is not completed. Dependency cycles are rejected.
  - **The transition map is duplicated** in `TaskAdmin/Services/TaskService`, `TaskManager/Services/ManagerTaskService`, and `TaskEmployee/Services/EmployeeTaskService`. A change must be made in all three and in `TaskPolicy`.
- **Time logs:** one running timer per user. Starting a new timer auto-stops the running one. `duration_minutes` is computed only on the server. Approval means setting `approved_by`. Approved logs and running timers can't be edited by non-admins, and approved logs can't be deleted by them either. Managers approve only their own department's finished logs and never their own (`TimeLogPolicy`; admins bypass all of these through `before()`). Timer logic exists in both `TimerService` and `TimeLogService`, so check which one the caller uses.
- **Comments:** only the author may edit, and only within 15 minutes (`Comment::isEditable`, `CommentPolicy`). Mentions are stored as `@[Name](userId)`.
- **Notifications:** there is one `notifications` table. `Notification\Admin\Models\Notification` and `Notification\User\Models\Notification` both map to it and are chosen by the recipient's role. Unread count and latest 5 are shared props on every page.

## Frontend

- Pages resolve as `Inertia::render('(portals)/manager/projects/Show')` → `resources/js/pages/(portals)/manager/projects/Show.jsx`, with `@` = `resources/js`. File naming is mixed (`page.jsx`, `Index.jsx`, `show.jsx`), and the render string must match the file's casing exactly. `import.meta.glob` is case-sensitive, so a mismatch breaks the page even on Windows.
- `app.jsx` wraps every page in `Components/Layout/MainLayout` (Toast, `useModal()` modal, spinner). Pages then wrap their own JSX in their portal layout, e.g. `<ManagerLayout>...</ManagerLayout>` imported from `../layout`, `../../layout`, or `@/pages/(portals)/manager/layout`. They do not set `Page.layout`. Don't add Toast or Modal providers yourself.
- Mutations use `router.post/put/patch/delete` from `@inertiajs/react`. Some older modals use `useForm`, and multipart updates use `_method: "PUT"`. Follow the pattern of the surrounding page.
- Use the Tailwind semantic tokens (`bg-primary`, `text-foreground`, `bg-surface`, ...) defined in `resources/css/app.css`, and use existing `Components/ui/*` components.
- Public pages (landing, auth) are Blade views under `resources/views/pages/`. PDF exports use Blade in `resources/views/exports/`.

## Database & migrations

- Never edit or delete an existing migration. Add a new one, and ask before any schema change, destructive migration, `migrate:fresh`, or `migrate:rollback` against a real database.
- The app and the tests both run on MySQL. Prefer Eloquent or the query builder over raw SQL. Some migrations and tests still contain SQLite workarounds from when the tests ran on SQLite; leave them alone.
- Avoid MySQL-only SQL when a portable equivalent exists, even though nothing runs on SQLite any more. `EmployeeTaskService` once had to be rewritten to replace `FIELD()` with a `CASE` expression. If a query really needs MySQL-only SQL, say so in a comment and call it out in the change summary, so nobody adds one unnoticed.
- Never point tests, `migrate:fresh`, or `migrate:rollback` at `zyncwork_saas_db`. Other projects' databases share the same local MySQL server.
- MySQL silently drops the index it auto-created for a foreign key once another index can serve that key. A migration's `down()` that drops such an index must first restore a single-column index (see `enforce_same_tenant_links`).
- Columns like `tasks.status`, `priority`, `impact`, `effort`, `projects.status`, and `time_logs.entry_type` are DB enums. Adding a value needs a migration plus model constants, validation rules, all transition maps, and frontend labels.
- `ProjectFactory` and `UserFactory` exist, but `Project` doesn't use `HasFactory`. Tests build fixtures with `Model::create()`.

## Working rules

- Investigate before changing anything. Read the existing implementation, its callers, the related policy, and the route (`route:list`) first. Prefer the pattern already used in the same feature.
- Make the smallest change that solves the task and preserve existing behavior unless the task is to change it. Change only the files you need to.
- No opportunistic refactors, reformatting, renames or moves (of classes, routes, route names, columns, directories), new abstractions, or new packages without a concrete need.
- Don't change auth, policies, role middleware, `bootstrap/app.php`, `app/Core`, shared props, or the DB schema until you understand everything that depends on them. Call out such changes explicitly.
- **The codebase is not Pint-clean.** Don't run `pint` on the repo or on existing files, because it rewrites whole files. If you do format, limit it to files you created (`vendor/bin/pint path/to/NewFile.php`).
- Behavior changes need tests: feature tests in `tests/Feature/` that use `RefreshDatabase`, hand-built fixtures, `actingAs`, and `Carbon::setTestNow` for date logic. Users created in tests start verified (`TestCase::$usersStartVerified`); onboarding tests set it to `false`. Cover role and department scoping (admin, same-department manager, other-department manager, employee), following the existing tests.
- Never weaken or edit a test just to make it pass. Run the targeted test first, then `composer test`. Report pre-existing failures separately from ones your change introduced, and never claim something works without having run it.
- `public/build/` is committed. After frontend changes, ask before rebuilding or committing build output.
- Ask before destructive or hard-to-reverse actions: data deletion, dropping tables, force pushes, or deleting files you didn't create.

## Known pitfalls

- Inside a route file, static segments (`/export`, `/pending`) must come before `/{param}` routes. Route names must be unique: `TimeLog/routes/web.php` already defines `manager.timesheets.export` twice, and one manager route sits inside the `role:admin` group.
- The README's seeded-account list and its "two notification tables" description don't match the code. Trust the code.
- Pages under `(portal)/` (singular) referenced in `Projects/Admin/Controllers/ProjectController` don't exist.
