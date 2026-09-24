# 🚀 Laravel FeatureKit v2.1.1 — Complete Technical Documentation

Welcome to the definitive guide for **rifatxtra/laravel-featurekit**. This document covers every system, pattern, utility, and command in the project — no detail omitted.

---

## Table of Contents

1. [Architecture: Feature-Driven Design](#-1-architecture-feature-driven-design-fdd)
2. [Core Layer (`app/Core/`)](#-2-core-layer)
3. [Routing Engine](#-3-automatic-route-discovery-engine)
4. [Frontend: Hybrid Strategy](#-4-frontend-hybrid-strategy)
5. [React UI Component Kit](#-5-react-ui-component-kit)
6. [Context System (Global State)](#-6-context-system)
7. [Custom React Hooks Library](#-7-custom-react-hooks-library)
8. [JavaScript Utility Suite](#-8-javascript-utility-suite)
9. [Modern Mailing System](#-9-modern-mailing-system)
10. [Auth Feature (Reference Implementation)](#-10-auth-feature-reference-implementation)
11. [Landing Feature](#-11-landing-feature)
12. [Command-Line Scaffolding](#-12-command-line-scaffolding)
13. [Tailwind CSS v4 Design System](#-13-tailwind-css-v4-design-system)
14. [Dev Environment & Scripts](#-14-dev-environment--scripts)
15. [Database & Migrations](#-15-database--migrations)
16. [Quick Reference Table](#-16-quick-reference-table)
17. [Unified Notification System](#-17-unified-notification-system)
18. [Advanced Feature Patterns (Independent Models & Private Storage)](#-18-advanced-feature-patterns)
19. [Activity Logs System](#-19-activity-logs-system)
20. [Core Administrative Hubs](#-20-core-administrative-hubs)

---

## 🏛️ 1. Architecture: Feature-Driven Design (FDD)

Traditional Laravel projects scatter logic across `app/Http/Controllers`, `app/Models`, `app/Services`, etc. As the project grows, finding related files becomes a nightmare. FeatureKit solves this with **Feature-Driven Design**.

### How It Works

Every business domain lives in `app/Features/{Name}/` as a self-contained unit:

```
app/Features/Payment/
├── Controllers/          # HTTP layer (thin, delegates to Services)
├── Services/             # Business logic (the "brain")
├── Models/               # Feature-specific Eloquent models
├── Requests/             # Form Request validation classes
├── Observers/            # Model lifecycle hooks
├── Events/               # Domain events
├── Exceptions/           # Feature-specific exceptions
└── routes/
    ├── web.php           # Auto-discovered web routes
    └── api.php           # Auto-discovered API routes
```

### Simple vs. Role-Based Features

**Simple Feature** (e.g., Auth, Landing, Notification):

```
app/Features/Auth/
├── Controllers/
├── Services/
├── Models/
└── routes/web.php       # Route prefix: none (registered at root)
```

**Role-Based Feature** (e.g., Dashboard with Admin & User roles):

```
app/Features/Dashboard/
├── Admin/
│   ├── Controllers/
│   ├── Services/
│   └── routes/web.php   # Route prefix: /admin, name prefix: admin.
└── User/
    ├── Controllers/
    ├── Services/
    └── routes/web.php   # Route prefix: /user, name prefix: user.
```

### Thin Controller + Service Pattern

Controllers have **one job**: translate HTTP requests. All business logic lives in Services.

```php
// ❌ BAD: Fat controller with business logic
public function store(Request $request) {
    $user = User::create([...]);
    Mail::to($user)->send(...);
    event(new UserRegistered($user));
    return redirect('/dashboard');
}

// ✅ GOOD: Thin controller delegating to a service
public function store(RegisterRequest $request, RegisterService $service) {
    $service->register($request->validated());
    return redirect()->intended(route('home.index'));
}
```

```php
// app/Features/Auth/Services/RegisterService.php
namespace App\Features\Auth\Services;

use App\Core\BaseService;
use App\Features\Auth\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterService extends BaseService
{
    public function register(array $data): User
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        return $user;
    }
}
```

### Key Benefit: Feature Isolation

Deleting a feature is as easy as deleting its folder. No hunting for scattered files across the project.

---

## 🧱 2. Core Layer

The `app/Core/` directory provides standardized base classes that power all features.

### `BaseController`

All feature controllers extend this. It provides the `ApiResponseTrait` automatically.

```php
namespace App\Core;

use Illuminate\Routing\Controller;
use App\Core\Traits\ApiResponseTrait;

abstract class BaseController extends Controller
{
    use ApiResponseTrait;
}
```

### `ApiResponseTrait`

Standardized JSON responses for consistent API output:

```php
// Available methods:
$this->success($data, 'Fetched successfully');
// → { "success": true, "message": "Fetched successfully", "data": [...] } (200)

$this->error('Something went wrong', $errors, 400);
// → { "success": false, "message": "Something went wrong", "errors": [...] } (400)

$this->created($data, 'Created successfully');
// → { "success": true, "message": "Created successfully", "data": [...] } (201)

$this->noContent();
// → null (204)
```

### `BaseService`

Abstract base for all domain services. Extend this for any business logic class:

```php
namespace App\Core;

abstract class BaseService
{
    //
}
```

### `BaseException`

Custom exception base for feature-specific exceptions:

```php
namespace App\Core\Exceptions;

use Exception;

class BaseException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

### Core Middleware (`app/Core/Middleware/`)

Two middleware classes are registered in `bootstrap/app.php`:

#### `HandleInertiaRequests`

Appended to the `web` middleware stack. Shares data to **every Inertia page** automatically:

| Shared Key   | Type           | Powers                                               |
| :----------- | :------------- | :--------------------------------------------------- |
| `auth.check` | `boolean`      | `useIsAuthenticated()`, `useIsGuest()`               |
| `auth.user`  | `object\|null` | `useUser()`, `useHasRole()`, `useHasPermission()`    |
| `flash`      | `object`       | `useFlash()`, `useFlashSuccess()`, `Toast` component |
| `csrf_token` | `string`       | `useCsrfToken()`                                     |
| `app`        | `object`       | `useAppConfig()`                                     |

The `auth.user` object includes: `id`, `name`, `email`, `phone`, `email_verified_at`, `created_at`, `roles`, `permissions`. Roles/permissions are auto-included if the relationships exist on the User model.

#### `RoleMiddleware`

Registered as alias `'role'`. Gates routes by the authenticated user's role:

```php
// Single role
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// Multiple roles (any match grants access)
Route::middleware(['auth', 'role:admin,moderator'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index']);
});
```

Supports three role formats:

- **String property**: `$user->role` (e.g., `"admin"`)
- **Relationship array**: `$user->roles` as collection of objects with `name` key
- **Plain array**: `$user->roles` as `["admin", "editor"]`

Unauthenticated users are redirected to login. Unauthorized users receive a `403` response.

---

## 🗺️ 3. Automatic Route Discovery Engine

**Location:** `bootstrap/app.php`

The routing engine scans `app/Features/` at boot and automatically registers all routes — no manual `Route::group()` needed.

### How Discovery Works

1. The engine iterates all directories in `app/Features/`.
2. For each feature, it checks subdirectories for **role-based routing** (e.g., `Dashboard/Admin/routes/web.php`).
3. Role-based routes get **automatic URL prefix and name prefix** based on the role folder name:
    - `Dashboard/Admin/routes/web.php` → prefix `/admin`, names `admin.*`
    - `Dashboard/Admin/routes/api.php` → prefix `/api/admin`, names `api.admin.*`
4. If no role subdirectories are found, it registers as a **simple feature** (no prefix).

### Discovery Algorithm

```php
foreach (File::directories($featuresPath) as $featurePath) {
    $hasRoleRoutes = false;

    // Check for role-based sub-features
    foreach (File::directories($featurePath) as $subPath) {
        $role = strtolower(basename($subPath)); // e.g., "admin"

        if (File::exists("$subPath/routes/web.php")) {
            Route::middleware('web')
                ->prefix($role)              // /admin
                ->name("$role.")             // admin.
                ->group("$subPath/routes/web.php");
            $hasRoleRoutes = true;
        }

        if (File::exists("$subPath/routes/api.php")) {
            Route::middleware('api')
                ->prefix("api/$role")        // /api/admin
                ->name("api.$role.")         // api.admin.
                ->group("$subPath/routes/api.php");
            $hasRoleRoutes = true;
        }
    }

    // Simple feature fallback
    if (!$hasRoleRoutes) {
        if (File::exists("$featurePath/routes/web.php")) {
            Route::middleware('web')->group("$featurePath/routes/web.php");
        }
        if (File::exists("$featurePath/routes/api.php")) {
            Route::middleware('api')->prefix('api')->group("$featurePath/routes/api.php");
        }
    }
}
```

### Adding Middleware

The bootstrap file includes a prepared middleware hook:

```php
->withMiddleware(function (Middleware $middleware) {
    // Add middleware aliases here e.g:
    // $middleware->alias(['role' => \App\Core\Middleware\RoleMiddleware::class]);
})
```

---

## ⚛️ 4. Frontend: Hybrid Strategy

FeatureKit uses a **dual rendering strategy** for optimal performance and interactivity.

### SEO Layer (Blade + Tailwind v4)

Used for: **Landing page, Documentation page, Features page, Auth forms.**

- **Master Layout:** `resources/views/pages/layout.blade.php` — includes header, footer, Vite assets, SEO meta tags (OG + Twitter Cards), and a loading spinner.
- **Auth Layout:** `resources/views/pages/auth/layout.blade.php` — split-screen design with brand panel and form area.
- **Partials:** Header (`layout/header.blade.php`) with responsive nav and auth-aware buttons; Footer (`layout/footer.blade.php`) with social links and sitemap.

```blade
@extends('pages.layout')

@section('title', 'Welcome to FeatureKit')

@section('content')
    <div class="bg-surface text-foreground p-8 rounded-lg shadow-sm">
        <h1 class="text-primary text-4xl font-bold">Build Faster.</h1>
        <p class="mt-4 opacity-80 text-lg">Your next-gen SaaS starts here.</p>
    </div>
@endsection
```

### SPA Layer (Inertia.js v2 + React 19)

Used for: **Admin and User Portals (dashboards, internal tools).**

- **Entry Point:** `resources/js/app.jsx` — Inertia app with automatic layout injection.
- **Inertia Root Template:** `resources/views/app.blade.php` — includes `@inertia`, `@inertiaHead`, `@viteReactRefresh`.
- **Portal Layout Convention:** Next.js App Router pattern: `resources/js/pages/(portals)/admin/layout.jsx`.
- **Page Convention:** `resources/js/pages/(portals)/admin/dashboard/page.jsx`.

#### Automatic Layout Injection

The `app.jsx` resolver checks if a page component has a `.layout` property. If not, it **automatically wraps the page in `MainLayout`**:

```javascript
// resources/js/app.jsx
resolve: async (name) => {
    const pages = import.meta.glob("./pages/**/*.jsx");
    const page = await pages[`./pages/${name}.jsx`]();

    if (page.default.layout === undefined) {
        const MainLayout = await import("./Components/Layout/MainLayout");
        page.default.layout = (p) => (
            <MainLayout.default>{p}</MainLayout.default>
        );
    }

    return page;
},
```

This means **every page gets Toast, Modal, and LoadingSpinner for free** without any configuration.

#### Custom Layout Override

To use a different layout (e.g., `AdminLayout`), set the `.layout` property:

```javascript
import AdminLayout from "@/pages/(portals)/admin/layout";

const SettingsPage = ({ settings }) => {
    return <div>...</div>;
};

SettingsPage.layout = (page) => <AdminLayout>{page}</AdminLayout>;
export default SettingsPage;
```

#### Available Layouts

| Layout        | File                               | Purpose                                              |
| :------------ | :--------------------------------- | :--------------------------------------------------- |
| `MainLayout`  | `Components/Layout/MainLayout.jsx` | Default persistent wrapper (Toast + Modal + Spinner) |
| `AdminLayout` | `pages/(portals)/admin/layout.jsx` | Admin dashboard shell with sidebar navigation        |
| `UserLayout`  | `pages/(portals)/user/layout.jsx`  | User portal shell with user-facing navigation        |

---

## 🎨 5. React UI Component Kit

All components live in `resources/js/Components/ui/` and are production-ready.

### `Toast.jsx` — Flash Notification System

Automatically reads Laravel flash messages (`flash.success`, `flash.error`, `flash.info`, `flash.warning`) from Inertia shared data and displays styled notifications.

- Auto-dismisses after 5 seconds.
- Slide-in animation with close button.
- Stacks multiple toasts vertically.

#### Flash Key Mapping & Colors

| Flash Session Key | UI Badge / Border Color | Icon Used | Purpose |
| :--- | :--- | :--- | :--- |
| `success` | **Green** (`bg-green-50`, `text-green-800`) | Checkmark Circle (Green) | Successful operations (e.g. Profile Saved). |
| `error` | **Red** (`bg-red-50`, `text-red-800`) | X Circle (Red) | Fatal exceptions, validation halts. |
| `warning` | **Yellow** (`bg-yellow-50`, `text-yellow-800`) | Exclamation Triangle (Yellow) | Cautionary warnings or required actions. |
| `info` | **Blue** (`bg-blue-50`, `text-blue-800`) | Information Circle (Blue) | General instructional alerts. |

**Backend Usage:**

```php
return redirect()->back()->with('success', 'Profile updated!');
return redirect()->back()->with('error', 'Something went wrong.');
```

### `Modal.jsx` — Global Modal System

A context-driven modal controlled via `ModalContext`. Features:

- **ESC key** closes the modal.
- **Overlay click** closes by default (configurable).
- **Body scroll lock** when open.
- Fade-in + slide-up animations.

#### Modal Size Presets

| Size Prop | CSS Max Width | Typical Use Case |
| :--- | :--- | :--- |
| `sm` | `max-w-md` (28rem) | Simple confirmation dialogs, quick edits. |
| `md` | `max-w-lg` (32rem) | Standard forms, logins, standard promo cards. |
| `lg` | `max-w-2xl` (42rem) | Multi-column forms, detailed settings views. |
| `xl` | `max-w-4xl` (56rem) | Complex tables, document previews, dashboards. |
| `full` | `w-full h-full` | Immersive media viewers, full-screen wizards. |

```javascript
import { useModal } from "@/Contexts/ModalContext";

const { openModal, closeModal } = useModal();

openModal(
    <div>
        <h2>Confirm Action</h2>
        <p>Are you sure you want to proceed?</p>
        <button
            onClick={() => {
                performAction();
                closeModal();
            }}
        >
            Confirm
        </button>
    </div>,
    { size: "lg", closeOnOverlay: false },
);
```

### `LoadingSpinner.jsx` — Global Loading Overlay

Full-screen spinner displayed during Inertia page transitions. Automatically managed by `MainLayout` via Inertia router events (`start` / `finish`).

### `Pagination.jsx` — Laravel Pagination Links

Renders Laravel's paginated response with responsive Previous/Next buttons and numbered page links. Works with Inertia `<Link>` components for SPA-style navigation.

```javascript
import Pagination from "@/Components/ui/Pagination";

<Pagination links={paginatedData} />;
```

### `SeoHead.jsx` — Dynamic SEO Meta Tags

Sets page `<title>`, `<meta description>`, and `<meta keywords>` via Inertia's `<Head>` component.

```javascript
import SeoHead from "@/Components/ui/SeoHead";

<SeoHead
    title="Dashboard"
    description="Manage your account and settings"
    keywords="dashboard, settings, profile"
/>;
```

### `BasicEditor.jsx` — Rich Text Editor (TipTap)

A toolbar-equipped rich text editor built on TipTap with:

- **Bold**, **Italic** formatting.
- **H1**, **H2**, **H3** headings with inline font-size styles.
- **Bullet List** and **Ordered List**.
- **Text Alignment**: Left, Center, Right, Justify.
- Inline styles applied directly to HTML output for email/CMS compatibility.
- Custom paragraph spacing for clean rendered output.

```javascript
import BasicEditor from "@/Components/ui/BasicEditor";

<BasicEditor value={htmlContent} onChange={(html) => setContent(html)} />;
```

### `PromoTemplates.jsx` — 5 Pre-Built Promotional Modal Templates

Ready-to-use marketing components that integrate with the ModalContext:

| Template            | Description                                                          |
| :------------------ | :------------------------------------------------------------------- |
| `ImagePromo`        | Full-width promotional image with title, description, and CTA button |
| `BannerPromo`       | Side-by-side image + text with feature checklist and CTA             |
| `CountdownPromo`    | Time-limited offer with live countdown timer                         |
| `EmailCapturePromo` | Lead generation form with email input and submit handler             |
| `GalleryPromo`      | Image carousel with prev/next navigation and counter                 |

```javascript
import { ImagePromo } from "@/Components/ui/PromoTemplates";

openModal(
    <ImagePromo
        imageUrl="/promo-banner.jpg"
        title="Summer Sale!"
        description="Get 50% off all courses"
        ctaText="Shop Now"
        ctaLink="/shop"
    />,
    { size: "lg" },
);
```

---

## 🧠 6. Context System

### `ModalContext.jsx` — Global Modal State

Provides `openModal()` and `closeModal()` functions to any component without prop-drilling.

```javascript
import { ModalProvider, useModal } from "@/Contexts/ModalContext";

// In your layout:
<ModalProvider>
    {children}
    <Modal />
</ModalProvider>;

// In any component:
const { openModal, closeModal } = useModal();

openModal(<MyContent />, {
    size: "md", // "sm" | "md" | "lg" | "xl" | "full"
    closeOnOverlay: true, // click backdrop to close?
    showCloseButton: true, // show X button?
});
```

---

## 🪝 7. Custom React Hooks Library

**Location:** `resources/js/Hooks/useInertia.js` — barrel exported via `@/Hooks`.

All hooks are built on Inertia's `usePage()` and provide clean access to shared server data.

### Authentication Hooks

| Hook                      | Returns        | Description                                                             |
| :------------------------ | :------------- | :---------------------------------------------------------------------- |
| `useAuth()`               | `auth` object  | Full auth data from server                                              |
| `useUser()`               | `User \| null` | Current authenticated user                                              |
| `useIsAuthenticated()`    | `boolean`      | Whether user is logged in                                               |
| `useIsGuest()`            | `boolean`      | Whether user is a guest                                                 |
| `useHasRole(roles)`       | `boolean`      | Check user role(s) — supports string, array, and `{name}` objects       |
| `useHasPermission(perms)` | `boolean`      | Check user permission(s) — supports string, array, and `{name}` objects |

```javascript
import { useUser, useHasRole } from "@/Hooks";

const user = useUser();
const isAdmin = useHasRole("admin");
const isAdminOrMod = useHasRole(["admin", "moderator"]);
```

### Flash Message Hooks

| Hook                   | Returns          | Description              |
| :--------------------- | :--------------- | :----------------------- |
| `useFlash()`           | `flash` object   | All flash data           |
| `useFlashMessage(key)` | `string \| null` | Flash message by key     |
| `useFlashSuccess()`    | `string \| null` | `flash.success` shortcut |
| `useFlashError()`      | `string \| null` | `flash.error` shortcut   |
| `useFlashWarning()`    | `string \| null` | `flash.warning` shortcut |
| `useFlashInfo()`       | `string \| null` | `flash.info` shortcut    |

### Validation Error Hooks

| Hook                    | Returns          | Description                         |
| :---------------------- | :--------------- | :---------------------------------- |
| `useErrors()`           | `object`         | All validation errors               |
| `useError(field)`       | `string \| null` | First error for a specific field    |
| `useHasErrors()`        | `boolean`        | Whether any errors exist            |
| `useFieldErrors(field)` | `string[]`       | All errors for a specific field     |
| `useFirstError(field?)` | `string \| null` | First error globally or for a field |

### Navigation & Config Hooks

| Hook                | Returns                                  | Description                                  |
| :------------------ | :--------------------------------------- | :------------------------------------------- |
| `useSharedProps()`  | `object`                                 | All shared Inertia props                     |
| `useRoute()`        | `{ current, component, props, version }` | Current route info                           |
| `useIsRoute(names)` | `boolean`                                | Check if current route matches given name(s) |
| `useAppConfig()`    | `object`                                 | Shared app config from server                |
| `useCsrfToken()`    | `string`                                 | CSRF token                                   |

---

## 🧰 8. JavaScript Utility Suite

**Location:** `resources/js/Utils/` — barrel exported via `@/Utils`.

```javascript
import {
    compressImage,
    slugify,
    formatDate,
    debounce,
    formatCurrency,
} from "@/Utils";
import storage from "@/Utils";
```

### 8.1 Image Compressor (`imageCompressor.js`)

Client-side image optimization before upload.

| Function                               | Description                                                                                                                |
| :------------------------------------- | :------------------------------------------------------------------------------------------------------------------------- |
| `compressImage(file, opts)`            | Compress and resize a single image. Options: `maxWidth` (1920), `maxHeight` (1080), `quality` (0.8), `type` ('image/jpeg') |
| `compressImages(files, opts)`          | Batch compress an array/FileList of images                                                                                 |
| `generateResponsiveImages(file, opts)` | Generate 3 sizes: small (640px), medium (1280px), large (1920px)                                                           |
| `getImageDimensions(file)`             | Get `{width, height}` from a File                                                                                          |
| `formatFileSize(bytes)`                | Convert bytes to human-readable format                                                                                     |

```javascript
const compressed = await compressImage(file, { maxWidth: 1200, quality: 0.7 });
const { small, medium, large } = await generateResponsiveImages(file);
```

### 8.2 Toast Utilities (`toast.js`)

Helpers for formatting Laravel validation errors.

| Function                         | Description                                             |
| :------------------------------- | :------------------------------------------------------ |
| `TOAST_TYPES`                    | Constants: `SUCCESS`, `ERROR`, `WARNING`, `INFO`        |
| `formatValidationErrors(errors)` | Flatten Inertia errors into a comma-separated string    |
| `hasErrors(errors, field?)`      | Check if errors exist (optionally for a specific field) |
| `getError(errors, field)`        | Get first error message for a field                     |

### 8.3 Storage (`storage.js`)

Type-safe localStorage wrapper with JSON auto-serialization and TTL support.

| Function                                        | Description                            |
| :---------------------------------------------- | :------------------------------------- |
| `storage.set(key, value)`                       | Store value (auto-stringifies objects) |
| `storage.get(key, default?)`                    | Retrieve value (auto-parses JSON)      |
| `storage.remove(key)`                           | Remove a key                           |
| `storage.clear()`                               | Clear all localStorage                 |
| `storage.setWithExpiry(key, value, ttlSeconds)` | Store with automatic expiration        |
| `storage.getWithExpiry(key, default?)`          | Retrieve only if not expired           |
| `storage.has(key)`                              | Check if key exists                    |
| `storage.keys()`                                | Get all storage keys                   |

```javascript
import storage from "@/Utils";

storage.set("user", { name: "John", role: "admin" });
storage.setWithExpiry("token", "abc123", 3600); // expires in 1 hour
const user = storage.get("user", {});
const token = storage.getWithExpiry("token");
```

### 8.4 String Utilities (`string.js`)

| Function                 | Description                                              |
| :----------------------- | :------------------------------------------------------- |
| `slugify(text)`          | Convert to URL-safe slug (`Hello World` → `hello-world`) |
| `truncate(text, length)` | Truncate with ellipsis (default: 100 chars)              |
| `capitalize(text)`       | Capitalize first letter of each word                     |
| `randomString(length)`   | Generate alphanumeric random string (default: 10 chars)  |

### 8.5 Date Utilities (`date.js`)

| Function                        | Description                                     |
| :------------------------------ | :---------------------------------------------- |
| `formatDate(date, locale?)`     | Format to readable date (`January 15, 2026`)    |
| `formatDateTime(date, locale?)` | Format with time (`January 15, 2026, 02:30 PM`) |
| `timeAgo(date)`                 | Relative time (`2 hours ago`, `just now`)       |

### 8.6 Number & Currency (`number.js`)

| Function                                    | Description                                                                                                 |
| :------------------------------------------ | :---------------------------------------------------------------------------------------------------------- |
| `formatNumber(value, locale?, decimals?)`   | Locale-formatted number (`1,234,567`)                                                                       |
| `formatCurrency(value, currency?, locale?)` | Formatted currency with symbol. Accepts names (`dollar`, `euro`, `taka`) or ISO codes (`USD`, `EUR`, `BDT`) |
| `formatPercent(value, decimals?)`           | Percentage string (`15%`, `15.67%`)                                                                         |
| `formatBytes(bytes, decimals?)`             | File size formatting (`1.5 KB`, `2.30 MB`)                                                                  |
| `compactNumber(value)`                      | Compact notation (`1.2K`, `1.5M`)                                                                           |

```javascript
formatCurrency(1234.56); // "$1,234.56"
formatCurrency(1234.56, "euro", "de-DE"); // "1.234,56 €"
formatCurrency(1234.56, "taka"); // "৳1,234.56"
compactNumber(1234567); // "1.2M"
```

### 8.7 Clipboard (`clipboard.js`)

| Function                        | Description                                           |
| :------------------------------ | :---------------------------------------------------- |
| `copyToClipboard(text)`         | Copy text (Clipboard API with `execCommand` fallback) |
| `copyWithToast(text, toastFn?)` | Copy and show notification                            |
| `readFromClipboard()`           | Read clipboard text (secure contexts only)            |

### 8.8 Performance (`performance.js`)

| Function                 | Description                                                                      |
| :----------------------- | :------------------------------------------------------------------------------- |
| `debounce(func, wait?)`  | Delay execution until idle (default: 300ms). For: search inputs, validation      |
| `throttle(func, limit?)` | Execute at most once per interval (default: 100ms). For: scroll, resize handlers |

```javascript
const handleSearch = debounce((query) => fetchResults(query), 300);
const handleScroll = throttle(() => updatePosition(), 100);
```

### 8.9 Validation (`validation.js`)

| Function / Export            | Description                                                         |
| :--------------------------- | :------------------------------------------------------------------ |
| `validatePhone(phone)`       | Validate Bangladeshi mobile numbers (`01[3-9]XXXXXXXX`)             |
| `validateEmail(email)`       | Basic email format validation                                       |
| `validatePassword(password)` | Strict validation: 8-15 chars, uppercase, lowercase, number, symbol |
| `passwordRequirements`       | Array of `{label, regex}` objects for live password strength UI     |

### 8.10 Web Vitals (`webVitals.js`)

Monitors Core Web Vitals (LCP, FID, CLS, FCP, TTFB) using the `web-vitals` library.

| Function              | Description                                                                                                      |
| :-------------------- | :--------------------------------------------------------------------------------------------------------------- |
| `initWebVitals(opts)` | Start monitoring. Options: `sendToServer` (POST to `/api/analytics/vitals`), `logToConsole`, `onMetric` callback |
| `getWebVitals()`      | Get a snapshot of all current vitals (Promise)                                                                   |

```javascript
// In app.jsx or main entry
import { initWebVitals } from "@/Utils/webVitals";

if (import.meta.env.PROD) {
    initWebVitals({ sendToServer: true });
} else {
    initWebVitals({ logToConsole: true });
}
```

---

## 📬 9. Modern Mailing System

Stop writing repetitive mailable classes. FeatureKit provides a **Universal Mailable Engine**.

### `GeneralMail.php`

A single queued mailable (`implements ShouldQueue`) that handles every email:

```php
namespace App\Mail;

class GeneralMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,    // Email subject line
        public string $contentView,    // Blade view for email body
        public array $data = []        // Data passed to the view
    ) {}
}
```

**Usage:**

```php
use App\Mail\GeneralMail;

Mail::to($user)->queue(new GeneralMail(
    mailSubject: 'Your Order Has Shipped',
    contentView: 'emails.orders.shipped-body',
    data: [
        'title'      => 'Order Shipped!',
        'body'       => 'Your order #1234 is on its way.',
        'actionText' => 'Track Order',
        'actionUrl'  => 'https://example.com/track/1234',
    ]
));
```

### Email Layout (`emails/layout.blade.php`)

Every email rendered via `GeneralMail` automatically wraps in a professional Markdown template:

- **Header**: Logo image + app name.
- **Content Area**: White card with rounded corners — renders the `$content_view`.
- **Footer**: Copyright, year, optional unsubscribe link, and configurable address.

### Email Templates Included

| Template        | Location                                     | Purpose                                                                  |
| :-------------- | :------------------------------------------- | :----------------------------------------------------------------------- |
| Master Layout   | `emails/layout.blade.php`                    | Wraps all GeneralMail content                                            |
| General Body    | `emails/general.blade.php`                   | Generic notification with title, body, optional CTA button               |
| Forgot Password | `emails/auth/forgot-password-body.blade.php` | Password reset email with styled button, security note, and fallback URL |

---

## 🔐 10. Auth Feature (Reference Implementation)

The Auth feature is a **complete, working implementation** demonstrating all FeatureKit patterns.

### Controllers

| Controller                 | Methods                                           | Purpose                                    |
| :------------------------- | :------------------------------------------------ | :----------------------------------------- |
| `LoginController`          | `index()`, `login()`                              | Renders login page, handles authentication |
| `RegisterController`       | `index()`, `register()`                           | Renders registration page, creates user    |
| `ForgotPasswordController` | `index()`, `send()`, `showResetForm()`, `reset()` | Full forgot/reset password flow            |

### Services

| Service                 | Methods                                              | Purpose                                                                                             |
| :---------------------- | :--------------------------------------------------- | :-------------------------------------------------------------------------------------------------- |
| `LoginService`          | `attempt(credentials, remember)`                     | Authenticates user, regenerates session, throws `ValidationException` on failure                    |
| `RegisterService`       | `register(data)`                                     | Creates user with hashed password, auto-logs in                                                     |
| `ForgotPasswordService` | `sendResetLink(email)`, `resetPassword(credentials)` | Generates reset token, sends email via `GeneralMail`, resets password via Laravel's Password broker |

### Form Requests

| Request           | Validation Rules                                                                                                 |
| :---------------- | :--------------------------------------------------------------------------------------------------------------- |
| `LoginRequest`    | `email` (required, email), `password` (required), `remember` (boolean)                                           |
| `RegisterRequest` | `name` (max:255), `email` (unique:users), `phone` (max:20), `password` (confirmed, defaults), `terms` (accepted) |

### User Model

Located at `app/Features/Auth/Models/User.php` — extends `Authenticatable`:

- **Fillable:** `name`, `email`, `phone`, `password`
- **Hidden:** `password`, `remember_token`
- **Casts:** `email_verified_at` (datetime), `password` (hashed)

### Routes

```
GET    /auth/login             → LoginController@index         (auth.login.index)
POST   /auth/login             → LoginController@login         (auth.login.login)
GET    /auth/register          → RegisterController@index      (auth.register.index)
POST   /auth/register          → RegisterController@register   (auth.register.register)
GET    /auth/forgot-password   → ForgotPasswordController@index(auth.forgot-password.index)
POST   /auth/forgot-password   → ForgotPasswordController@send (auth.forgot-password.send)
GET    /auth/reset-password/{t}→ ForgotPasswordController@showResetForm (password.reset)
POST   /auth/reset-password    → ForgotPasswordController@reset(password.update)
```

### Auth Blade Views

- **`pages/auth/layout.blade.php`** — Split-screen auth layout: brand panel (left) + form area (right). Includes logo fallback, dot-grid background pattern, and loading spinner.
- **`pages/auth/login/page.blade.php`** — Login form with SEO meta.
- **`pages/auth/register/page.blade.php`** — Registration form.
- **`pages/auth/forgot-password/page.blade.php`** — Forgot password form.
- **`pages/auth/reset-password/page.blade.php`** — Reset password form with token.

---

## 🏠 11. Landing Feature

### Controller

`LandingController` serves three Blade pages:

| Route                | Method       | View                                          |
| :------------------- | :----------- | :-------------------------------------------- |
| `GET /`              | `index()`    | `pages.home.page` (Landing homepage)          |
| `GET /documentation` | `docs()`     | `pages.home.docs` (Interactive documentation) |
| `GET /features`      | `features()` | `pages.home.features` (Feature comparison)    |

### Landing Pages

**Home (`page.blade.php`):**

- Hero section with CTA buttons (Get Started → register, Documentation → docs).
- 6-card feature grid: Feature-Driven Design, Next.js Style Structure, Global Mail System, Utility Suite, Artisan Superpowers, Tailwind CSS v4.

**Documentation (`docs.blade.php`):**

- Sticky sidebar navigation (Installation, Architecture, Frontend Strategy, Artisan Commands).
- Sections: Introduction & Setup (prerequisites, quick install), Mastering Domains (thin controllers, brainy services with code examples), Frontend Architecture (Blade for performance, React 19 dashboard), CLI Scaffolders table.

**Features (`features.blade.php`):**

- Side-by-side comparison: Traditional Laravel (❌) vs FeatureKit Design (✅).
- Animated code preview showing folder structure.
- 3-pillar grid: Isolation, DX First, Reliability.

---

## ⚙️ 12. Command-Line Scaffolding

7 custom Artisan commands for generating feature components with flexible positional arguments.

### `make:feature`

```bash
# Simple Feature
php artisan make:feature {Name}
# Example: php artisan make:feature Blog

# Role-Based Feature (using arguments)
php artisan make:feature {Name} {Roles...}
# Example: php artisan make:feature Dashboard Admin User

# Role-Based Feature (using option)
php artisan make:feature {Name} --roles=Admin,User
```

**What it does:**

- Creates feature root in `app/Features/{Name}/`
- Scaffolds 8 directories: `Controllers/`, `Models/`, `Services/`, `Requests/`, `Observers/`, `Events/`, `Exceptions/`, `routes/`
- For role-based features, mirrors this structure inside each `{Role}/` folder.
- Generates `.gitkeep` in every folder and ready-to-use `web.php`/`api.php` stubs.

### Sub-Commands (`make:feature:type`)

All sub-commands support flexible positional paths. If multiple arguments follow the feature name, the last one is treated as the class name and the middle ones as roles/sub-paths.

#### `make:feature:controller`

```bash
# Simple: app/Features/Auth/Controllers/LoginController.php
php artisan make:feature:controller Auth Login

# Role-Based: app/Features/Dashboard/Admin/Controllers/ReportController.php
php artisan make:feature:controller Dashboard Admin Report
```

#### `make:feature:service`

```bash
# Simple: app/Features/Payment/Services/CheckoutService.php
php artisan make:feature:service Payment Checkout

# Role-Based: app/Features/Dashboard/User/Services/ProfileService.php
php artisan make:feature:service Dashboard User Profile
```

#### `make:feature:request`

```bash
# Simple: app/Features/Auth/Requests/RegisterRequest.php
php artisan make:feature:request Auth Register

# Role-Based: app/Features/Dashboard/Admin/Requests/UpdateSettingsRequest.php
php artisan make:feature:request Dashboard Admin UpdateSettings
```

#### `make:feature:event`

```bash
# Simple: app/Features/Order/Events/OrderPlaced.php
php artisan make:feature:event Order OrderPlaced

# Role-Based: app/Features/Project/Client/Events/FileUploaded.php
php artisan make:feature:event Project Client FileUploaded
```

#### `make:feature:exception`

```bash
# Simple: app/Features/Auth/Exceptions/InvalidTokenException.php
php artisan make:feature:exception Auth InvalidToken

# Role-Based: app/Features/Payment/Stripe/Exceptions/PaymentFailedException.php
php artisan make:feature:exception Payment Stripe PaymentFailed
```

#### `make:feature:observer`

```bash
# Simple: app/Features/Project/Observers/TaskObserver.php
php artisan make:feature:observer Project Task

# Role-Based: app/Features/Support/Admin/Observers/TicketObserver.php
php artisan make:feature:observer Support Admin Ticket
```

> **Pro Tip:** All sub-commands automatically append the appropriate suffix (Controller, Service, Request, Exception, Observer) if you omit it.

---

## 🎨 13. Tailwind CSS v4 Design System

**Location:** `resources/css/app.css`

Uses Tailwind v4's `@theme` directive for semantic design tokens with OKLCH color space:

### Design Tokens

```css
@theme {
    /* Primary Theme (Indigo) */
    --color-primary: oklch(0.637 0.191 278.358);
    --color-primary-foreground: white;
    --color-primary-surface: oklch(0.962 0.018 272.314);
    --color-primary-surface-foreground: oklch(0.368 0.11 277.394);

    /* Secondary Theme (Cyan) */
    --color-secondary: oklch(0.72 0.17 236.62);
    --color-secondary-foreground: white;
    --color-secondary-surface: oklch(0.977 0.013 236.62);
    --color-secondary-surface-foreground: oklch(0.46 0.09 236.62);

    /* Surface & Content */
    --color-surface: oklch(0.985 0 0);
    --color-surface-foreground: oklch(0.2 0 0);
    --color-surface-muted: oklch(0.92 0 0);
    --color-surface-muted-foreground: oklch(0.4 0 0);

    /* Status Colors */
    --color-error: oklch(0.6 0.2 25);
    --color-success: oklch(0.6 0.2 145);

    /* System */
    --color-border: oklch(0.92 0 0);
    --color-ring: oklch(0.637 0.191 278.358 / 0.5);

    /* Typography */
    --font-sans: "Instrument Sans", ui-sans-serif, system-ui, sans-serif, ...;
}
```

### White-Labeling

To rebrand the app, simply update the OKLCH values in `app.css`:

```css
--color-primary: oklch(0.65 0.2 150); /* Change indigo → green */
```

Every component using `bg-primary`, `text-primary`, etc. updates automatically.

### Content Sources

```css
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';
```

---

## 🔧 14. Dev Environment & Scripts

### Composer Scripts

| Command          | What It Does                                                                                                                   |
| :--------------- | :----------------------------------------------------------------------------------------------------------------------------- |
| `composer setup` | Full project setup: install PHP deps, copy `.env`, generate key, migrate DB, install node deps, build assets                   |
| `composer dev`   | Starts 4 processes concurrently: `php artisan serve` (server), `queue:listen` (queue), `pail` (logs), `npm run dev` (Vite HMR) |
| `composer test`  | Clears config cache and runs PHPUnit test suite                                                                                |

### NPM Scripts

| Command         | What It Does                           |
| :-------------- | :------------------------------------- |
| `npm run dev`   | Start Vite development server with HMR |
| `npm run build` | Production build                       |

### Vite Configuration

```javascript
// vite.config.js
export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.jsx"],
            refresh: ["resources/**", "app/Features/**"], // HMR on feature changes
        }),
        tailwindcss(),
        react(),
    ],
    resolve: {
        alias: { "@": "/resources/js" }, // @/ import alias
    },
});
```

### Environment

- **Default DB:** SQLite (`database/database.sqlite`)
- **Default Queue:** Database driver
- **Default Mail:** Log driver (switch to SMTP in production)
- **Session:** Database-backed

---

## 🗄️ 15. Database & Migrations

### Default Migrations

| Migration            | Tables Created                                                                                                                 |
| :------------------- | :----------------------------------------------------------------------------------------------------------------------------- |
| `create_users_table` | `users` (id, name, email, phone, email_verified_at, password, remember_token, timestamps), `password_reset_tokens`, `sessions` |
| `create_cache_table` | `cache`, `cache_locks`                                                                                                         |
| `create_jobs_table`  | `jobs`, `job_batches`, `failed_jobs`                                                                                           |

### Users Table Schema

```sql
id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
name            VARCHAR(255)
email           VARCHAR(255) UNIQUE
phone           VARCHAR(255) NULLABLE
email_verified_at TIMESTAMP NULLABLE
password        VARCHAR(255)
remember_token  VARCHAR(100) NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

---

## 🗺️ 16. Quick Reference Table

| Layer               | Responsibility                                                     | Location                                  |
| :------------------ | :----------------------------------------------------------------- | :---------------------------------------- |
| **Route Discovery** | Automatic scanning & registration                                  | `bootstrap/app.php`                       |
| **Base Classes**    | Controller, Service, Exception foundations                         | `app/Core/`                               |
| **API Responses**   | Standardized JSON format                                           | `app/Core/Traits/ApiResponseTrait.php`    |
| **Feature Logic**   | Domain controllers + services                                      | `app/Features/*/Controllers` & `Services` |
| **Feature Models**  | Domain-specific Eloquent models                                    | `app/Features/*/Models`                   |
| **Validation**      | Domain form requests                                               | `app/Features/*/Requests`                 |
| **Email Engine**    | Universal queued mailable                                          | `app/Mail/GeneralMail.php`                |
| **Email Layout**    | Professional Markdown master template                              | `resources/views/emails/layout.blade.php` |
| **Blade Pages**     | SEO landing, auth, docs                                            | `resources/views/pages/`                  |
| **React SPA**       | Inertia portal pages                                               | `resources/js/pages/(portals)/`           |
| **React UI Kit**    | Toast, Modal, Spinner, Pagination, Editor, SeoHead, PromoTemplates | `resources/js/Components/ui/`             |
| **React Layouts**   | MainLayout, AdminLayout, UserLayout                                | `resources/js/Components/Layout/`         |
| **Global State**    | Modal context system                                               | `resources/js/Contexts/ModalContext.jsx`  |
| **Custom Hooks**    | 20+ Inertia accessor hooks                                         | `resources/js/Hooks/useInertia.js`        |
| **JS Utilities**    | 11 utility modules                                                 | `resources/js/Utils/`                     |
| **Design Tokens**   | Tailwind v4 @theme                                                 | `resources/css/app.css`                   |
| **CLI Scaffolding** | 7 make:feature:* commands                                         | `app/Console/Commands/`                   |

---

## ✉️ 17. Universal Mailing System

Stop writing repetitive mailable classes. One `GeneralMail` class handles every email in your app — queued by default.

```php
Mail::to($user)->queue(new GeneralMail(
    mailSubject: 'Your Order Has Shipped',
    contentView: 'emails.orders.shipped-body',
    data: [
        'title'      => 'Order Shipped!',
        'body'       => 'Your order #1234 is on its way.',
        'actionText' => 'Track Order',
        'actionUrl'  => 'https://example.com/track/1234',
    ]
));
```

#### GeneralMail Constructor Map

| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `mailSubject` | `string` | **Yes** | Sets the exact email subject line. |
| `contentView` | `string` | **Yes** | The exact dot-notation Blade path for the template to render. |
| `data` | `array` | No | An array of dynamic values injected into the Blade template layout. |

---

## 🔔 18. Unified Notification System

FeatureKit uses a **Event-Driven Unified Notification System** that decouple features from the notification logic.

### `NotificationCreated` Event

A single, global event (`App\Features\Notification\Events\NotificationCreated`) is used to trigger notifications across the entire application.

```php
use App\Features\Notification\Events\NotificationCreated;

// Dispatch from any Service or Controller
event(new NotificationCreated(
    user: $user, 
    category: 'Security', 
    title: 'Password Changed', 
    message: 'Your account password has been updated.'
));
```

#### NotificationCreated Event Map

| Argument | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `user` | `User` | **Yes** | The Eloquent model instance of the user receiving the notification. |
| `title` | `string` | **Yes** | The explicit bold summary title shown in the notification list. |
| `message` | `string` | **Yes** | The detailed descriptive payload of the notification. |
| `category` | `string` | No (default: `system`) | Categorizes the notification for backend log aggregations (e.g. `Account`, `System`, `Billing`). |

### `CreateNotificationRecord` Listener

The listener (`App\Features\Notification\Listeners\CreateNotificationRecord`) is automatically registered in `AppServiceProvider`. It handles:
- **Queueing**: Implements `ShouldQueue` to run in the background.
- **Role Detection**: Automatically routes notifications to `AdminNotification` or `UserNotification` tables based on the user's role.

### Creating New Feature Events

To create a new event for a specific feature, use the scaffolder:
```bash
php artisan make:feature:event {FeatureName} {EventName}
```

---

---

## 🏗️ 19. Advanced Feature Patterns

### Independent User Models
For complex role-based systems, FeatureKit recommends using **Feature-Specific User Models** (e.g., `App\Features\Profile\Admin\Models\User`). 
- **Decoupling**: Prevents the core `App\Models\User` from becoming a "God Class".
- **Specialization**: Allows adding role-specific scopes, accessors, and relationships without cluttering other parts of the app.

### Secure Private Storage & Modular Delivery
FeatureKit implements a secure pattern for sensitive files like profile images:
1. **Private Storage**: Files are stored in `storage/app/private/` (not publicly accessible via URL).
2. **Modular Controller**: A dedicated `ProfileImageController` within the `Profile` feature serves these images.
3. **Role-Aware Routing**: Secure routes (e.g., `/admin/profile-image`) are defined within the feature's `web.php`, ensuring users can only access their own files or authorized assets.

---

## 📈 19. Activity Logs System

FeatureKit provides a highly extensible, **Event-Driven Activity Logging System** that captures and stores user interactions automatically.

### The `ActivityLogged` Event & `CreateActivityLogRecord` Listener

The system uses a synchronous event-listener combination to guarantee log capture, ensuring that audit trails are reliable even without background workers active.

```php
use App\Features\ActivityLog\Events\ActivityLogged;

// Dispatch from any Service
event(new ActivityLogged(
    user: $auth_user,
    action: 'login',
    description: 'User logged in successfully.'
));
```

The listener (`CreateActivityLogRecord`) captures:
- `user_id` and polymorphic `subject_type`
- The `action` keyword
- Detailed `description`
- `ip_address` and `user_agent` 

### Flexible UI Badges

The Activity Logs UI automatically scales with your application. The `getActionMeta` utility on the frontend maps standard keywords inside your action strings to visually distinct badges.

| Action Keyword Match | Badge Color | Description |
| :--- | :--- | :--- |
| `create`, `add`, `store` | **Green** | Best for resources being added to the database. |
| `update`, `edit` | **Blue** | Best for standard modification of resources. |
| `delete`, `remove`, `destroy` | **Red** | Best for destructive UI actions (deletion or archiving). |
| `login`, `auth` | **Emerald** | Reserved for authentication milestones. |
| `fail`, `error` | **Red** | Highlights failed transactions or exceptions. |
| `password`, `security` | **Amber** | Critical for tracking sensitive user credential changes. |
| `setting`, `config` | **Purple** | Settings or application environment modifications. |
| `view`, `read`, `download`, `export` | **Indigo** | Best for read-only tracking or exporting artifacts. |
| *No Match* | **Gray** | Automatic title-casing fallback for custom events. |

If no keyword matches, it gracefully transforms the action name (e.g., `invoice_generated`) into a readable format ("Invoice Generated") and categorizes it with a neutral gray badge.

---

## 🏛️ 20. Core Administrative Hubs

FeatureKit provides 4 scaffolded administration points, carefully isolated into feature domains and powered by thin-controller, thick-service architectures. Each hub perfectly illustrates how to execute complex logic without polluting controllers.

### 👥 1. User Management (`App\Features\UserManagement`)
A comprehensive control center replacing basic scaffolding, wired to a dedicated `UserService`.

- **Live Pagination & Search**: Automatically synchronizes UI parameters with Inertia queries.
- **Secure Provisioning**: An isolated "Add User" modal that directly validates against `StoreUserRequest` and properly hashes credentials before executing DB insertions.
- **Role & Access Interceptors**: You can safely flip User flags (`is_active` for banning, `role` for elevation) utilizing `UpdateUserRequest`.
- **Automatic Audit Trails**: The `UserService` dynamically detects toggle changes, such as suspending an account, and automatically fires `ActivityLogged` events.

```php
// Inside UserService.php
if ($user->isDirty('is_active')) {
    $statusText = $user->is_active ? 'Un-suspended' : 'Suspended';
    event(new ActivityLogged(auth()->user(), "account_status", "{$statusText} user: {$user->email}"));
}
```

### ⚡ 2. UI Cache Management (`App\Features\CacheManagement`)
A powerful, graphical representation for server configuration caching.

- The `CacheService` prevents bloated controllers by extracting specific `Artisan::call()` mapping logic into dedicated helper methods.
- Safely exposes flushing commands via UI: `cache:clear`, `route:clear`, `config:clear`, `view:clear`, or `optimize:clear`.
- Utilizes the global `ModalContext` on the frontend before executing destructive Artisan commands.

### ❤️ 3. System Health Monitor (`App\Features\SystemHealth`)
A visually striking "Live Metrics" dashboard analyzing the environment.

Instead of performing connections inside the controller, the modular `HealthStatusService` runs runtime checks to fetch:
1. **PDO SQL Integrity**: Verifying the current database connection is stable.
2. **Caching Driver Latency**: Assessing if Redis/Memcached is responding quickly.
3. **Hardware Config Constraints**: Reading `ini_get('memory_limit')` to warn about potential OOM issues.
4. **Stack Diagnostics**: Displaying precise Laravel and PHP versions via `app()->version()` and `phpversion()`.

### 📊 4. Professional Dashboards (`App\Features\Dashboard`)
Designed exclusively to prove the power of cleanly separating queries away from the HTTP layer.

The logic is split into Role-Based folders (`Admin` and `User`). We strictly abstract metric queries and relation loads into their respective `AdminDashboardService` and `UserDashboardService`. 

#### The Admin Dashboard
Tracks broad system performance overviews by calculating active vs suspended users, measuring total action milestones, and fetching the 10 most recent global `ActivityLogs` from across the entire app.

#### The User Dashboard
A personalized welcome hub that tracks the individual's exact `member_since` diff (e.g. "1 month ago"). It dynamically calculates a **Profile Completion Health** by evaluating fields like `profile_image`, `phone` and generates a responsive UI progress bar. Finally, it exposes a localized, private feed of their own event log history.

```php
// Example: Thin Dashboard Controller
public function index() {
    $user = Auth::user();
    return inertia('(portals)/user/dashboard/page', [
        'stats' => $this->dashboardService->getUserStats($user),
        'recent_activity' => $this->dashboardService->getRecentActivity($user, 10)
    ]);
}
```

### ⚙️ System Settings (Placeholder)
`App\Features\SystemSettings` is an empty foundational structure wired into the `AdminLayout`, standing by for your app's custom global variable definitions.

---

### Developed and Maintained by [Rifatxtra](https://rifatxtra.com).
MIT Licensed. Open for everyone to scale.
