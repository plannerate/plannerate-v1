# Relatório de Auditoria de Migrations
Data: 2026-03-21 | Total analisado: 51 arquivos

## Resumo
- 🔴 Crítico: 44 ocorrências em 24 arquivos
- 🟡 Aviso: 18 ocorrências em 15 arquivos
- ✅ Sem problemas: 27 arquivos

> ⚠️ Todas as migrations já foram executadas — correções devem ser feitas via ALTER TABLE migrations.

---

## 🔴 CRÍTICOS — ID não-ULID

| Arquivo | Problema |
|---------|---------|
| create_activity_log_table | `uuid('id')` → `ulid('id')` |
| create_stores_table | `char('id', 26)` → `ulid('id')` |
| create_client_integrations_table | `char('id', 26)` → `ulid('id')` |
| create_clients_table | `char('id', 26)` → `ulid('id')` |
| create_clusters_table | `char('id', 26)` → `ulid('id')` |
| create_images_table | `char('id', 26)` → `ulid('id')` |
| create_jobs_table | `id()` → `ulid('id')` |
| create_notifications_table | `uuid('id')` → `ulid('id')` |
| create_agent_conversations_table | `string('id', 36)` + `foreignId()` |
| clients/create_categories_table | `char('id', 26)` |
| clients/create_gondolas_table | `char('id', 26)` |
| clients/create_gondola_zones_table | `char('id', 26)` |
| clients/create_layers_table | `char('id', 26)` |
| clients/create_monthly_sales_summaries_table | `char('id', 26)` |
| clients/create_planograms_table | `char('id', 26)` |
| clients/create_products_table | `char('id', 26)` |
| clients/create_product_analyses_table | `char('id', 26)` |
| clients/create_product_provider_table | `char(26)` direto |
| clients/create_product_store_table | `char(26)` direto |
| clients/create_providers_table | `char('id', 26)` |
| clients/create_purchases_table | `char('id', 26)` |
| clients/create_sales_table | `char('id', 26)` |
| clients/create_sections_table | `char('id', 26)` |
| clients/create_segments_table | `char('id', 26)` |
| clients/create_shelves_table | `char('id', 26)` |

## 🔴 CRÍTICOS — unique sem tenant_id

| Arquivo | Campo |
|---------|-------|
| create_stores_table | slug, code |
| create_users_table | email, slug |
| create_clients_table | slug |
| create_clusters_table | slug |
| create_images_table | slug |
| create_permissions_table | slug |
| create_roles_table | slug |
| create_tenant_domains_table | domain |
| create_flow_presets_table | slug |
| clients/create_gondolas_table | slug |
| clients/create_planograms_table | slug |
| clients/create_sections_table | code, slug |
| clients/create_shelves_table | code |

## 🟡 AVISOS — softDeletes via timestamp raw

| Arquivo | Observação |
|---------|-----------|
| create_stores_table | `timestamp('deleted_at')` em vez de `softDeletes()` |
| create_clients_table | idem |
| create_clusters_table | idem |
| create_images_table | idem |
| clients/create_gondolas_table | idem |
| clients/create_layers_table | idem |
| clients/create_planograms_table | idem |
| clients/create_products_table | idem |
| clients/create_providers_table | idem |
| clients/create_sales_table | idem |
| clients/create_sections_table | idem |
| clients/create_segments_table | idem |
| clients/create_shelves_table | idem |

## ✅ Sem problemas
addresses, cache, tenants, permission_role, permission_user, role_user,
translation_groups, translation_overrides, inspirations, sales_sync_logs,
flow_* (todos), gondola_analyses, mercadologico_reorganize_logs,
personal_access_tokens (token único global — aceitável)
