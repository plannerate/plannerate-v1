---
description: Plannerate development workflow checklist — run before starting any feature, controller, component or route work to ensure conventions are followed.
---

You are about to work on the **Plannerate** project. Before writing a single line of code, execute this complete checklist in sequence. For each step, reason out loud with the user what you found.

## 1. Understand the Task Scope

Identify what is being built/changed:
- Is it a **new resource** (model + controller + policy)?
- Is it a **new frontend component** or page?
- Is it a **new route / Wayfinder action**?
- Is it a **bug fix** in existing code?

## 2. Commands — Always Use Sail

This project runs inside Docker via Laravel Sail. **Every** CLI command must use the Sail wrapper:

| What | Command |
|------|---------|
| Artisan | `./vendor/bin/sail artisan ...` |
| NPM | `./vendor/bin/sail npm run ...` |
| Composer | `./vendor/bin/sail composer ...` |
| Pint | `./vendor/bin/sail vendor/bin/pint --dirty` |
| Tests | `./vendor/bin/sail artisan test --compact` |

Never run `php artisan`, `npm`, or `composer` directly — Sail proxies everything through Docker.

## 3. Backend — Controller Pattern Check

Before creating any controller, read an existing sibling to understand the pattern:

```
app/Http/Controllers/Tenant/ClusterController.php   ← canonical example
app/Http/Controllers/Tenant/PlanogramController.php
app/Http/Controllers/Landlord/
```

Rules:
- Tenant controllers extend `Callcocam\LaravelRaptor\Http\Controllers\AbstractController`
- Always declare `->resource(ModelClass::class)` on the `Index` page
- `resourcePath()` returns the context string: `'tenant'`, `'landlord'`, `'admin'`
- Scaffold first: `./vendor/bin/sail artisan raptor:generate Name --controller --model=Name --table=table_name --force`
- Customize after scaffold — never start from scratch

## 4. Frontend — Check Components Before Creating

Before writing any new Vue component, search in order:

### 4a. Raptor package components (import via `~`)
```
packages/callcocam/laravel-raptor/resources/js/components/
  ui/          → Button, Input, Textarea, Badge, Card, Select, Dialog, Popover, Skeleton, Separator, Collapsible, Command, Field, Sidebar suite
  form/        → Raptor form field wrappers
  table/       → Table builder components
  infolist/    → InfoList display components
  actions/     → Action button components
  filters/     → Table filter components
  breadcrumbs/ → Breadcrumb components
  theme/       → Theme toggle and controls
```

Import example: `import { Button } from '~/components/ui/button'`

### 4b. App-level components (import via `@`)
```
resources/js/components/
  ui/            → Additional UI (card, checkbox, input wrappers)
  form/          → MapField, MultiSelectField, SysmoAPI, VisaoAPI
  gondola/       → Gondola canvas components
  kanban/        → Kanban board
  table/         → App-specific table wrappers
  theme/         → App theme components
  AppSidebar, AppHeader, AppShell, NavMain, NavUser, NavFooter, Breadcrumbs, Icon, Heading, etc.
```

Import example: `import AppShell from '@/components/AppShell.vue'`

**Only create a new component if none exists. If it could be reused, extract it as shared.**

## 5. Design System — Colors & Styling

Use **semantic CSS variables** — never hardcode colors or use arbitrary oklch values directly.

| Tailwind class | Meaning |
|---------------|---------|
| `bg-primary` / `text-primary` | Teal-green accent (brand color) |
| `bg-background` / `text-foreground` | Page background / main text |
| `bg-card` / `text-card-foreground` | Card surfaces |
| `bg-muted` / `text-muted-foreground` | Subtle backgrounds, secondary text |
| `bg-secondary` / `text-secondary-foreground` | Secondary actions |
| `border-border` | All dividers and borders |
| `bg-destructive` | Danger / delete |
| `bg-primary/10` | Tinted primary background (alpha) |
| `text-chart-1` → `text-chart-5` | Data visualization |
| `bg-sidebar` / `text-sidebar-foreground` | Sidebar surfaces |

Always:
- Support dark mode with `dark:` variants
- Use `rounded-[var(--radius)]` or `rounded-md` for consistent radius (0.625rem)
- Use Lucide icons via `lucide-vue-next` (app standard)
- Use `gap-*` for spacing in flex/grid, not margins between siblings
- Vue SFC: `<script setup lang="ts">` — typed props with `defineProps<Props>()`

## 6. Wayfinder — Type-Safe Routes

After any controller route change:
```bash
./vendor/bin/sail artisan wayfinder:generate --with-form
```

Use generated actions — never hardcode URLs:
```typescript
// Import controller methods
import { index, show, store, update, destroy } from '@/actions/App/Http/Controllers/Tenant/ClusterController'

// Navigation
router.visit(index())

// Inertia Form (with --with-form flag)
<Form v-bind="store.form()">...</Form>

// Link href
<Link :href="show.url(cluster.id)">...</Link>
```

Actions directory: `resources/js/actions/App/Http/Controllers/`
Named routes: `resources/js/routes/`

## 7. Wayfinder — Vite Plugin Note

The Wayfinder Vite plugin is **currently disabled** in this project (permissions issue). Always run `wayfinder:generate` manually after route changes.

## 8. Testing Checklist

- Write or update a Pest test for every change
- Feature tests in `tests/Feature/`, unit in `tests/Unit/`, browser in `tests/Browser/`
- Run: `./vendor/bin/sail artisan test --compact --filter=RelevantTestName`
- Use `php artisan make:test --pest TestName` to scaffold

## 9. Code Formatting

Always run Pint before finishing:
```bash
./vendor/bin/sail vendor/bin/pint --dirty
```

## 10. Frontend Build Reminder

If frontend changes aren't reflected:
```bash
./vendor/bin/sail npm run build
# or in dev mode:
./vendor/bin/sail npm run dev
```

---

Now proceed with the task, following each applicable step above.
