# Table Column Components Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create 5 reusable Vue cell components in `resources/js/components/table/columns/` and migrate all 7 tenant list pages to use them.

**Architecture:** Each component renders only the cell content (not the `<td>` wrapper), keeping per-page flexibility for cell styling. A barrel `index.ts` exports all components. Existing pages swap inline markup for the components, removing duplicate `statusVariant`, `formatDate`, and image/action markup.

**Tech Stack:** Vue 3 `<script setup>`, TypeScript, Tailwind CSS v4, lucide-vue-next, `@/components/ui/badge`

---

## File Map

**Create:**
- `resources/js/components/table/columns/ColumnStatusBadge.vue`
- `resources/js/components/table/columns/ColumnActions.vue`
- `resources/js/components/table/columns/ColumnImage.vue`
- `resources/js/components/table/columns/ColumnDate.vue`
- `resources/js/components/table/columns/ColumnLabel.vue`
- `resources/js/components/table/columns/index.ts`

**Modify (7 pages):**
- `resources/js/pages/tenant/planograms/Index.vue`
- `resources/js/pages/tenant/products/Index.vue`
- `resources/js/pages/tenant/categories/Index.vue`
- `resources/js/pages/tenant/gondolas/Index.vue`
- `resources/js/pages/tenant/stores/Index.vue`
- `resources/js/pages/tenant/providers/Index.vue`
- `resources/js/pages/tenant/clusters/Index.vue`

---

### Task 1: ColumnStatusBadge.vue

**Files:**
- Create: `resources/js/components/table/columns/ColumnStatusBadge.vue`

- [ ] **Step 1: Create the component**

```vue
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';

const props = defineProps<{
    status: string;
}>();

function statusVariant(status: string): BadgeVariants['variant'] {
    if (status === 'published') return 'default';
    if (status === 'importer') return 'secondary';
    return 'outline';
}
</script>

<template>
    <Badge :variant="statusVariant(status)" class="capitalize">{{ status }}</Badge>
</template>
```

---

### Task 2: ColumnActions.vue

**Files:**
- Create: `resources/js/components/table/columns/ColumnActions.vue`

- [ ] **Step 1: Create the component**

```vue
<script setup lang="ts">
import DeleteButton from '@/components/DeleteButton.vue';
import EditButton from '@/components/EditButton.vue';

withDefaults(
    defineProps<{
        editHref: string;
        deleteHref: string;
        deleteLabel?: string;
        requireConfirmWord?: boolean;
    }>(),
    {
        requireConfirmWord: false,
    },
);
</script>

<template>
    <div class="inline-flex items-center gap-2">
        <slot />
        <EditButton :href="editHref" />
        <DeleteButton :href="deleteHref" :label="deleteLabel" :require-confirm-word="requireConfirmWord" />
    </div>
</template>
```

---

### Task 3: ColumnImage.vue

**Files:**
- Create: `resources/js/components/table/columns/ColumnImage.vue`

- [ ] **Step 1: Create the component**

```vue
<script setup lang="ts">
withDefaults(
    defineProps<{
        src?: string | null;
        alt?: string;
    }>(),
    {
        alt: '',
    },
);
</script>

<template>
    <img
        v-if="src"
        :src="src"
        :alt="alt"
        class="h-10 w-10 rounded-md border border-border object-cover"
        loading="lazy"
    />
    <div v-else class="h-10 w-10 rounded-md border border-dashed border-border bg-muted/30" />
</template>
```

---

### Task 4: ColumnDate.vue

**Files:**
- Create: `resources/js/components/table/columns/ColumnDate.vue`

- [ ] **Step 1: Create the component**

```vue
<script setup lang="ts">
import { CalendarDays } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    date?: string | null;
    from?: string | null;
    to?: string | null;
}>();

function formatDate(value: string | null | undefined): string {
    if (!value) return '-';
    return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' }).format(
        new Date(value + 'T00:00:00'),
    );
}

const display = computed((): string => {
    if (props.from || props.to) {
        return `${formatDate(props.from)} → ${formatDate(props.to)}`;
    }
    if (props.date) {
        return formatDate(props.date);
    }
    return '—';
});
</script>

<template>
    <div class="flex items-start gap-1.5 text-muted-foreground">
        <CalendarDays class="mt-0.5 size-3.5 shrink-0" />
        <span class="leading-snug text-sm">{{ display }}</span>
    </div>
</template>
```

---

### Task 5: ColumnLabel.vue

**Files:**
- Create: `resources/js/components/table/columns/ColumnLabel.vue`

- [ ] **Step 1: Create the component**

```vue
<script setup lang="ts">
defineProps<{
    label: string;
    description?: string | null;
}>();
</script>

<template>
    <div class="flex flex-col gap-0.5">
        <span class="text-sm font-medium">{{ label }}</span>
        <span v-if="description" class="text-xs text-muted-foreground">{{ description }}</span>
    </div>
</template>
```

---

### Task 6: Barrel export + TypeScript check + commit

**Files:**
- Create: `resources/js/components/table/columns/index.ts`

- [ ] **Step 1: Create the barrel**

```typescript
export { default as ColumnActions } from './ColumnActions.vue';
export { default as ColumnDate } from './ColumnDate.vue';
export { default as ColumnImage } from './ColumnImage.vue';
export { default as ColumnLabel } from './ColumnLabel.vue';
export { default as ColumnStatusBadge } from './ColumnStatusBadge.vue';
```

- [ ] **Step 2: Run TypeScript check**

```bash
./vendor/bin/sail npm run types:check 2>&1 | grep "table/columns"
```

Expected: no output (no errors in the new files)

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/table/columns/
git commit -m "feat: add shared table column components (ColumnStatusBadge, ColumnActions, ColumnImage, ColumnDate, ColumnLabel)"
```

---

### Task 7: Migrate planograms/Index.vue

**Files:**
- Modify: `resources/js/pages/tenant/planograms/Index.vue`

**What changes:**
- Remove `CalendarDays` from lucide import (moved into ColumnDate)
- Remove `statusVariant()` function (moved into ColumnStatusBadge)
- Remove `formatDate()` function (moved into ColumnDate)
- Add column component imports
- Name cell: wrap the two `<p>` tags with `<ColumnLabel>`
- Period cell: replace the date div with `<ColumnDate :from="..." :to="..." />`
- Status cell: replace `<Badge :variant="statusVariant(...)">` with `<ColumnStatusBadge>`
- Actions cell: replace `<div class="inline-flex ...">` with `<ColumnActions>` + slot for "Ver Gondolas" button
- `Badge` import stays (still used for the `type` column)

- [ ] **Step 1: Update the script section**

Replace the script block with:

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { LayoutTemplate, Store } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import GondolaController from '@/actions/App/Http/Controllers/Tenant/GondolaController';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnDate, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type PlanogramRow = {
    id: string;
    name: string | null;
    slug: string | null;
    type: 'realograma' | 'planograma';
    store: string | null;
    cluster: string | null;
    category: string | null;
    start_date: string | null;
    end_date: string | null;
    status: 'draft' | 'published';
};

const props = defineProps<{
    subdomain: string;
    planograms: Paginator<PlanogramRow>;
    filters: {
        search: string;
        status: string;
        type: string;
        store_id: string;
    };
    filter_options: {
        stores: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();
const planogramsIndexPath = PlanogramController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.planograms.title'),
    title: t('app.tenant.planograms.title'),
    description: t('app.tenant.planograms.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.planograms.navigation'), href: planogramsIndexPath },
    ],
});
</script>
```

- [ ] **Step 2: Replace the 4 affected `<td>` cells in the template**

Replace the name cell (currently lines 133–141):
```html
<!-- BEFORE -->
<td class="px-4 py-3">
    <div class="flex items-center gap-2">
        <LayoutTemplate class="size-4 shrink-0 text-muted-foreground" />
        <div>
            <p class="font-medium leading-tight">{{ planogram.name ?? '-' }}</p>
            <p v-if="planogram.category" class="mt-0.5 text-xs text-muted-foreground">{{ planogram.category }}</p>
        </div>
    </div>
</td>

<!-- AFTER -->
<td class="px-4 py-3">
    <div class="flex items-center gap-2">
        <LayoutTemplate class="size-4 shrink-0 text-muted-foreground" />
        <ColumnLabel :label="planogram.name ?? '-'" :description="planogram.category" />
    </div>
</td>
```

Replace the period cell (currently lines 159–169):
```html
<!-- BEFORE -->
<td class="px-4 py-3">
    <div class="flex items-start gap-1.5 text-muted-foreground">
        <CalendarDays class="mt-0.5 size-3.5 shrink-0" />
        <div class="leading-snug">
            <span>{{ formatDate(planogram.start_date) }}</span>
            <span class="mx-1 text-muted-foreground/50">→</span>
            <span>{{ formatDate(planogram.end_date) }}</span>
        </div>
    </div>
</td>

<!-- AFTER -->
<td class="px-4 py-3">
    <ColumnDate :from="planogram.start_date" :to="planogram.end_date" />
</td>
```

Replace the status cell (currently lines 171–176):
```html
<!-- BEFORE -->
<td class="px-4 py-3">
    <Badge :variant="statusVariant(planogram.status)" class="capitalize">
        {{ planogram.status }}
    </Badge>
</td>

<!-- AFTER -->
<td class="px-4 py-3">
    <ColumnStatusBadge :status="planogram.status" />
</td>
```

Replace the actions cell (currently lines 178–194):
```html
<!-- BEFORE -->
<td class="px-4 py-3 text-right">
    <div class="inline-flex items-center gap-2">
        <Button variant="outline" size="sm" as-child>
            <Link :href="GondolaController.index.url({ subdomain: props.subdomain, planogram: planogram.id })">
                {{ t('app.tenant.planograms.actions.view_gondolas') }}
            </Link>
        </Button>
        <EditButton :href="PlanogramController.edit.url({ subdomain: props.subdomain, planogram: planogram.id })" />
        <DeleteButton
            :href="PlanogramController.destroy.url({ subdomain: props.subdomain, planogram: planogram.id })"
            :label="planogram.name ?? undefined"
            require-confirm-word
        />
    </div>
</td>

<!-- AFTER -->
<td class="px-4 py-3 text-right">
    <ColumnActions
        :edit-href="PlanogramController.edit.url({ subdomain: props.subdomain, planogram: planogram.id })"
        :delete-href="PlanogramController.destroy.url({ subdomain: props.subdomain, planogram: planogram.id })"
        :delete-label="planogram.name ?? undefined"
        :require-confirm-word="true"
    >
        <Button variant="outline" size="sm" as-child>
            <Link :href="GondolaController.index.url({ subdomain: props.subdomain, planogram: planogram.id })">
                {{ t('app.tenant.planograms.actions.view_gondolas') }}
            </Link>
        </Button>
    </ColumnActions>
</td>
```

- [ ] **Step 3: Verify TypeScript**

```bash
./vendor/bin/sail npm run types:check 2>&1 | grep "planograms/Index"
```

Expected: no output

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/tenant/planograms/Index.vue
git commit -m "refactor: migrate planograms/Index.vue to shared column components"
```

---

### Task 8: Migrate products/Index.vue

**Files:**
- Modify: `resources/js/pages/tenant/products/Index.vue`

**What changes:**
- Add column component imports
- Image cell: replace `<img>` + fallback `<div>` with `<ColumnImage>`
- Name + Slug: merge into one `<td>` using `<ColumnLabel>` — removes the separate Slug column
- Actions cell: replace `<div class="inline-flex ...">` with `<ColumnActions>`
- Remove `DeleteButton` and `EditButton` direct imports (now inside ColumnActions)
- Update thead to remove the "Slug" `<th>`
- Update empty row colspan from `7` to `6`

- [ ] **Step 1: Update the script section**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnImage, ColumnLabel } from '@/components/table/columns';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type ProductRow = {
    id: string;
    name: string | null;
    image_url: string | null;
    slug: string | null;
    ean: string | null;
    status: 'draft' | 'published' | 'synced' | 'error';
    category: string | null;
};

const props = defineProps<{
    subdomain: string;
    products: Paginator<ProductRow>;
    filters: {
        search: string;
        status: string;
        category_id: string;
    };
    filter_options: {
        categories: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();
const productsIndexPath = ProductController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.products.title'),
    title: t('app.tenant.products.title'),
    description: t('app.tenant.products.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.products.navigation'), href: productsIndexPath },
    ],
});
</script>
```

- [ ] **Step 2: Update the thead — remove the Slug column**

```html
<!-- BEFORE -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.form.sections.image') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">Slug</th>
    <th class="px-4 py-3 font-medium">EAN</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.category') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.status') }}</th>
    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
</tr>

<!-- AFTER -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.form.sections.image') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">EAN</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.category') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.status') }}</th>
    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
</tr>
```

- [ ] **Step 3: Update the empty row colspan and the data rows**

```html
<!-- BEFORE (empty row) -->
<td class="px-4 py-6 text-muted-foreground" colspan="7">

<!-- AFTER -->
<td class="px-4 py-6 text-muted-foreground" colspan="6">
```

```html
<!-- BEFORE (data row cells) -->
<td class="px-4 py-3">
    <img
        v-if="product.image_url"
        :src="product.image_url"
        :alt="product.name ?? 'Produto'"
        class="h-10 w-10 rounded-md border border-border object-cover"
        loading="lazy"
    />
    <div v-else class="h-10 w-10 rounded-md border border-dashed border-border bg-muted/30" />
</td>
<td class="px-4 py-3 font-medium">{{ product.name ?? '-' }}</td>
<td class="px-4 py-3">{{ product.slug ?? '-' }}</td>
<td class="px-4 py-3">{{ product.ean ?? '-' }}</td>
<td class="px-4 py-3">{{ product.category ?? '-' }}</td>
<td class="px-4 py-3">{{ product.status }}</td>
<td class="px-4 py-3 text-right">
    <div class="inline-flex items-center gap-2">
        <EditButton :href="ProductController.edit.url({ subdomain: props.subdomain, product: product.id })" />
        <DeleteButton
            :href="ProductController.destroy.url({ subdomain: props.subdomain, product: product.id })"
            :label="product.name ?? undefined"
            require-confirm-word
        />
    </div>
</td>

<!-- AFTER -->
<td class="px-4 py-3">
    <ColumnImage :src="product.image_url" :alt="product.name ?? 'Produto'" />
</td>
<td class="px-4 py-3">
    <ColumnLabel :label="product.name ?? '-'" :description="product.slug" />
</td>
<td class="px-4 py-3">{{ product.ean ?? '-' }}</td>
<td class="px-4 py-3">{{ product.category ?? '-' }}</td>
<td class="px-4 py-3">{{ product.status }}</td>
<td class="px-4 py-3 text-right">
    <ColumnActions
        :edit-href="ProductController.edit.url({ subdomain: props.subdomain, product: product.id })"
        :delete-href="ProductController.destroy.url({ subdomain: props.subdomain, product: product.id })"
        :delete-label="product.name ?? undefined"
        :require-confirm-word="true"
    />
</td>
```

- [ ] **Step 4: Verify TypeScript**

```bash
./vendor/bin/sail npm run types:check 2>&1 | grep "products/Index"
```

Expected: no output

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/tenant/products/Index.vue
git commit -m "refactor: migrate products/Index.vue to shared column components"
```

---

### Task 9: Migrate categories/Index.vue

**Files:**
- Modify: `resources/js/pages/tenant/categories/Index.vue`

**What changes:**
- Remove `statusVariant()` function
- Add column component imports
- Remove `DeleteButton` and `EditButton` direct imports
- Name + Slug: merge into `<ColumnLabel>` — removes separate Slug column
- Status cell: `<Badge :variant="statusVariant(...)">` → `<ColumnStatusBadge>`
- Actions cell: → `<ColumnActions>`
- Remove unused `Button` import (no extra action buttons in this page)
- Update thead: remove the "Slug" `<th>`
- Update empty row colspan from `6` to `5`
- `Badge` import stays (used for `is_placeholder` badge)

- [ ] **Step 1: Update the script section**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import CategoryController from '@/actions/App/Http/Controllers/Tenant/CategoryController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import { Badge } from '@/components/ui/badge';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type CategoryRow = {
    id: string;
    name: string;
    slug: string | null;
    status: 'draft' | 'published' | 'importer';
    codigo: number | null;
    is_placeholder: boolean;
};

const props = defineProps<{
    subdomain: string;
    categories: Paginator<CategoryRow>;
    filters: {
        search: string;
        status: string;
    };
}>();

const { t } = useT();
const categoriesIndexPath = CategoryController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.categories.title'),
    title: t('app.tenant.categories.title'),
    description: t('app.tenant.categories.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.categories.navigation'), href: categoriesIndexPath },
    ],
});
</script>
```

- [ ] **Step 2: Update the thead**

```html
<!-- BEFORE -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">Slug</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.codigo') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.status') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.is_placeholder') }}</th>
    <th class="px-4 py-3 text-right font-medium">{{ t('app.tenant.common.actions') }}</th>
</tr>

<!-- AFTER -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.codigo') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.status') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.is_placeholder') }}</th>
    <th class="px-4 py-3 text-right font-medium">{{ t('app.tenant.common.actions') }}</th>
</tr>
```

- [ ] **Step 3: Update the empty row colspan and data cells**

```html
<!-- BEFORE (empty row) -->
<td class="px-4 py-8 text-center text-muted-foreground" colspan="6">

<!-- AFTER -->
<td class="px-4 py-8 text-center text-muted-foreground" colspan="5">
```

```html
<!-- BEFORE (data row cells) -->
<td class="px-4 py-3 font-medium">{{ category.name }}</td>
<td class="px-4 py-3 text-muted-foreground">{{ category.slug ?? '—' }}</td>
<td class="px-4 py-3 text-muted-foreground">{{ category.codigo ?? '—' }}</td>
<td class="px-4 py-3">
    <Badge :variant="statusVariant(category.status)" class="capitalize">
        {{ category.status }}
    </Badge>
</td>
<td class="px-4 py-3">
    <Badge v-if="category.is_placeholder" variant="secondary">
        {{ t('app.tenant.common.yes') }}
    </Badge>
    <span v-else class="text-muted-foreground">{{ t('app.tenant.common.no') }}</span>
</td>
<td class="px-4 py-3 text-right">
    <div class="inline-flex items-center gap-2">
        <EditButton :href="CategoryController.edit.url({
            subdomain: props.subdomain,
            category: category.id,
        })" />
        <DeleteButton :href="CategoryController.destroy.url({
            subdomain: props.subdomain,
            category: category.id,
        })" :label="category.name ?? undefined" require-confirm-word />
    </div>
</td>

<!-- AFTER -->
<td class="px-4 py-3">
    <ColumnLabel :label="category.name" :description="category.slug" />
</td>
<td class="px-4 py-3 text-muted-foreground">{{ category.codigo ?? '—' }}</td>
<td class="px-4 py-3">
    <ColumnStatusBadge :status="category.status" />
</td>
<td class="px-4 py-3">
    <Badge v-if="category.is_placeholder" variant="secondary">
        {{ t('app.tenant.common.yes') }}
    </Badge>
    <span v-else class="text-muted-foreground">{{ t('app.tenant.common.no') }}</span>
</td>
<td class="px-4 py-3 text-right">
    <ColumnActions
        :edit-href="CategoryController.edit.url({ subdomain: props.subdomain, category: category.id })"
        :delete-href="CategoryController.destroy.url({ subdomain: props.subdomain, category: category.id })"
        :delete-label="category.name ?? undefined"
        :require-confirm-word="true"
    />
</td>
```

- [ ] **Step 4: Verify TypeScript**

```bash
./vendor/bin/sail npm run types:check 2>&1 | grep "categories/Index"
```

Expected: no output

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/tenant/categories/Index.vue
git commit -m "refactor: migrate categories/Index.vue to shared column components"
```

---

### Task 10: Migrate gondolas/Index.vue

**Files:**
- Modify: `resources/js/pages/tenant/gondolas/Index.vue`

**What changes:**
- Add column component imports
- Remove `DeleteButton` and `EditButton` direct imports
- Name + Slug: wrap with `<ColumnLabel>` (icon stays), remove separate Slug column
- Actions cell: → `<ColumnActions>` + slot for "Open Editor" button
- Update thead: remove "Slug" `<th>`
- Update empty row colspan from `7` to `6`

- [ ] **Step 1: Update the script section**

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PanelTop } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import GondolaController from '@/actions/App/Http/Controllers/Tenant/GondolaController';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnLabel } from '@/components/table/columns';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import { editor as tenantEditorPlanogramGondolas } from '@/routes/tenant/planograms/gondolas';
import type { Paginator } from '@/types';

type GondolaRow = {
    id: string;
    name: string;
    slug: string | null;
    num_modulos: number;
    location: string | null;
    side: string | null;
    flow: 'left_to_right' | 'right_to_left';
    alignment: 'left' | 'right' | 'center' | 'justify';
    scale_factor: number;
    status: 'draft' | 'published';
};

const props = defineProps<{
    subdomain: string;
    planogram: {
        id: string;
        name: string | null;
    };
    gondolas: Paginator<GondolaRow>;
    filters: {
        search: string;
        status: string;
    };
}>();

const { t } = useT();
const gondolasIndexPath = GondolaController.index.url({
    subdomain: props.subdomain,
    planogram: props.planogram.id,
}).replace(/^\/\/[^/]+/, '');
const planogramsIndexPath = PlanogramController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.gondolas.title'),
    title: t('app.tenant.gondolas.title'),
    description: t('app.tenant.gondolas.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.planograms.navigation'), href: planogramsIndexPath },
        { title: props.planogram.name ?? '-', href: planogramsIndexPath },
        { title: t('app.tenant.gondolas.navigation'), href: gondolasIndexPath },
    ],
});
</script>
```

- [ ] **Step 2: Update the thead**

```html
<!-- BEFORE -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">Slug</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.modules') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.flow') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.alignment') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.status') }}</th>
    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
</tr>

<!-- AFTER -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.modules') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.flow') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.alignment') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.gondolas.fields.status') }}</th>
    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
</tr>
```

- [ ] **Step 3: Update empty row colspan and data cells**

```html
<!-- BEFORE (empty row) -->
<td class="px-4 py-6 text-muted-foreground" colspan="7">

<!-- AFTER -->
<td class="px-4 py-6 text-muted-foreground" colspan="6">
```

```html
<!-- BEFORE (data row — name and slug cells) -->
<td class="px-4 py-3 font-medium">
    <div class="inline-flex items-center gap-2">
        <PanelTop class="size-4 text-muted-foreground" />
        {{ gondola.name }}
    </div>
</td>
<td class="px-4 py-3">{{ gondola.slug ?? '-' }}</td>

<!-- AFTER (merged into one cell) -->
<td class="px-4 py-3">
    <div class="inline-flex items-center gap-2">
        <PanelTop class="size-4 shrink-0 text-muted-foreground" />
        <ColumnLabel :label="gondola.name" :description="gondola.slug" />
    </div>
</td>
```

```html
<!-- BEFORE (actions cell) -->
<td class="px-4 py-3 text-right">
    <div class="inline-flex items-center gap-2">
        <!-- editor.planograms.gondolas -->
        <Button variant="outline" size="sm" as-child>
            <a
                target="_blank"
                :href="tenantEditorPlanogramGondolas.url({
                    subdomain: props.subdomain,
                    record:gondola.id,
                })"
            >
                {{ t('app.tenant.planograms.actions.view_gondolas') }}
            </a>
        </Button>
        <EditButton
            :href="GondolaController.edit.url({
                subdomain: props.subdomain,
                planogram: props.planogram.id,
                gondola: gondola.id,
            })"
        />
        <DeleteButton
            :href="GondolaController.destroy.url({
                subdomain: props.subdomain,
                planogram: props.planogram.id,
                gondola: gondola.id,
            })"
            :label="gondola.name"
            require-confirm-word
        />
    </div>
</td>

<!-- AFTER -->
<td class="px-4 py-3 text-right">
    <ColumnActions
        :edit-href="GondolaController.edit.url({ subdomain: props.subdomain, planogram: props.planogram.id, gondola: gondola.id })"
        :delete-href="GondolaController.destroy.url({ subdomain: props.subdomain, planogram: props.planogram.id, gondola: gondola.id })"
        :delete-label="gondola.name"
        :require-confirm-word="true"
    >
        <Button variant="outline" size="sm" as-child>
            <a target="_blank" :href="tenantEditorPlanogramGondolas.url({ subdomain: props.subdomain, record: gondola.id })">
                {{ t('app.tenant.planograms.actions.view_gondolas') }}
            </a>
        </Button>
    </ColumnActions>
</td>
```

- [ ] **Step 4: Verify TypeScript**

```bash
./vendor/bin/sail npm run types:check 2>&1 | grep "gondolas/Index"
```

Expected: no output

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/tenant/gondolas/Index.vue
git commit -m "refactor: migrate gondolas/Index.vue to shared column components"
```

---

### Task 11: Migrate stores/Index.vue

**Files:**
- Modify: `resources/js/pages/tenant/stores/Index.vue`

**What changes:**
- Add column component imports
- Remove `DeleteButton` and `EditButton` direct imports
- Name + Slug: merge into `<ColumnLabel>`, remove Slug column
- Actions cell: → `<ColumnActions>`
- Update thead: remove "Slug" `<th>`
- Update empty row colspan from `6` to `5`

- [ ] **Step 1: Update the script section**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import StoreController from '@/actions/App/Http/Controllers/Tenant/StoreController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnLabel } from '@/components/table/columns';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type StoreRow = {
    id: string;
    name: string | null;
    slug: string | null;
    code: string | null;
    document: string | null;
    status: 'draft' | 'published';
};

const props = defineProps<{
    subdomain: string;
    stores: Paginator<StoreRow>;
    filters: {
        search: string;
        status: string;
    };
}>();

const { t } = useT();
const storesIndexPath = StoreController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.stores.title'),
    title: t('app.tenant.stores.title'),
    description: t('app.tenant.stores.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.stores.navigation'), href: storesIndexPath },
    ],
});
</script>
```

- [ ] **Step 2: Update thead, colspan, and data cells**

```html
<!-- BEFORE thead -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">Slug</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.code') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.document') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.status') }}</th>
    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
</tr>

<!-- AFTER thead -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.code') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.document') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.status') }}</th>
    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
</tr>
```

```html
<!-- BEFORE empty row -->
<td class="px-4 py-6 text-muted-foreground" colspan="6">

<!-- AFTER -->
<td class="px-4 py-6 text-muted-foreground" colspan="5">
```

```html
<!-- BEFORE data cells -->
<td class="px-4 py-3 font-medium">{{ store.name ?? '-' }}</td>
<td class="px-4 py-3">{{ store.slug ?? '-' }}</td>
<td class="px-4 py-3">{{ store.code ?? '-' }}</td>
<td class="px-4 py-3">{{ store.document ?? '-' }}</td>
<td class="px-4 py-3">{{ store.status }}</td>
<td class="px-4 py-3 text-right">
    <div class="inline-flex items-center gap-2">
        <EditButton :href="StoreController.edit.url({ subdomain: props.subdomain, store: store.id })" />
        <DeleteButton
            :href="StoreController.destroy.url({ subdomain: props.subdomain, store: store.id })"
            :label="store.name ?? undefined"
            require-confirm-word
        />
    </div>
</td>

<!-- AFTER -->
<td class="px-4 py-3">
    <ColumnLabel :label="store.name ?? '-'" :description="store.slug" />
</td>
<td class="px-4 py-3">{{ store.code ?? '-' }}</td>
<td class="px-4 py-3">{{ store.document ?? '-' }}</td>
<td class="px-4 py-3">{{ store.status }}</td>
<td class="px-4 py-3 text-right">
    <ColumnActions
        :edit-href="StoreController.edit.url({ subdomain: props.subdomain, store: store.id })"
        :delete-href="StoreController.destroy.url({ subdomain: props.subdomain, store: store.id })"
        :delete-label="store.name ?? undefined"
        :require-confirm-word="true"
    />
</td>
```

- [ ] **Step 3: Verify + Commit**

```bash
./vendor/bin/sail npm run types:check 2>&1 | grep "stores/Index"
git add resources/js/pages/tenant/stores/Index.vue
git commit -m "refactor: migrate stores/Index.vue to shared column components"
```

---

### Task 12: Migrate providers/Index.vue

**Files:**
- Modify: `resources/js/pages/tenant/providers/Index.vue`

**What changes:**
- Add column component imports
- Remove `DeleteButton` and `EditButton` direct imports
- Name cell: use `<ColumnLabel :label="provider.name" :description="provider.code">` — removes separate Code column
- Actions cell: → `<ColumnActions>`
- Update thead: remove "Code" `<th>`
- Update empty row colspan from `6` to `5`

- [ ] **Step 1: Update the script section**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProviderController from '@/actions/App/Http/Controllers/Tenant/ProviderController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnLabel } from '@/components/table/columns';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type ProviderRow = {
    id: string;
    code: string | null;
    name: string | null;
    email: string | null;
    phone: string | null;
    cnpj: string | null;
    is_default: boolean;
};

const props = defineProps<{
    subdomain: string;
    providers: Paginator<ProviderRow>;
    filters: {
        search: string;
        is_default: string;
    };
}>();

const { t } = useT();
const providersIndexPath = ProviderController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.providers.title'),
    title: t('app.tenant.providers.title'),
    description: t('app.tenant.providers.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.providers.navigation'), href: providersIndexPath },
    ],
});
</script>
```

- [ ] **Step 2: Update thead, colspan, and data cells**

```html
<!-- BEFORE thead -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.code') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.cnpj') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.email') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.is_default') }}</th>
    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
</tr>

<!-- AFTER thead -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.cnpj') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.email') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.is_default') }}</th>
    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
</tr>
```

```html
<!-- BEFORE empty row -->
<td class="px-4 py-6 text-muted-foreground" colspan="6">

<!-- AFTER -->
<td class="px-4 py-6 text-muted-foreground" colspan="5">
```

```html
<!-- BEFORE data cells -->
<td class="px-4 py-3 font-medium">{{ provider.name ?? '-' }}</td>
<td class="px-4 py-3">{{ provider.code ?? '-' }}</td>
<td class="px-4 py-3">{{ provider.cnpj ?? '-' }}</td>
<td class="px-4 py-3">{{ provider.email ?? '-' }}</td>
<td class="px-4 py-3">{{ provider.is_default ? t('app.tenant.common.yes') : t('app.tenant.common.no') }}</td>
<td class="px-4 py-3 text-right">
    <div class="inline-flex items-center gap-2">
        <EditButton :href="ProviderController.edit.url({ subdomain: props.subdomain, provider: provider.id })" />
        <DeleteButton
            :href="ProviderController.destroy.url({ subdomain: props.subdomain, provider: provider.id })"
            :label="provider.name ?? undefined"
            require-confirm-word
        />
    </div>
</td>

<!-- AFTER -->
<td class="px-4 py-3">
    <ColumnLabel :label="provider.name ?? '-'" :description="provider.code" />
</td>
<td class="px-4 py-3">{{ provider.cnpj ?? '-' }}</td>
<td class="px-4 py-3">{{ provider.email ?? '-' }}</td>
<td class="px-4 py-3">{{ provider.is_default ? t('app.tenant.common.yes') : t('app.tenant.common.no') }}</td>
<td class="px-4 py-3 text-right">
    <ColumnActions
        :edit-href="ProviderController.edit.url({ subdomain: props.subdomain, provider: provider.id })"
        :delete-href="ProviderController.destroy.url({ subdomain: props.subdomain, provider: provider.id })"
        :delete-label="provider.name ?? undefined"
        :require-confirm-word="true"
    />
</td>
```

- [ ] **Step 3: Verify + Commit**

```bash
./vendor/bin/sail npm run types:check 2>&1 | grep "providers/Index"
git add resources/js/pages/tenant/providers/Index.vue
git commit -m "refactor: migrate providers/Index.vue to shared column components"
```

---

### Task 13: Migrate clusters/Index.vue + final verification

**Files:**
- Modify: `resources/js/pages/tenant/clusters/Index.vue`

**What changes:**
- Add column component imports
- Remove `DeleteButton` and `EditButton` direct imports
- Name + Slug: merge into `<ColumnLabel>`, remove separate Slug column
- Actions cell: → `<ColumnActions>`
- Update thead: remove "Slug" `<th>`
- Update empty row colspan from `6` to `5`

- [ ] **Step 1: Update the script section**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ClusterController from '@/actions/App/Http/Controllers/Tenant/ClusterController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnLabel } from '@/components/table/columns';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type ClusterRow = {
    id: string;
    store_id: string;
    store: string | null;
    name: string;
    slug: string | null;
    specification_1: string | null;
    status: 'draft' | 'published';
};

const props = defineProps<{
    subdomain: string;
    clusters: Paginator<ClusterRow>;
    filters: {
        search: string;
        status: string;
        store_id: string;
    };
    filter_options: {
        stores: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();
const clustersIndexPath = ClusterController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.clusters.title'),
    title: t('app.tenant.clusters.title'),
    description: t('app.tenant.clusters.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.clusters.navigation'), href: clustersIndexPath },
    ],
});
</script>
```

- [ ] **Step 2: Update thead, colspan, and data cells**

```html
<!-- BEFORE thead -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.store') }}</th>
    <th class="px-4 py-3 font-medium">Slug</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.specification_1') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.status') }}</th>
    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
</tr>

<!-- AFTER thead -->
<tr>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.name') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.store') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.specification_1') }}</th>
    <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.status') }}</th>
    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
</tr>
```

```html
<!-- BEFORE empty row -->
<td class="px-4 py-6 text-muted-foreground" colspan="6">

<!-- AFTER -->
<td class="px-4 py-6 text-muted-foreground" colspan="5">
```

```html
<!-- BEFORE data cells -->
<td class="px-4 py-3 font-medium">{{ cluster.name }}</td>
<td class="px-4 py-3">{{ cluster.store ?? '-' }}</td>
<td class="px-4 py-3">{{ cluster.slug ?? '-' }}</td>
<td class="px-4 py-3">{{ cluster.specification_1 ?? '-' }}</td>
<td class="px-4 py-3">{{ cluster.status }}</td>
<td class="px-4 py-3 text-right">
    <div class="inline-flex items-center gap-2">
        <EditButton :href="ClusterController.edit.url({ subdomain: props.subdomain, cluster: cluster.id })" />
        <DeleteButton
            :href="ClusterController.destroy.url({ subdomain: props.subdomain, cluster: cluster.id })"
            :label="cluster.name"
            require-confirm-word
        />
    </div>
</td>

<!-- AFTER -->
<td class="px-4 py-3">
    <ColumnLabel :label="cluster.name" :description="cluster.slug" />
</td>
<td class="px-4 py-3">{{ cluster.store ?? '-' }}</td>
<td class="px-4 py-3">{{ cluster.specification_1 ?? '-' }}</td>
<td class="px-4 py-3">{{ cluster.status }}</td>
<td class="px-4 py-3 text-right">
    <ColumnActions
        :edit-href="ClusterController.edit.url({ subdomain: props.subdomain, cluster: cluster.id })"
        :delete-href="ClusterController.destroy.url({ subdomain: props.subdomain, cluster: cluster.id })"
        :delete-label="cluster.name"
        :require-confirm-word="true"
    />
</td>
```

- [ ] **Step 3: Verify TypeScript — all project files**

```bash
./vendor/bin/sail npm run types:check 2>&1 | grep "resources/js/" | grep -v "^>" | grep -v "vendor/"
```

Expected: only pre-existing errors in `DeleteButton.vue`, `ImageUploadField.vue`, `ButtonWithTooltip.vue` — no new errors.

- [ ] **Step 4: Final commit**

```bash
git add resources/js/pages/tenant/clusters/Index.vue
git commit -m "refactor: migrate clusters/Index.vue to shared column components"
```
