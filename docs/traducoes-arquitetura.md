# Arquitetura de Traduções (Back + Front)

Como as traduções são organizadas, alimentadas do backend para o frontend e
consumidas nos componentes Vue.

> Para o guia específico das chaves de planograma, veja
> [`traducoes-planograma.md`](./traducoes-planograma.md). Este documento cobre
> a **infraestrutura** geral.

---

## 1. Visão geral do fluxo

```
lang/pt_BR/**.php  ──(MergingFileLoader)──►  trans('grupo')
        │                                          │
        │                                  HandleInertiaRequests::share()
        │                                          │  prop 'translations'
        ▼                                          ▼
   PHP backend                              page.props.translations
   __('app.tenant.x')                              │
                                              useT() → t('app.tenant.x')
                                                     │
                                                 Componente Vue
```

A **mesma** árvore de traduções alimenta o backend (via `__()` / `trans()`) e o
frontend (via `useT()`), a partir dos arquivos em `lang/{locale}/`.

---

## 2. Backend — organização dos arquivos

### 2.1 Estrutura de pastas

Locale padrão: **`pt_BR`** (`config('app.locale')`, fallback `en`).

```
lang/pt_BR/
├── app.php                      # chaves "soltas" e seções pequenas (welcome, dashboard, actions...)
├── app/                         # subdivisão do grupo "app"
│   ├── landlord/                # CRUDs do painel landlord (1 arquivo por recurso)
│   │   ├── tenants.php          #   → app.landlord.tenants.*
│   │   ├── users.php            #   → app.landlord.users.*
│   │   └── ...
│   └── tenant/                  # CRUDs do contexto tenant (1 arquivo por recurso)
│       ├── products.php         #   → app.tenant.products.*
│       ├── categories.php       #   → app.tenant.categories.*
│       └── ...
├── plannerate/                  # editor de planograma, fatiado por seção
│   ├── toolbar.php              #   → plannerate.toolbar.*
│   ├── header.php               #   → plannerate.header.*
│   ├── form.php, print.php, analysis.php, sidebar.php, ...
│   └── share_qr.php
├── planogram-templates.php
├── validation.php, auth.php, passwords.php, pagination.php, site.php
```

### 2.2 Convenção de chaves

A chave sempre reflete o **caminho do arquivo + as chaves internas**, com `.`:

| Arquivo                                   | Chave de acesso                      |
| ----------------------------------------- | ------------------------------------ |
| `lang/pt_BR/app.php` → `'dashboard'`      | `app.dashboard`                      |
| `lang/pt_BR/app/tenant/products.php` → `'title'` | `app.tenant.products.title`   |
| `lang/pt_BR/plannerate/toolbar.php` → `'zones'`  | `plannerate.toolbar.zones`    |

Padrão geral: `app.{contexto}.{recurso}.{campo}` (ex.: `app.tenant.categories.navigation`).

### 2.3 O `MergingFileLoader` (peça-chave)

**Problema:** o Laravel nativo **não** mescla subpastas. `__('app.tenant.x')`
(notação de ponto) lê apenas o arquivo plano `lang/pt_BR/app.php` — ele
desconhece `lang/pt_BR/app/tenant/products.php` (que só seria acessível como
`__('app/tenant/products.x')`, com barra). Ou seja, dividir um grupo em
subpastas quebraria todos os `__('app.tenant....')` espalhados pelo backend
(controllers, navegação, mensagens).

**Solução:** [`app/Support/Translation/MergingFileLoader.php`](../app/Support/Translation/MergingFileLoader.php)
estende o `FileLoader` do Laravel. Ao carregar um grupo (ex.: `app`), além do
arquivo `app.php` ele mescla **recursivamente** o diretório homônimo
`app/` — cada `.php` vira uma chave, cada subdiretório um nível aninhado.

Registro em [`AppServiceProvider::register()`](../app/Providers/AppServiceProvider.php)
via `extend('translation.loader', ...)`, preservando `paths`, `jsonPaths` e
`namespaces` do loader original (não perde traduções do framework/pacotes).

Resultado: a notação de ponto volta a funcionar de forma transparente, **tanto
no backend quanto nas props do Inertia**:

```php
__('app.tenant.products.title');          // "Produtos"
__('app.tenant.gondolas.messages.created'); // funciona mesmo estando em subpasta
trans('app');                              // árvore completa já mesclada
```

---

## 3. Backend → Frontend — como é alimentado

As traduções são compartilhadas como **prop global do Inertia** em
[`HandleInertiaRequests::share()`](../app/Http/Middleware/HandleInertiaRequests.php):

```php
'translations' => fn (): array => [
    'app'                  => trans('app'),
    'plannerate'           => trans('plannerate'),
    'auth'                 => trans('auth'),
    'passwords'            => trans('passwords'),
    'pagination'           => trans('pagination'),
    'validation'           => trans('validation'),
    'planogram-templates'  => trans('planogram-templates'),
    'site'                 => trans('site'),
],
'locale' => app()->getLocale(),
```

- Cada `trans('grupo')` retorna a **árvore inteira** daquele grupo (já mesclada
  pelo `MergingFileLoader`).
- É uma **closure** (`fn () => ...`) → avaliada por requisição, de forma
  preguiçosa, no momento em que o Inertia monta a resposta.
- O resultado fica disponível no front em `page.props.translations`.

> **Adicionar um novo grupo ao front:** inclua uma linha `'grupo' => trans('grupo')`
> nessa lista. Sem isso, o grupo existe no backend mas não chega ao Vue.

---

## 4. Frontend — como é consumido

### 4.1 O composable `useT()`

[`resources/js/composables/useT.ts`](../resources/js/composables/useT.ts) lê
`page.props.translations` e navega pela chave dividindo por `.`:

```ts
const { t } = useT();
t('app.tenant.products.title');                  // "Produtos"
t('app.created_success', { resource: 'Produto' }); // interpola :resource
```

- Não há `vue-i18n` no projeto — `useT()` é uma implementação própria, leve.
- Se a chave não existir, retorna a **própria chave** (fallback visível, útil
  para detectar tradução faltando — foi o que apareceu na tela como
  `app.tenant.products.title` durante a migração).
- Placeholders `:nome` são substituídos pelo segundo argumento.

### 4.2 Uso em componentes Vue

```vue
<script setup lang="ts">
import { useT } from '@/composables/useT';
const { t } = useT();
</script>

<template>
  <h1>{{ t('app.tenant.products.title') }}</h1>
  <button :title="t('plannerate.toolbar.toggle_zones')">
    {{ t('plannerate.toolbar.zones') }}
  </button>
</template>
```

**Regra do projeto:** nunca escrever texto fixo em Vue ou PHP — sempre usar
chave de tradução PT-BR via `useT()` (front) ou `__()` (back).

### 4.3 Labels resolvidas no backend

Nem tudo é traduzido no front. A **navegação do sidebar**, por exemplo, é
montada server-side em `SidebarNavigationService` usando `__('app.tenant.x.navigation')`
e enviada já traduzida na prop `navigation`. Por isso o `MergingFileLoader` é
essencial: sem ele, essas labels server-side apareceriam como chaves cruas.

---

## 5. Como adicionar/dividir traduções

### Adicionar uma chave nova
1. Edite (ou crie) o arquivo apropriado em `lang/pt_BR/...`.
2. Use no back com `__('grupo.caminho.chave')` ou no front com `t('grupo.caminho.chave')`.
3. Nada mais a registrar — o loader descobre arquivos/subpastas por glob.

### Dividir um arquivo grande em subpasta
1. Crie `lang/pt_BR/{grupo}/{secao}.php` retornando um array.
2. Mova o bloco correspondente para lá (a chave passa a ser `grupo.secao.*`).
3. Garanta que o grupo está listado no `share()` (item 3) se precisar no front.
4. As chaves de ponto (`__('grupo.secao.x')`) continuam funcionando graças ao
   `MergingFileLoader`.

### Validar (sempre via Docker)
```bash
# A chave resolve no backend (deve retornar o texto, não a chave)?
docker compose exec php php artisan tinker --execute 'echo __("app.tenant.products.title");'
```
Para mudanças aparecerem no browser, as traduções vêm por props em runtime —
**não precisam de rebuild**. Basta um hard refresh (Ctrl+Shift+R).

---

## 6. Arquivos-chave (referência rápida)

| Arquivo | Papel |
| --- | --- |
| `lang/pt_BR/**/*.php` | Fonte das traduções (arquivos planos + subpastas) |
| [`app/Support/Translation/MergingFileLoader.php`](../app/Support/Translation/MergingFileLoader.php) | Mescla subpastas no grupo; faz a notação de ponto funcionar |
| [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) | Registra o loader via `extend('translation.loader')` |
| [`app/Http/Middleware/HandleInertiaRequests.php`](../app/Http/Middleware/HandleInertiaRequests.php) | Compartilha `translations` e `locale` como props Inertia |
| [`resources/js/composables/useT.ts`](../resources/js/composables/useT.ts) | Consome `page.props.translations` no front via `t()` |
