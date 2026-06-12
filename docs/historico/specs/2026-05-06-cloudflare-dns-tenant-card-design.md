# Cloudflare DNS Card — Tenant Form

**Date:** 2026-05-06  
**Status:** Approved

## Context

The `CloudflareService` exists and is fully functional but completely disconnected from the tenant management system. When a tenant is created with a `host` (subdomain), no DNS record is created in Cloudflare automatically. Landlord admins have no visibility or control over whether a CNAME record exists for the tenant's subdomain. This feature adds a DNS status card to the tenant edit form with create/delete actions, using Inertia v3 deferred props so the page load is not blocked by the Cloudflare API call.

DNS record type: **CNAME** pointing `host` → `CLOUDFLARE_CNAME_TARGET` (e.g. `cliente.app.com` → `app.com`). CNAME is preferred over A record because a single target update propagates to all tenants automatically if the app server changes.

## Architecture

### Backend — new pieces

**`config/cloudflare.php`**
```php
return [
    'api_token'   => env('CLOUDFLARE_API_TOKEN'),
    'zone_id'     => env('CLOUDFLARE_ZONE_ID'),
    'cname_target'=> env('CLOUDFLARE_CNAME_TARGET'),
    'base_uri'    => env('CLOUDFLARE_BASE_URI', 'https://api.cloudflare.com/client/v4'),
];
```
Also add these four vars to `.env.example`.

**`app/Http/Controllers/Landlord/TenantCloudflareController.php`**
- `store(Tenant $tenant)` — authorizes `update` on tenant, guards against missing config/host, calls `CloudflareService::createRecord()` with type `CNAME`, name = `$host`, content = `config('cloudflare.cname_target')`. Returns redirect back with Inertia flash toast.
- `destroy(Tenant $tenant)` — authorizes `update` on tenant, calls `CloudflareService::listRecords($zoneId, 'CNAME', $host)` to get the record ID, then calls `deleteRecord()`. Returns redirect back with flash toast.
- Both methods return early (with error toast) when CloudflareService is not configured or host is empty.

**`routes/web.php`** — inside existing landlord middleware group:
```php
Route::post('tenants/{tenant}/cloudflare', [TenantCloudflareController::class, 'store'])
    ->name('landlord.tenants.cloudflare.store');
Route::delete('tenants/{tenant}/cloudflare', [TenantCloudflareController::class, 'destroy'])
    ->name('landlord.tenants.cloudflare.destroy');
```

**`TenantController::edit()`** — append deferred prop:
```php
'cloudflare_record' => Inertia::defer(function () use ($tenant): ?array {
    // returns null if not configured
    // returns ['exists' => false] if no record found
    // returns ['exists' => true, 'id' => '...', 'name' => '...', 'content' => '...'] if found
}),
```

### Frontend — new pieces

**`resources/js/components/CloudflareDnsCard.vue`**
- Props: `cloudflareRecord: CloudflareRecord | null | undefined`, `createHref: string`, `deleteHref: string`
- Type:
  ```ts
  type CloudflareRecord =
    | { exists: false }
    | { exists: true; id: string; name: string; content: string }
  ```
- When `cloudflareRecord === undefined` (deferred still loading): show skeleton card
- When `cloudflareRecord === null` (not configured): render nothing (`v-if`)
- When `exists === false`: amber badge "Sem registro DNS" + "Criar CNAME" button that opens a confirmation Dialog (same style as `DeleteButton.vue`) showing `host → cname_target`. Uses `useForm({}).post(createHref)`.
- When `exists === true`: green badge "DNS Ativo" + record details (`name → content`) + "Remover" button using Dialog with destructive confirm. Uses `useForm({}).delete(deleteHref)`.

**`resources/js/pages/landlord/tenants/Form.vue`**
- Add `cloudflare_record: CloudflareRecord | null | undefined` to `defineProps`
- Wrap with `<Deferred data="cloudflare_record">` template slot to pass loading state
- Insert `<CloudflareDnsCard>` inside `<FormCard>`, positioned above the Domain section
- Only render when `isEdit` is true and `tenant.host` is set

## Data Flow

```
1. GET /landlord/tenants/{id}/edit
   → TenantController.edit() renders Form with cloudflare_record deferred

2. Inertia fetches deferred props (background XHR)
   → CloudflareService.listRecords(zoneId, 'CNAME', host)
   → Returns exists: true/false with record data

3. CloudflareDnsCard renders status

4a. User clicks "Criar CNAME" → confirm dialog → form.post(createHref)
   → TenantCloudflareController.store() → CloudflareService.createRecord()
   → redirect back → deferred prop re-fetches → card updates to "DNS Ativo"

4b. User clicks "Remover" → confirm dialog → form.delete(deleteHref)
   → TenantCloudflareController.destroy() → listRecords to get ID → deleteRecord()
   → redirect back → deferred prop re-fetches → card updates to "Sem registro DNS"
```

## Error Handling

- Cloudflare not configured (`api_token` or `zone_id` empty): deferred returns `null`, card hidden
- Tenant has no `host`: deferred returns `null`, card hidden
- Cloudflare API error on create/delete: controller catches exception, returns redirect back with error toast
- Record not found on delete: controller returns redirect back with warning toast

## Files to Create/Modify

| File | Action |
|------|--------|
| `config/cloudflare.php` | Create |
| `.env.example` | Modify (add 4 vars) |
| `app/Http/Controllers/Landlord/TenantCloudflareController.php` | Create |
| `routes/web.php` | Modify (add 2 routes) |
| `app/Http/Controllers/Landlord/TenantController.php` | Modify (add deferred prop to `edit()`) |
| `resources/js/components/CloudflareDnsCard.vue` | Create |
| `resources/js/pages/landlord/tenants/Form.vue` | Modify (prop + Deferred + card) |
| `tests/Feature/Landlord/TenantCloudflareControllerTest.php` | Create |

## Wayfinder

After creating `TenantCloudflareController`, run:
```bash
php artisan wayfinder:generate
```
This generates `resources/js/actions/App/Http/Controllers/Landlord/TenantCloudflareController.ts` with typed `.store()` and `.destroy()` functions for use in the Vue component.

## Tests

`TenantCloudflareControllerTest.php` covers:
- `store`: creates record when configured and host exists → 302 + success toast
- `store`: returns error toast when Cloudflare not configured
- `store`: returns error toast when tenant has no host
- `destroy`: deletes record when it exists → 302 + success toast
- `destroy`: returns warning toast when record not found in Cloudflare
- Both: unauthorized if user lacks `update` permission on tenant

Use `CloudflareService` mock/fake in tests — do not call real Cloudflare API.

## Verification

1. Set `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ZONE_ID`, `CLOUDFLARE_CNAME_TARGET` in `.env`
2. Open edit page for a tenant with a `host` set — card should appear with skeleton then resolve
3. If no record: "Sem registro DNS" badge + "Criar CNAME" button visible
4. Click "Criar CNAME" → confirm → card should update to "DNS Ativo"
5. Click "Remover" → confirm → card should update to "Sem registro DNS"
6. Remove Cloudflare config → card should be hidden
7. Run `php artisan test --compact --filter=TenantCloudflareController`
