Descrevendo é essencialmente um **schema de mapeamento declarativo** — em vez de código imperativo por provider, você define *regras* de extração e a engine resolve.

Isso é um padrão bem sólido. Vou te mostrar como ficaria:

---

## A ideia: `FieldResolver` com regras declarativas

```php
// Cada provider declara seu schema assim:
'codigo_erp' => ['produto', 'id', 'codigo'],
'ean'        => ['gtins.completo[principal=S].gtin', 'gtins.completo[0].gtin', 'ean'],
'brand'      => ['marca.descricao', 'marca'],
```

A engine tenta cada path em ordem, retorna o primeiro que resolver um valor não-nulo.

---

## As peças

**1. O schema de cada provider (array puro, sem lógica):**

```php
// config/integrations/product_maps.php  ou  uma classe por provider

class SysmoProductMap implements ProductMapInterface
{
    public function fields(): array
    {
        return [
            'codigo_erp'  => ['produto', 'id', 'codigo'],
            'ean'         => ['gtins.completo[principal=S].gtin', 'gtins.completo.0.gtin', 'ean'],
            'name'        => ['descricao', 'nome'],
            'brand'       => ['marca.descricao', 'marca'],
            'unit_measure'       => ['unidade_venda.codigo'],
            'measurement_unit'   => ['unidade_venda.descricao'],
            'current_stock'      => ['estoque.disponivel'],
            'last_purchase_date' => ['fornecedores[max:data_ultima_compra]'],  // agregação
            'sales_status'       => ['cadastro_ativo', 'status'],
        ];
    }

    public function filters(): array   // validações de exclusão
    {
        return [
            'cadastro_ativo'     => fn($v) => strtoupper((string)$v) !== 'N',
            'ativo_na_empresa'   => fn($v) => strtoupper((string)$v) !== 'N',
            'pertence_ao_mix'    => fn($v) => strtoupper((string)$v) !== 'N',
        ];
    }
}
```

**2. O `FieldResolver` — a engine:**

```php
class FieldResolver
{
    public function resolve(array $item, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->resolvePath($item, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    private function resolvePath(array $data, string $path): mixed
    {
        // "marca.descricao"  → dot notation
        // "gtins.completo[principal=S].gtin"  → array filter + dot
        // "fornecedores[max:data_ultima_compra]" → agregação

        // 1. agregação: [max:campo]
        if (preg_match('/^(.+)\[max:(.+)\]$/', $path, $m)) {
            $list = data_get($data, $m[1]);
            return $this->resolveMax($list, $m[2]);
        }

        // 2. filtro: [campo=valor]
        if (preg_match('/^(.+)\[(\w+)=(\w+)\]\.(.+)$/', $path, $m)) {
            $list = data_get($data, $m[1]);
            return $this->resolveFilter($list, $m[2], $m[3], $m[4]);
        }

        // 3. dot notation simples (Laravel helper)
        return data_get($data, $path);
    }

    private function resolveFilter(mixed $list, string $key, string $expected, string $field): mixed
    {
        if (!is_array($list)) return null;

        foreach ($list as $entry) {
            if (is_array($entry) && strtoupper((string)($entry[$key] ?? '')) === strtoupper($expected)) {
                return $entry[$field] ?? null;
            }
        }
        return null;
    }

    private function resolveMax(mixed $list, string $field): mixed
    {
        if (!is_array($list)) return null;

        return collect($list)
            ->filter(fn($e) => is_array($e) && isset($e[$field]))
            ->max(fn($e) => $e[$field]);
    }
}
```

**3. O `mapItem` vira genérico:**

```php
private function mapItem(string $provider, array $item): array
{
    $map = $this->resolveMap($provider); // retorna o ProductMapInterface
    $result = [];

    foreach ($map->fields() as $field => $paths) {
        $result[$field] = $this->fieldResolver->resolve($item, (array) $paths);
    }

    return $result;
}

private function passesProviderValidation(string $provider, array $mapped, array $raw): bool
{
    $map = $this->resolveMap($provider);

    foreach ($map->filters() as $rawField => $fn) {
        if (array_key_exists($rawField, $raw) && !$fn($raw[$rawField])) {
            return false;
        }
    }

    return $this->normalizeString($mapped['name'] ?? null) !== null;
}
```

---

## O que você ganha

| Antes | Depois |
|---|---|
| Novo provider = novo método `mapXyzItem()` | Novo provider = nova classe com array declarativo |
| Lógica espalhada em PHP imperativo | Regras legíveis como configuração |
| Testar requer mocks complexos | Testar o schema é só comparar arrays |
| `FieldResolver` não existe | Reutilizável em qualquer contexto |

---

## O que vale refinar

- **Paths mais complexos** (ex: concatenar dois campos) → você pode aceitar um `callable` no lugar de string no array de paths
- **Transformações** → um `transformers()` no map, tipo `'current_stock' => fn($v) => (float)$v`
- **Schemas em config/YAML** → se quiser sem código PHP por provider, mas aí perde o type-safety

O núcleo da ideia é sólido. Quer que eu esboce a estrutura completa com a refatoração do `PersistImportedProductsService` integrada?