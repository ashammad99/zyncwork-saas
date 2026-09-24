# AI Agent — Complete Project Knowledge Base

> **Read this ENTIRELY before generating any code.** This file is the single source of truth for the project's architecture, available tools, patterns, and conventions.

---

## 🏗️ Architecture: Feature-Driven Design (FDD)

All domain logic lives in `app/Features/{FeatureName}/`. There are **no** `app/Http/Controllers`, `app/Models`, or `app/Services` — those directories do NOT exist.

### Feature Folder Structure

```
app/Features/{Name}/
├── Controllers/          # Thin HTTP layer (extends BaseController)
├── Services/             # Business logic brain (extends BaseService)
├── Models/               # Eloquent models
├── Requests/             # FormRequest validation classes
├── Observers/            # Model lifecycle hooks
├── Events/               # Domain events (Dispatchable + SerializesModels)
├── Exceptions/           # Feature errors (extends BaseException)
└── routes/
    ├── web.php           # Auto-discovered, no manual registration
    └── api.php           # Auto-discovered, prefix /api auto-applied
```

### Role-Based Features

For features with different roles (e.g., Dashboard):

```
app/Features/Dashboard/
├── Admin/
│   ├── Controllers/
│   ├── Services/
│   └── routes/web.php   # Auto-prefix: /admin, name prefix: admin.
└── User/
    ├── Controllers/
    ├── Services/
    └── routes/web.php   # Auto-prefix: /user, name prefix: user.
```

### Auto-Route Discovery

`bootstrap/app.php` scans `app/Features/` at boot:
- **Simple features**: routes registered at root (no prefix).
- **Role-based features**: auto-prefixed by role folder name (`/admin`, `/user`, etc.).
- **NEVER** register routes manually in `bootstrap/app.php`.
- **NEVER** create a root `routes/web.php` or `routes/api.php` — they don't exist.

---

## 🧱 Core Layer (`app/Core/`)

### BaseController

```php
namespace App\Core;
use Illuminate\Routing\Controller;
use App\Core\Traits\ApiResponseTrait;

abstract class BaseController extends Controller
{
    use ApiResponseTrait;
}
```

**ALL controllers MUST extend `BaseController`.** This gives them:

### ApiResponseTrait — Available Response Methods

```php
$this->success($data, 'Message', 200);     // { success: true, message, data }
$this->error('Message', $errors, 400);      // { success: false, message, errors }
$this->created($data, 'Created');           // { success: true, data } (201)
$this->noContent();                          // null (204)
```

### BaseService

```php
namespace App\Core;
abstract class BaseService {}
```

**ALL services MUST extend `BaseService`.** Services contain ALL business logic — DB queries, hashing, mailing, API calls, calculations. Controllers NEVER contain business logic.

### BaseException

```php
namespace App\Core\Exceptions;
class BaseException extends Exception {}
```

**ALL feature exceptions MUST extend `BaseException`.**

### Core Middleware (`app/Core/Middleware/`)

**HandleInertiaRequests** — Shares data to every Inertia page:
- `auth` → `{ check, user: { id, name, email, phone, roles, permissions } }`
- `flash` → `{ success, error, warning, info, message }`
- `csrf_token`
- `app` → `{ name, env }`

Registered automatically in the `web` middleware stack via `bootstrap/app.php`.

**RoleMiddleware** — Gate routes by user role:
```php
// Single role
Route::middleware(['auth', 'role:admin'])->group(function () { ... });

// Multiple roles (any match)
Route::middleware(['auth', 'role:admin,moderator'])->group(function () { ... });
```

Supports:
- `$user->role` (single string property)
- `$user->roles` (relationship — collection of objects with `name`)
- Redirects unauthenticated users to login; returns 403 for unauthorized.

Registered as alias `'role'` in `bootstrap/app.php`.

---

## ⚛️ Frontend: Hybrid Strategy

### Blade (SEO/Public Layer)

Used for: **Landing, Documentation, Features, Auth pages.**

| File | Purpose |
|---|---|
| `resources/views/pages/layout.blade.php` | Master layout (header, footer, Vite, OG/Twitter meta, loading spinner) |
| `resources/views/pages/auth/layout.blade.php` | Split-screen auth layout (brand panel + form) |
| `resources/views/layout/header.blade.php` | Sticky nav with auth-aware buttons |
| `resources/views/layout/footer.blade.php` | Footer with social links, sitemap |
| `resources/views/pages/components/ui/loading-spinner.blade.php` | Full-screen spinner on form submit |

**Blade conventions:**
- Extend layout: `@extends('pages.layout')` or `@extends('pages.auth.layout')`
- View path uses dot notation: `view('pages.home.page')`
- Use Tailwind semantic tokens: `bg-primary`, `text-foreground`, `bg-surface`, etc.

### Inertia + React 19 (SPA Portal Layer)

Used for: **Admin and User dashboards, internal tools.**

| File | Purpose |
|---|---|
| `resources/js/app.jsx` | Inertia entry — auto-injects MainLayout on all pages |
| `resources/views/app.blade.php` | Inertia root template (`@inertia`, `@inertiaHead`, `@viteReactRefresh`) |

**Inertia conventions:**
- Page path: `resources/js/pages/(portals)/{role}/{section}/page.jsx`
- Render from controller: `Inertia::render('(portals)/admin/dashboard/page', ['data' => $data])`
- Props passed from controller are received as component props — NO fetch needed.
- **Auto layout injection**: Every page gets `MainLayout` (Toast + Modal + Spinner) automatically unless `Page.layout` is explicitly set.

**Override layout per page:**
```jsx
import AdminLayout from "@/pages/(portals)/admin/layout";
MyPage.layout = (page) => <AdminLayout>{page}</AdminLayout>;
```

### Available Layouts

| Layout | Path | Purpose |
|---|---|---|
| `MainLayout` | `Components/Layout/MainLayout.jsx` | Default wrapper — provides Toast, Modal, LoadingSpinner |
| `AdminLayout` | `pages/(portals)/admin/layout.jsx` | Admin dashboard shell with sidebar |
| `UserLayout` | `pages/(portals)/user/layout.jsx` | User portal shell |

---

## 🎨 React UI Components (`Components/ui/`)

**USE THESE — do NOT recreate similar components.**

### Toast.jsx
Auto-reads Laravel flash messages (`flash.success`, `flash.error`, `flash.info`, `flash.warning`). Auto-dismiss 5s, stacks, slide-in animation. **No manual setup needed — included in MainLayout.**

**Backend:**
```php
return redirect()->back()->with('success', 'Saved!');
return redirect()->back()->with('error', 'Failed.');
```

### Modal.jsx + ModalContext
Context-driven global modal. Accessible from ANY component via `useModal()`.

```jsx
import { useModal } from "@/Contexts/ModalContext";
const { openModal, closeModal } = useModal();

openModal(<MyContent />, {
    size: "md",           // "sm" | "md" | "lg" | "xl" | "full"
    closeOnOverlay: true,
    showCloseButton: true,
});
```

Features: ESC close, overlay click close, body scroll lock, fade + slideUp animations.

### LoadingSpinner.jsx
Full-screen overlay during page transitions. Auto-managed by MainLayout.

### Pagination.jsx
Renders Laravel paginator with Inertia `<Link>` navigation.

```jsx
import Pagination from "@/Components/ui/Pagination";
<Pagination links={paginatedData} />
```

### SeoHead.jsx
Dynamic `<title>`, `<meta description>`, `<meta keywords>` via Inertia `<Head>`.

```jsx
import SeoHead from "@/Components/ui/SeoHead";
<SeoHead title="Dashboard" description="Manage your account" keywords="dashboard" />
```

### BasicEditor.jsx
TipTap rich text editor: Bold, Italic, H1-H3, Bullet/Ordered lists, Text alignment. Outputs inline-styled HTML.

```jsx
import BasicEditor from "@/Components/ui/BasicEditor";
<BasicEditor value={html} onChange={(html) => setContent(html)} />
```

### PromoTemplates.jsx — 5 Variants

```jsx
import { ImagePromo, BannerPromo, CountdownPromo, EmailCapturePromo, GalleryPromo } from "@/Components/ui/PromoTemplates";

openModal(<ImagePromo imageUrl="/img.jpg" title="Sale!" ctaText="Shop" ctaLink="/shop" />, { size: "lg" });
```

| Template | Props | Purpose |
|---|---|---|
| `ImagePromo` | imageUrl, title, description, ctaText, ctaLink, onClose | Full-width promo image + CTA |
| `BannerPromo` | imageUrl, title, subtitle, features[], ctaText, ctaLink | Side-by-side image + feature list |
| `CountdownPromo` | imageUrl, title, endTime, ctaText, ctaLink | Live countdown timer + CTA |
| `EmailCapturePromo` | imageUrl, title, subtitle, placeholder, buttonText, onSubmit | Lead gen form |
| `GalleryPromo` | images[], title, ctaText, ctaLink | Image carousel with nav |

---

## 🪝 Custom React Hooks (`@/Hooks`)

Import from `@/Hooks`. **USE THESE instead of reimplementing auth/error/flash logic.**

### Authentication
```jsx
import { useAuth, useUser, useIsAuthenticated, useIsGuest, useHasRole, useHasPermission } from "@/Hooks";

const user = useUser();                           // User object or null
const isAdmin = useHasRole("admin");              // boolean
const isAdminOrMod = useHasRole(["admin", "mod"]); // boolean
const canEdit = useHasPermission("posts.edit");    // boolean
const loggedIn = useIsAuthenticated();             // boolean
```

### Flash Messages
```jsx
import { useFlash, useFlashSuccess, useFlashError, useFlashWarning, useFlashInfo } from "@/Hooks";

const flash = useFlash();           // entire flash object
const success = useFlashSuccess();  // flash.success string or null
```

### Validation Errors
```jsx
import { useErrors, useError, useHasErrors, useFieldErrors, useFirstError } from "@/Hooks";

const errors = useErrors();             // { field: ['msg'] }
const emailErr = useError("email");     // first error string or null
const hasAny = useHasErrors();          // boolean
const first = useFirstError();          // first error across all fields
```

### Navigation & Config
```jsx
import { useSharedProps, useRoute, useIsRoute, useAppConfig, useCsrfToken } from "@/Hooks";

const route = useRoute();       // { current, component, props, version }
const isUsers = useIsRoute("(portals)/admin/users");  // boolean
const csrf = useCsrfToken();
```

---

## 🧰 JavaScript Utilities (`@/Utils`)

Import from `@/Utils`. **USE THESE instead of writing similar logic.**

```jsx
import { compressImage, slugify, formatDate, debounce, formatCurrency, copyToClipboard } from '@/Utils';
import storage from '@/Utils';
```

### imageCompressor.js
| Function | Signature | Description |
|---|---|---|
| `compressImage` | `(file, { maxWidth?, maxHeight?, quality?, type? })` → `Promise<File>` | Compress/resize single image |
| `compressImages` | `(files, opts)` → `Promise<File[]>` | Batch compress |
| `generateResponsiveImages` | `(file, { smallWidth?, mediumWidth?, largeWidth?, quality? })` → `Promise<{small, medium, large}>` | 3 responsive sizes |
| `getImageDimensions` | `(file)` → `Promise<{width, height}>` | Get image dimensions |
| `formatFileSize` | `(bytes)` → `string` | Human-readable file size |

### toast.js
| Function | Description |
|---|---|
| `TOAST_TYPES` | `{ SUCCESS, ERROR, WARNING, INFO }` |
| `formatValidationErrors(errors)` | Flatten Inertia errors → comma string |
| `hasErrors(errors, field?)` | Check if errors exist |
| `getError(errors, field)` | First error for a field |

### storage.js (default export as object)
| Function | Description |
|---|---|
| `storage.set(key, value)` | Store (auto JSON stringify) |
| `storage.get(key, default?)` | Retrieve (auto JSON parse) |
| `storage.remove(key)` | Delete key |
| `storage.clear()` | Clear all |
| `storage.setWithExpiry(key, value, ttlSeconds)` | Store with TTL |
| `storage.getWithExpiry(key, default?)` | Get if not expired |
| `storage.has(key)` | Check existence |
| `storage.keys()` | All keys |

### string.js
| Function | Description |
|---|---|
| `slugify(text)` | `"Hello World"` → `"hello-world"` |
| `truncate(text, length=100)` | Truncate with `...` |
| `capitalize(text)` | Capitalize each word |
| `randomString(length=10)` | Alphanumeric random string |

### date.js
| Function | Description |
|---|---|
| `formatDate(date, locale?)` | `"January 15, 2026"` |
| `formatDateTime(date, locale?)` | `"January 15, 2026, 02:30 PM"` |
| `timeAgo(date)` | `"2 hours ago"`, `"just now"` |

### number.js
| Function | Description |
|---|---|
| `formatNumber(value, locale?, decimals?)` | `1234567` → `"1,234,567"` |
| `formatCurrency(value, currency?, locale?)` | Supports names (`dollar`, `euro`, `taka`, `yen`, etc.) and ISO codes |
| `formatPercent(value, decimals?)` | `0.15` → `"15%"` |
| `formatBytes(bytes, decimals?)` | `1536` → `"1.50 KB"` |
| `compactNumber(value)` | `1234567` → `"1.2M"` |

### clipboard.js
| Function | Description |
|---|---|
| `copyToClipboard(text)` | Copy with Clipboard API + `execCommand` fallback |
| `copyWithToast(text, toastFn?)` | Copy + show notification |
| `readFromClipboard()` | Read clipboard text |

### performance.js
| Function | Description |
|---|---|
| `debounce(func, wait=300)` | Delay until idle. Use for: search, validation |
| `throttle(func, limit=100)` | Execute at most once per interval. Use for: scroll, resize |

### validation.js
| Function | Description |
|---|---|
| `validatePhone(phone)` | Bangladeshi mobile: `01[3-9]XXXXXXXX` |
| `validateEmail(email)` | Basic email format |
| `validatePassword(password)` | Strict: 8-15 chars, upper, lower, number, symbol |
| `passwordRequirements` | Array of `{label, regex}` for password strength UI |

### webVitals.js
| Function | Description |
|---|---|
| `initWebVitals({ sendToServer?, logToConsole?, onMetric? })` | Monitor LCP, FID, CLS, FCP, TTFB |
| `getWebVitals()` | Snapshot of all current vitals |

---

## 📬 Mailing System

**ALWAYS use `GeneralMail` — NEVER create new Mailable classes.**

```php
use App\Mail\GeneralMail;
use Illuminate\Support\Facades\Mail;

Mail::to($user)->queue(new GeneralMail(
    mailSubject: 'Subject Line',
    contentView: 'emails.{feature}.{template}-body',
    data: [
        'title'      => 'Heading',
        'body'       => 'Paragraph text',
        'actionText' => 'Button Label',
        'actionUrl'  => 'https://example.com/action',
    ]
));
```

- `GeneralMail` is **queued by default** (`implements ShouldQueue`).
- All emails wrap in `emails/layout.blade.php` (logo + app name header, white card, copyright footer).
- Create email body views at `resources/views/emails/{feature}/{name}-body.blade.php`.
- Available data variables in body views: `$title`, `$body`, `$actionText`, `$actionUrl` (and any custom keys).

---

## ⚙️ Artisan Scaffolding Commands

**USE THESE to generate files — they wire everything to the architecture automatically.**

| Command | Creates |
|---|---|
| `php artisan make:feature {Name}` | Full scaffold: Controllers, Services, Models, Requests, Observers, Events, Exceptions, routes |
| `php artisan make:feature {Name} --roles=Admin,User` | Role-based feature with sub-folders per role |
| `php artisan make:feature:controller {Feature} {Name}` | Controller extending BaseController |
| `php artisan make:feature:service {Feature} {Name}` | Service extending BaseService |
| `php artisan make:feature:request {Feature} {Name}` | FormRequest with authorize + rules |
| `php artisan make:feature:event {Feature} {Name}` | Event with Dispatchable + SerializesModels |
| `php artisan make:feature:exception {Feature} {Name}` | Exception extending BaseException |
| `php artisan make:feature:observer {Feature} {Name}` | Observer with creating/created/updating/updated/deleting/deleted |

**Role-based paths:** `php artisan make:feature:service Dashboard/Admin Report` → `app/Features/Dashboard/Admin/Services/ReportService.php`

---

## 🎨 Tailwind CSS v4 Design System

### Available Semantic Tokens

Use these CSS classes everywhere — **NEVER use arbitrary colors.**

| Token | Class Examples | Color |
|---|---|---|
| **primary** | `bg-primary`, `text-primary`, `border-primary` | OKLCH color space |
| **primary-foreground** | `text-primary-foreground` | White |
| **primary-surface** | `bg-primary-surface` | Light tint |
| **secondary** | `bg-secondary`, `text-secondary` | OKLCH |
| **surface** | `bg-surface` | Near-white |
| **surface-foreground** | `text-surface-foreground` | Dark text |
| **surface-muted** | `bg-surface-muted` | Light gray |
| **error** | `bg-error`, `text-error` | Red |
| **success** | `bg-success`, `text-success` | Green |
| **border** | `border-border` | Light gray |
| **ring** | `ring-ring` | Primary with alpha |

### Typography

Font: `"Instrument Sans"` (defined in `--font-sans`).

---

## 📂 Existing Features (Reference)

### Auth Feature (`app/Features/Auth/`)
- **Controllers:** LoginController, RegisterController, ForgotPasswordController
- **Services:** LoginService (attempt + session regen), RegisterService (create + auto-login), ForgotPasswordService (token gen + email + reset)
- **Models:** User (fillable: name, email, phone, password)
- **Requests:** LoginRequest, RegisterRequest
- **Routes:** 8 routes under `/auth` prefix (login, register, forgot-password, reset-password)

### Landing Feature (`app/Features/Landing/`)
- **Controllers:** LandingController (index, docs, features)
- **Routes:** `/` (home), `/documentation`, `/features`

---

## 🔔 17. Unified Notification System

FeatureKit uses a **Event-Driven Unified Notification System**.

### `NotificationCreated` Event
A single global event (`App\Features\Notification\Events\NotificationCreated`) triggers notifications across the app.

```php
use App\Features\Notification\Events\NotificationCreated;

event(new NotificationCreated(
    user: $user, 
    category: 'Security', 
    title: 'Profile Updated', 
    message: 'Your profile has been updated.'
));
```

### `CreateNotificationRecord` Listener
- **Location**: `App\Features\Notification\Listeners\CreateNotificationRecord`
- **Queueable**: Implements `ShouldQueue` for background processing.
- **Role-Aware**: Automatically targets `AdminNotification` or `UserNotification`.

---

## 🏗️ 18. Advanced Feature Patterns

### Independent User Models
Use feature-specific models (e.g., `App\Features\Profile\Admin\Models\User`) to decouple role logic from the base `User` model.

### Secure Private Storage
Assets like profile images are stored in `storage/app/private/` and delivered via modular feature controllers (e.g., `ProfileImageController`).

---

## 📝 Naming & Path Conventions

| Thing | Convention | Example |
|---|---|---|
| PHP Classes | PascalCase | `RegisterService`, `LoginController` |
| PHP Methods | camelCase | `sendResetLink()`, `register()` |
| Feature Folders | PascalCase | `app/Features/Auth/`, `app/Features/Dashboard/` |
| Role Folders | PascalCase | `Admin/`, `User/` |
| JS Components | PascalCase files | `Toast.jsx`, `MainLayout.jsx` |
| JS Utilities | camelCase files | `toast.js`, `imageCompressor.js` |
| JS Hooks | camelCase with `use` prefix | `useAuth()`, `useHasRole()` |
| Blade views | kebab-case / dot notation | `pages.home.page`, `emails.auth.forgot-password-body` |
| Routes names | dot-separated | `auth.login.index`, `admin.dashboard` |

---

## 🔧 Dev Environment

| Command | What It Does |
|---|---|
| `composer setup` | Install deps + .env + key + migrate + npm install + build |
| `composer dev` | Starts 4 concurrent processes: serve, queue:listen, pail, vite dev |
| `composer test` | Clear config + run PHPUnit |

---

## ⚠️ ABSOLUTE RULES — NEVER BREAK THESE

1. **NEVER** put controllers/models/services in `app/Http/` or `app/Models/` — use `app/Features/`
2. **NEVER** register routes in `bootstrap/app.php` — auto-discovery handles it
3. **NEVER** create `routes/web.php` or `routes/api.php` in the project root
4. **NEVER** create new Mailable classes — use `GeneralMail`
5. **NEVER** put business logic in controllers — it belongs in Services
6. **NEVER** use arbitrary CSS colors — use the semantic design tokens
7. **NEVER** recreate utilities that already exist (check `@/Utils` and `@/Hooks` first)
8. **NEVER** manually set up Toast/Modal/Spinner — `MainLayout` provides them automatically
9. **ALWAYS** extend `BaseController` for controllers
10. **ALWAYS** extend `BaseService` for services
11. **ALWAYS** extend `BaseException` for exceptions
12. **ALWAYS** use `NotificationCreated` event for triggering user-facing notifications.
13. **ALWAYS** use constructor injection for services in controllers
14. **ALWAYS** use `(portals)/` prefix in Inertia render path for portal pages
15. **ALWAYS** use `middleware('role:admin')` for role-gating.

---

Developed and Maintained by [Rifatxtra](https://rifatxtra.com).
MIT Licensed. Open for everyone to scale.
