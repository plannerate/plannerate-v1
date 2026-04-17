# Alterações laravel-raptor - Preservar Dados do Formulário Após Erro

## Problema
Quando ocorre um erro de validação ou erro de banco de dados no formulário de criação, os dados digitados pelo usuário são perdidos.

---

## Alterações Necessárias

### 1. `src/Http/Controllers/AbstractController.php`

#### 1.1 Método `create()` - Adicionar `oldInput`

Após a linha:
```php
'form' => $this->form(Form::make($model, 'model')->defaultActions($this->getFormActions()))->render(),
```

Adicionar:
```php
'oldInput' => $request->old(),
```

#### 1.2 Método `handleStoreError()` - Adicionar `withErrors()`

**DE:**
```php
protected function handleStoreError(\Exception $e): BaseRedirectResponse
{
    report($e);

    return redirect()
        ->back()
        ->withInput()
        ->with('error', app()->environment('local') ? $e->getMessage() : 'Erro ao criar o item.');
}
```

**PARA:**
```php
protected function handleStoreError(\Exception $e): BaseRedirectResponse
{
    report($e);

    $message = app()->environment('local') ? $e->getMessage() : 'Erro ao criar o item.';

    return redirect()
        ->back()
        ->withInput()
        ->withErrors(['error' => $message])
        ->with('error', $message);
}
```

#### 1.3 Método `handleUpdateError()` - Mesmo padrão

**DE:**
```php
protected function handleUpdateError(\Exception $e, string $id): BaseRedirectResponse
{
    report($e);

    return redirect()
        ->back()
        ->withInput()
        ->with('error', app()->environment('local') ? $e->getMessage() : 'Erro ao atualizar o item.');
}
```

**PARA:**
```php
protected function handleUpdateError(\Exception $e, string $id): BaseRedirectResponse
{
    report($e);

    $message = app()->environment('local') ? $e->getMessage() : 'Erro ao atualizar o item.';

    return redirect()
        ->back()
        ->withInput()
        ->withErrors(['error' => $message])
        ->with('error', $message);
}
```

---

### 2. `resources/js/pages/admin/resource/CreatePage.vue`

#### 2.1 Interface Props - Adicionar `oldInput`

**DE:**
```typescript
interface Props {
  message?: string
  resourceLabel?: string
  breadcrumbs?: BackendBreadcrumb[]
  form?: {
    columns: FormColumn[]
    model?: Record<string, any>
    formActions?: any[]
  }
  pageHeaderActions?: any[],
  action?: string
}
```

**PARA:**
```typescript
interface Props {
  message?: string
  resourceLabel?: string
  breadcrumbs?: BackendBreadcrumb[]
  form?: {
    columns: FormColumn[]
    model?: Record<string, any>
    formActions?: any[]
  }
  pageHeaderActions?: any[]
  oldInput?: Record<string, any>
  action?: string
}
```

#### 2.2 Computed `initialData` - Priorizar `oldInput`

**DE:**
```typescript
const initialData = computed(() => {
  const data: Record<string, any> = {}

  props.form?.columns?.forEach(column => {
    if (column.default !== undefined && column.default !== null) {
      data[column.name] = column.default
    } else if (column.component === 'form-field-repeater') {
      // RepeaterField sempre deve iniciar como array vazio
      data[column.name] = []
    }
  })

  return data
})
```

**PARA:**
```typescript
const initialData = computed(() => {
  const data: Record<string, any> = {}

  props.form?.columns?.forEach(column => {
    // Prioriza old input (dados após erro de validação)
    if (props.oldInput && props.oldInput[column.name] !== undefined) {
      data[column.name] = props.oldInput[column.name]
    } else if (column.default !== undefined && column.default !== null) {
      data[column.name] = column.default
    } else if (column.component === 'form-field-repeater') {
      // RepeaterField sempre deve iniciar como array vazio
      data[column.name] = []
    }
  })

  return data
})
```

---

## Resultado
Após essas alterações, quando ocorrer um erro de validação ou erro de banco de dados:
1. Os dados do formulário serão preservados
2. O usuário verá a mensagem de erro
3. Os campos manterão os valores que foram digitados
