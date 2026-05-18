# Guia de Traduções - Planograma

## Visão Geral
Todas as traduções dos componentes de planograma foram centralizadas em `lang/pt_BR/planogram-templates.php`.

## Estrutura das Traduções

As traduções estão organizadas em seções por componente:

```php
[
    'fields'          => [],  // PlanogramTemplateFormFields.vue
    'grid'            => [],  // GondolaGrid.vue
    'slot_editor'     => [],  // SlotEditorModal.vue
    'slot_card'       => [],  // SlotCard.vue
    'product_search'  => [],  // ProductSearchPanel.vue
    'product_table'   => [],  // TemplateProductTable.vue
    'messages'        => [],  // Mensagens gerais
]
```

## Como Usar nos Componentes

### Método 1: Com `useT()` (Recomendado)

```vue
<script setup lang="ts">
import { useT } from '@/composables/useT';

const { t } = useT();
</script>

<template>
  <!-- Acessar traduções -->
  <h1>{{ t('planogram-templates.grid.module_label') }}1</h1>
  <button>{{ t('planogram-templates.slot_editor.save_button') }}</button>
</template>
```

### Método 2: Em JavaScript/TypeScript

```typescript
import { useT } from '@/composables/useT';

const { t } = useT();
const label = t('planogram-templates.product_table.columns.ean');
```

## Exemplos de Uso

### GondolaGrid.vue
```vue
<!-- Antes (hardcoded) -->
<div>Módulo #{{ m }}</div>
<div>Prat #{{ shelf }}</div>
<button>Adicionar</button>

<!-- Depois (com traduções) -->
<div>{{ t('planogram-templates.grid.module_label') }}{{ m }}</div>
<div>{{ t('planogram-templates.grid.shelf_label') }}{{ shelf }}</div>
<button>{{ t('planogram-templates.grid.add_button') }}</button>
```

### ProductSearchPanel.vue
```vue
<!-- Antes (hardcoded) -->
<Input placeholder="Buscar por EAN, nome ou marca..." />
<SelectValue placeholder="Grouping de destino" />
<p>Configure os slots (etapa 2) primeiro</p>

<!-- Depois (com traduções) -->
<Input :placeholder="t('planogram-templates.product_search.search_placeholder')" />
<SelectValue :placeholder="t('planogram-templates.product_search.grouping_placeholder')" />
<p>{{ t('planogram-templates.product_search.no_groupings_hint') }}</p>
```

### SlotEditorModal.vue
```vue
<!-- Antes (hardcoded) -->
<DialogTitle>Configurar slot — Módulo #{{ moduleNumber }}, Prat #{{ shelfOrder }}</DialogTitle>

<!-- Depois (com traduções) -->
<DialogTitle>
  {{ t('planogram-templates.slot_editor.title') }} — 
  {{ t('planogram-templates.slot_editor.module') }}{{ moduleNumber }}, 
  {{ t('planogram-templates.slot_editor.shelf') }}{{ shelfOrder }}
</DialogTitle>
```

### SlotCard.vue
```vue
<!-- Antes (hardcoded) -->
<span>↑ preço</span> (para asc)

<!-- Depois (com traduções) -->
<span>{{ t(`planogram-templates.slot_card.price_order.${slot.price_order}`) }}</span>
```

### TemplateProductTable.vue
```vue
<!-- Antes (hardcoded) -->
<p>{{ products.length }} produto{{ products.length !== 1 ? 's' : '' }} no template</p>

<!-- Depois (com traduções) -->
<p>
  {{ products.length }} 
  {{ t(`planogram-templates.product_table.count_${products.length === 1 ? 'singular' : 'plural'}`) }}
</p>
```

## Checklist de Implementação

- [ ] `GondolaGrid.vue` - atualizar labels de módulo e prateleira
- [ ] `PlanogramTemplateFormFields.vue` - confirmado ✓ (já usa `useT()`)
- [ ] `ProductSearchPanel.vue` - atualizar placeholder e mensagens
- [ ] `SlotCard.vue` - atualizar labels de preço
- [ ] `SlotEditorModal.vue` - atualizar todos os labels e placeholder
- [ ] `TemplateProductTable.vue` - atualizar labels de tabela e mensagens

## Notas

1. **Valores dinâmicos**: Sempre que houver números ou valores variáveis, mantenha-os fora das traduções
2. **Pluralização**: Para plurais (como "produto/produtos"), use chaves separadas no arquivo de tradução
3. **Enums**: Para opções de select (preço, tamanho, exposição), agrupe em um objeto dentro da seção do componente
4. **Consistência**: Mantenha a estrutura de chaves aninhadas para fácil localização

## Arquivo de Referência
- `lang/pt_BR/planogram-templates.php` - arquivo principal de traduções
- `resources/js/components/planogram-templates/` - componentes a atualizar
