# Agent Workflow — Code Generation Decision Engine

> **Prerequisites:** Read `.agent/agent.md` first for the complete knowledge base (architecture, all utilities, hooks, components, tokens, and conventions).
> This file provides the **step-by-step workflow** for generating code correctly.

---

## 🧠 Step 1 — Identify Feature Type

```
Is it role-specific? (admin only, user only, etc.)
  YES → Role-based feature: app/Features/{Feature}/{Role}/
  NO  → Simple feature:     app/Features/{Feature}/
```

---

## 🗂️ Step 2 — Determine Page Type

```
Is it a public page? (no login required, needs SEO)
  YES → Blade  → resources/views/pages/{feature}/{page}/page.blade.php
                  Extend: @extends('pages.layout') or @extends('pages.auth.layout')

  NO  → React  → resources/js/pages/(portals)/{role}/{page}/page.jsx
                  Render: Inertia::render('(portals)/{role}/{page}/page', [...])
                  Auto-wrapped in MainLayout (Toast + Modal + Spinner)
```

---

## 📁 Step 3 — Map All File Paths

### Backend (PHP)

```
Simple feature:
  Controller → app/Features/{Feature}/Controllers/{Name}Controller.php
  Service    → app/Features/{Feature}/Services/{Name}Service.php
  Request    → app/Features/{Feature}/Requests/{Name}Request.php
  Model      → app/Features/{Feature}/Models/{Name}.php
  Observer   → app/Features/{Feature}/Observers/{Name}Observer.php
  Event      → app/Features/{Feature}/Events/{Name}.php
  Exception  → app/Features/{Feature}/Exceptions/{Name}Exception.php
  Web routes → app/Features/{Feature}/routes/web.php
  API routes → app/Features/{Feature}/routes/api.php

Role-based feature:
  Controller → app/Features/{Feature}/{Role}/Controllers/{Name}Controller.php
  Service    → app/Features/{Feature}/{Role}/Services/{Name}Service.php
  (same pattern for all other types)
  Web routes → app/Features/{Feature}/{Role}/routes/web.php
  API routes → app/Features/{Feature}/{Role}/routes/api.php
```

### Namespaces

```php
// Simple
namespace App\Features\{Feature}\Controllers;
namespace App\Features\{Feature}\Services;
namespace App\Features\{Feature}\Models;
namespace App\Features\{Feature}\Requests;
namespace App\Features\{Feature}\Observers;
namespace App\Features\{Feature}\Events;
namespace App\Features\{Feature}\Exceptions;

// Role-based
namespace App\Features\{Feature}\{Role}\Controllers;
namespace App\Features\{Feature}\{Role}\Services;
// etc.
```

---

## 🖥️ Step 4 — Controller Template

```php
<?php

namespace App\Features\{Feature}\Controllers;

use App\Core\BaseController;
use App\Features\{Feature}\Services\{Name}Service;
use App\Features\{Feature}\Requests\{Name}Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class {Name}Controller extends BaseController
{
    public function __construct(private readonly {Name}Service $service) {}

    // Inertia page (portal/SPA)
    public function dashboard(): Response
    {
        return Inertia::render('(portals)/{role}/{page}/page', [
            'data' => $this->service->getData(),
        ]);
    }

    // Form submission (Inertia — same pattern, redirect triggers Toast)
    public function update({Name}Request $request): RedirectResponse
    {
        $this->service->update($request->validated());
        return redirect()->back()->with('success', 'Updated!');
        // Toast component in MainLayout reads 'success' flash automatically.
    }
}
```

---

## 🧠 Step 5 — Service Template

```php
<?php

namespace App\Features\{Feature}\Services;

use App\Core\BaseService;
use App\Features\{Feature}\Models\{Model};
use App\Features\Notification\Events\NotificationCreated;
use Illuminate\Support\Facades\DB;

class {Name}Service extends BaseService
{
    public function handle(array $data): {Model}
    {
        $record = DB::transaction(fn() => {Model}::create($data));

        // ALWAYS Trigger Unified Notification for user status updates
        event(new NotificationCreated(
            user: auth()->user(), 
            category: 'System', 
            title: 'Action Successful', 
            message: 'Your request has been processed.'
        ));

        return $record;
    }
}
```

---

## 📝 Step 6 — Route Templates

```php
<?php
// Role-based feature: app/Features/{Feature}/{Role}/routes/web.php
// Auto-prefix: /{role}, name prefix: {role}.
use App\Features\{Feature}\{Role}\Controllers\{Name}Controller;
use Illuminate\Support\Facades\Route;

// Use 'role:xxx' middleware to gate by user role
Route::middleware(['auth', 'role:{role}'])->group(function () {
    Route::get('/dashboard', [{Name}Controller::class, 'index'])->name('dashboard');
    // Accessible at: /{role}/dashboard → name: {role}.dashboard
});
```

---

## ⚛️ Step 7 — React Page Template

```jsx
// resources/js/pages/(portals)/{role}/{page}/page.jsx
// MainLayout auto-injected — Toast, Modal, Spinner available automatically

import { useForm, Link, Head } from "@inertiajs/react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import SeoHead from "@/Components/ui/SeoHead";
import { useUser, useHasRole } from "@/Hooks";

export default function {Name}Page({ data }) {
    const user = useUser();
    const isAdmin = useHasRole("admin");

    const { data: formData, setData, post, processing, errors } = useForm({
        name: "",
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route("{role}.{feature}.store"));
    };

    return (
        <div className="p-6">
            <SeoHead title="{Page Title}" />
            <Head title="{Page Title}" />

            <h1 className="text-2xl font-bold">{/* Title */}</h1>

            <form onSubmit={handleSubmit}>
                <input
                    value={formData.name}
                    onChange={(e) => setData("name", e.target.value)}
                    className="border border-border rounded-lg px-4 py-2"
                />
                <button type="submit" disabled={processing} className="bg-primary text-white px-6 py-2 rounded-lg">
                    {processing ? "Saving..." : "Save"}
                </button>
            </form>
        </div>
    );
}

{Name}Page.layout = (page) => <AdminLayout>{page}</AdminLayout>;
export default {Name}Page;
```

---

## 📋 Decision Tree

```
User request received
        │
        ▼
Is it a NEW FEATURE?
  YES → Run: php artisan make:feature {Name}
        or:  php artisan make:feature {Name} {RoleA} {RoleB}
        │
        ▼
Is the page public or portal?
  Public  → Blade view + Feature Controller + view()
  Portal  → React page + Feature Controller + Inertia::render()
        │
        ▼
Trigger Notification?
  YES → ALWAYS use event(new NotificationCreated(...)) in Service.
        Listener is queue-backed and role-aware automatically.
        │
        ▼
Need to send email?
  YES → Create body template in resources/views/emails/
        Use GeneralMail (NEVER create new Mailable)
        │
        ▼
Need a modal?
  Use useModal() from @/Contexts/ModalContext
        │
        ▼
No routes in bootstrap/app.php needed ✅
Toast notifications automatic via flash messages ✅
Layout injection automatic via MainLayout ✅
```

---

## 🔍 Pre-Generation Checklist

Before generating ANY code, verify:

- [ ] Namespace matches file path exactly
- [ ] Controller extends `BaseController`
- [ ] Service extends `BaseService`
- [ ] Business logic is in Service (NOT controller)
- [ ] **NotificationCreated** event is triggered for status updates
- [ ] Route is in the feature's `routes/` folder (not root)
- [ ] Using semantic Tailwind tokens (not arbitrary colors)
- [ ] Role-gated routes use `middleware('role:xxx')`
