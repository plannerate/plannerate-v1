# Localização do Plannerate (Vendor Components)

## Resumo
Vamos migrar textos hardcoded do pacote `vendor/callcocam/laravel-raptor-plannerate/resources/js/components/plannerate` para i18n no frontend, usando namespace separado `plannerate.*` (fora de `lang/pt_BR/app.php`).  
Também vamos expor esse novo arquivo no `page.props.translations` para o `useT()` resolver as chaves.

## Mudanças de Implementação
- Rodar `search-docs` antes de editar, com foco em Inertia shared props e localization (conforme guideline do projeto).
- Criar `lang/pt_BR/plannerate.php` com estrutura hierárquica por domínio de UI (ex.: `header`, `toolbar`, `editor`, `sidebar`, `form`, `analysis`, `print`, `common`).
- Atualizar `app/Http/Middleware/HandleInertiaRequests.php` para incluir:
  - `'plannerate' => trans('plannerate')` dentro de `translations`.
- Nos componentes do escopo definido (`components/plannerate`):
  - Garantir `const { t } = useT()` onde faltar.
  - Substituir strings hardcoded visíveis por `t('plannerate....')`.
  - Padronizar placeholders, labels, títulos, mensagens vazias, estados de loading, botões e textos SR-only.
  - Manter textos dinâmicos vindos de API/back-end sem tradução local duplicada (só traduzir UI fixa).
- Organizar chaves para evitar colisão e facilitar manutenção (sem reutilizar `app.php`).

## Testes e Validação
- Executar testes focados em páginas/fluxos que usam Plannerate (mínimo necessário com `php artisan test --compact` por arquivo/filtro relevante).
- Validar manualmente os fluxos principais:
  - Kanban/Planogram carregando sem chaves literais na tela.
  - Toolbar, modais e painéis com textos traduzidos.
  - Placeholders e mensagens de estado aparecendo corretamente.
- Se houver alteração em PHP, rodar `vendor/bin/pint --dirty --format agent`.

## APIs/Interfaces e Impacto Público
- Nova fonte de tradução frontend: `plannerate` em `page.props.translations`.
- Novo contrato de chave no frontend: `t('plannerate...')` para textos do pacote Plannerate.
- Sem mudança de rotas, payloads de API ou schema de banco.

## Premissas
- Escopo desta etapa: apenas `vendor/.../resources/js/components/plannerate`.
- Namespace confirmado: `plannerate.*`.
- Não será criado arquivo de documentação.
