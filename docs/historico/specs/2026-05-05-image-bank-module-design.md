# Image Bank Module — Design Spec

**Date:** 2026-05-05  
**Status:** Approved

---

## Context

Product image resolution is currently slow and incomplete:
- `ProcessProductImages` only finds products with `url IS NULL`, missing products whose `url` points to a file that no longer exists on public storage.
- Each EAN is resolved independently per tenant — no sharing between tenants means the same EAN is looked up N times across N tenants.
- There is no global image cache, so repeated runs re-do all external API calls.
- The resolver always hits remote storage (DigitalOcean Spaces) before checking local disk.

This spec introduces a **global image cache in `EanReference.image_front_url`** and a new **`image-bank` module** to gate which tenants participate in image processing.

---

## Components

### 1. Module `image-bank`

| Field | Value |
|---|---|
| name | Banco de Imagens |
| slug | `image-bank` |
| description | Habilita o processamento e sincronização de imagens de produtos via repositório e fontes externas. |

**Files to create/modify:**
- `app/Support/Modules/ModuleSlug.php` — add `IMAGE_BANK = 'image-bank'`
- `database/seeders/LandlordPlansAndModulesSeeder.php` — add module record

---

### 2. Command `process-product-images` (rewrite)

**File:** `app/Console/Commands/ProcessProductImages.php`

Remove the `TenantAware` trait. The command handles its own tenant iteration.

**Signature:**
```
process-product-images
  {--ean=      : EAN específico para processar}
  {--tenant=*  : ID(s) do tenant (todos com image-bank se omitido)}
  {--force     : Reprocessa mesmo produtos com url válida}
```

**Execution flow per command run:**

```
1. Resolve tenant list:
   - If --tenant= provided → those tenants only
   - Otherwise → all active tenants with image-bank module active

2. Load global EanReference map (landlord):
   EAN (normalized) → image_front_url
   (one query, shared across all tenants in this run)

3. For each tenant:
   a. Connect to tenant database
   b. Find eligible products:
      - url IS NULL  →  always eligible
      - url IS NOT NULL AND Storage::disk('public')->exists(url) === false  →  eligible
      - If --ean= is set, filter to that EAN
   c. FAST PATH: products whose normalized EAN exists in EanReference map
      → Bulk UPDATE products SET url = image_front_url WHERE id IN (...)
      → No jobs, no API calls
   d. SLOW PATH: remaining products (EAN not in EanReference)
      → Dispatch DOProcessProductImageJob per product
```

**Progress output:** Show per-tenant summary: total eligible, fast-path updated, jobs dispatched.

---

### 3. `ProductRepositoryImageResolver` — updated priority chain

**File:** `app/Services/ProductRepositoryImageResolver.php`

New resolution order in `resolveByEan()`:

| Priority | Source | Action |
|---|---|---|
| 1 | `EanReference.image_front_url` | Return immediately if present — no I/O |
| 2 | Public disk (`Storage::disk('public')`) | File already exists locally |
| 3 | DigitalOcean `.webp` | Copy to public disk |
| 4 | DigitalOcean `.png` | Convert to webp, save to public disk |
| 5 | Web (OpenFoodFacts / Open Beauty Facts / etc.) | Download, convert, save to public disk |

**After finding image at priority 2–5:**
- Save path to `EanReference.image_front_url` via `DB::connection('landlord')` upsert on `ean` column.

**New method:** `protected function saveToEanReference(string $ean, string $path): void`
- Uses `DB::connection('landlord')->table('ean_references')->updateOrInsert(['ean' => $normalizedEan], ['image_front_url' => $path, 'updated_at' => now()])`

> Note: `resolveForProduct()` already calls `resolveByEan()` — no interface change needed for the job.

---

### 4. `DOProcessProductImageJob` — no interface change

The job calls `resolveForProduct()` which calls `resolveByEan()` which now handles EanReference lookup and save. The job itself does not change.

---

## Data Flow Diagram

```
Command run
  │
  ├─ Load EanReference map (landlord, 1 query)
  │
  └─ For each tenant:
       │
       ├─ Find eligible products (url null or file missing)
       │
       ├─ FAST PATH (EAN in EanReference map)
       │    └─ Bulk SQL UPDATE → done
       │
       └─ SLOW PATH (EAN unknown)
            └─ Dispatch DOProcessProductImageJob
                 └─ resolveByEan()
                      ├─ 1. EanReference cache → found → return
                      ├─ 2. Public disk → found → save to EanReference → return
                      ├─ 3. DO .webp → found → copy local → save to EanReference → return
                      ├─ 4. DO .png → found → convert → save to EanReference → return
                      └─ 5. Web → found → download → save to EanReference → return (or null)
```

---

## Key Design Decisions

- **EanReference as global cache**: resolving an EAN once populates the landlord cache for all future runs across all tenants. After the first full run, most products are updated via fast path.
- **Tenant isolation**: only tenants with `image-bank` module active participate. Other tenants are untouched.
- **File existence check**: `Storage::disk('public')->exists()` is a local filesystem stat — fast even for thousands of products.
- **No interface changes to Job**: the resolver change is backward-compatible.

---

## Verification

1. Add `image-bank` module to a test tenant in the landlord DB.
2. Run: `php artisan process-product-images --tenant=<id> --ean=<known-ean>`
3. Confirm: product `url` updated, `ean_references.image_front_url` populated.
4. Run again: confirm fast path is taken (no jobs dispatched for same EAN).
5. Run without `--tenant`: confirm only tenants with `image-bank` module are processed.
6. Check `storage/logs/laravel.log` for any resolver errors.
