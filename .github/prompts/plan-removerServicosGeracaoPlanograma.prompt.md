# Plan: Remover Serviços de Geração Automática de Planograma (somente pacote)

## TL;DR
Remover tudo relacionado a AutoGenerate, IAGenerate e SectionGenerate dentro do pacote. Nada em app/ será tocado. AutoGenerateModal.vue é mantido — será reutilizado pelo app/Services/AutoPlanogram.

---

## Fase 1 — PHP (pacote): Services, DTOs, Controller, Request, Agent, Command
*Todos paralelos entre si.*

1. Deletar dir `src/Services/Plannerate/AutoGenerate/`
2. Deletar dir `src/Services/Plannerate/IAGenerate/`
3. Deletar dir `src/Services/Plannerate/SectionGenerate/`
4. Deletar dir `src/DTOs/Plannerate/AutoGenerate/`
5. Deletar dir `src/DTOs/Plannerate/IAGenerate/`
6. Deletar dir `src/DTOs/Plannerate/SectionGenerate/`
7. Deletar `src/Http/Controllers/AutoPlanogramController.php`
8. Deletar `src/Http/Requests/Tenant/Plannerate/IAGeneratePlanogramRequest.php`
9. Deletar `src/Ai/Agents/PlanogramSectionAllocator.php`
10. Deletar `src/Commands/Plannerate/TestAutoGenerateCommand.php`

---

## Fase 2 — Service Provider do pacote (depende da Fase 1)

11. Deletar `src/Providers/AutoPlanogramServiceProvider.php` (namespace Callcocam\...)
12. Editar `src/LaravelRaptorPlannerateServiceProvider.php`:
    - Remover import de AutoPlanogramServiceProvider
    - Remover `$this->app->register(AutoPlanogramServiceProvider::class)` do packageRegistered()
    - Remover import e registro de TestAutoGenerateCommand do configurePackage()

NÃO TOCAR: app/Providers/AutoPlanogramServiceProvider.php, bootstrap/providers.php, app/Services/AutoPlanogram/

---

## Fase 3 — Config e Env (paralelo com Fase 2)

13. Editar `packages/callcocam/laravel-raptor-plannerate/config/plannerate.php` — remover chave `features.auto_generate` e bloco `auto_generate`
14. Remover `PLANNERATE_AUTO_GENERATE_ENABLED` do `.env` se presente

---

## Fase 4 — Frontend Vue (paralelo com Fases 2 e 3)

Caminhos relativos a `packages/callcocam/laravel-raptor-plannerate/resources/js/`:

15. MANTER `components/plannerate/header/AutoGenerateModal.vue` — reutilizado pelo app/Services/AutoPlanogram
16. Deletar `components/plannerate/header/AutoGenerateModalOld.vue`
17. Editar `resources/js/routes/api/tenant/plannerate/gondolas/index.ts` — remover exports `iaGenerate` e `generateBySections` (avaliar `autoGenerate` na execução: manter se o novo serviço do app usar a mesma rota)

---

## Fase 5 — Testes e Build

18. Buscar em `tests/Feature/` referências a IAGenerate/SectionGenerate do pacote e remover testes exclusivos
19. `vendor/bin/pint --dirty --format agent` nos arquivos PHP editados
20. Build: `docker compose exec -u root php php artisan wayfinder:generate --with-form && VITE_ENABLE_WAYFINDER=false npm run build`

---

## Banco de Dados — Nenhuma ação necessária
Sem tabelas dedicadas. Serviços usam tabelas compartilhadas.
