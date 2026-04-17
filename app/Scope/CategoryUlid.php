<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Scope;

use Callcocam\LaravelRaptor\Support\Import\Contracts\GeneratesImportId;

class CategoryUlid implements GeneratesImportId
{
    /**
     * Gera um ULID determinístico para Category baseado em tenant_id + caminho hierárquico.
     *
     * Para importação hierárquica, espera que $row contenha:
     * - tenant_id: ID do tenant
     * - hierarchy_path: Caminho completo (ex: "Varejo > Eletro > TV") OU
     * - Colunas hierárquicas individuais (segmento, departamento, categoria, etc.)
     *
     * Exemplos:
     * - "Varejo" -> tenant_123 + "varejo" -> ID único
     * - "Varejo > Eletro" -> tenant_123 + "varejo>eletro" -> ID único
     * - "Varejo > Eletro > TV" -> tenant_123 + "varejo>eletro>tv" -> ID único
     */
    public function generate(array $row): string
    {
        $tenantId = $row['tenant_id'] ?? null;

        if (! $tenantId) {
            throw new \InvalidArgumentException('tenant_id é obrigatório para gerar o ULID da categoria.');
        }

        // Opção 1: hierarchy_path já montado (ex: "Varejo > Eletro > TV")
        if (isset($row['hierarchy_path']) && ! empty($row['hierarchy_path'])) {
            $hierarchyPath = $row['hierarchy_path'];
        } else {
            // Opção 2: monta o caminho a partir das colunas hierárquicas
            $hierarchyPath = $this->buildHierarchyPath($row);
        }

        if (empty($hierarchyPath)) {
            throw new \InvalidArgumentException('Não foi possível determinar o caminho hierárquico da categoria.');
        }

        // Normaliza o caminho (lowercase, remove espaços extras)
        $normalizedPath = strtolower(trim($hierarchyPath));

        // Chave única baseada em tenant + caminho completo
        $uniqueKey = $tenantId.'|'.$normalizedPath;

        // Gerar hash determinístico que sempre produz o mesmo resultado
        $hash = md5($uniqueKey);

        // Criar ID determinístico baseado APENAS no hash (sem time)
        // Usa prefixo fixo + hash para garantir formato ULID de 26 chars
        $prefix = 'C1'; // Prefixo fixo para categories (C=Category, 1=versão)
        $hashComponent = strtoupper(substr($hash, 0, 24)); // 24 chars restantes

        return $prefix.$hashComponent;
    }

    /**
     * Monta o caminho hierárquico a partir das colunas da linha.
     * Pega todas as colunas que não são metadados e concatena os valores não-vazios.
     */
    protected function buildHierarchyPath(array $row): string
    {
        // Lista de campos a ignorar (metadados, não são níveis da hierarquia)
        $ignoredFields = [
            'id',
            'tenant_id',
            'user_id',
            'category_id',
            'slug',
            'status',
            'description',
            'nivel',
            'level_name',
            'hierarchy_position',
            'full_path',
            'hierarchy_path',
            'is_placeholder',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $pathParts = [];

        foreach ($row as $key => $value) {
            // Ignora metadados e valores vazios
            if (in_array($key, $ignoredFields, true)) {
                continue;
            }

            if ($value !== null && $value !== '' && trim($value) !== '') {
                $pathParts[] = trim($value);
            }
        }

        return implode(' > ', $pathParts);
    }
}
