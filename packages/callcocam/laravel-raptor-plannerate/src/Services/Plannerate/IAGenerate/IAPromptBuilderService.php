<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\IAGenerate;

use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\IAGenerate\IAGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\IAGenerate\PlanogramContextDTO;

/**
 * Serviço de Construção de Prompts para IA
 * Responsável por criar prompts estruturados e eficazes para geração de planogramas
 */
class IAPromptBuilderService
{
    /**
     * Construir prompt completo para geração de planograma
     */
    public function buildPrompt(
        PlanogramContextDTO $context,
        IAGenerateConfigDTO $config
    ): string {
        $systemPrompt = $this->buildSystemPrompt();
        $contextPrompt = $this->buildContextPrompt($context);
        $rulesPrompt = $this->buildRulesPrompt($config);
        $outputPrompt = $this->buildOutputFormatPrompt();

        return implode("\n\n", [
            $systemPrompt,
            $contextPrompt,
            $rulesPrompt,
            $outputPrompt,
        ]);
    }

    /**
     * Prompt de sistema (quem é a IA)
     */
    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
# 🎯 VOCÊ É UM ESPECIALISTA EM MERCHANDISING E PLANOGRAMAS

Você é um merchandiser experiente responsável por criar planogramas otimizados para varejo.
Seu objetivo é distribuir produtos nas prateleiras de uma gôndola considerando:

1. **Regras de Merchandising Visual:**
    - Produtos ABC nas posições corretas (A=altura dos olhos 60-90%, B=meio 30-60%, C=baixo 5-30%)
    - Agrupamento por subcategoria (produtos similares juntos)
    - Marcas organizadas logicamente (marca -> linha -> tamanho)

2. **Ergonomia e Vendas:**
    - Produtos mais vendidos em posições privilegiadas
    - Evitar prateleiras muito altas ou baixas para produtos pesados
    - Balancear peso entre prateleiras

3. **Utilização de Espaço (CRÍTICO):**
    - **TODAS as prateleiras devem ser utilizadas** - nenhuma prateleira pode ficar vazia
    - **Ocupação mínima de 70% por prateleira** - deixe apenas 10-15% livre para reposição
    - **Ocupação média ideal: 75-85%** do espaço total disponível
    - Distribuir produtos uniformemente (evitar prateleiras 100% cheias enquanto outras têm <50%)
    - Respeitar dimensões físicas (largura, altura, profundidade)
    - Se sobrar muito espaço, aumentar facings dos produtos existentes

4. **Repetição de SKU (CRÍTICO):**
     - Quando repetir o mesmo produto, priorize **bloco vertical**: mesma section_id e prateleira imediatamente acima/abaixo
     - Evite espalhar o mesmo produto em módulos diferentes
     - Evite repetir o mesmo produto em mais de 2 prateleiras
     - Se precisar aumentar exposição, prefira aumentar facings na mesma prateleira antes de abrir nova prateleira

5. **Facings (OBRIGATÓRIO por classificação ABC):**
    - **Produtos A:** 3-5 facings (prioridade máxima de exposição)
    - **Produtos B:** 2-3 facings (exposição média)
    - **Produtos C:** 1-2 facings (exposição mínima)
    - Ajustar facings ANTES de deixar espaços vazios

6. **Densificação do Planograma:**
    - Ao calcular o espaço disponível, considere que vai usar TODOS os produtos fornecidos
    - Se detectar que produtos não vão caber, aumente facings ao invés de deixar vazios
    - Priorize ocupação completa do espaço sobre ter menos produtos expostos
PROMPT;
    }

    /**
     * Prompt de contexto (dados da gôndola e produtos)
     */
    protected function buildContextPrompt(PlanogramContextDTO $context): string
    {
        $stats = $context->getStatsSummary();

        // Extrair valores ABC para interpolação segura
        $abcA = $stats['abc_distribution']['A'] ?? 0;
        $abcB = $stats['abc_distribution']['B'] ?? 0;
        $abcC = $stats['abc_distribution']['C'] ?? 0;

        return <<<PROMPT
# 📊 CONTEXTO DO PLANOGRAMA

## Gôndola:
- **ID:** {$context->gondolaData['id']}
- **Nome:** {$context->gondolaData['name']}
- **Dimensões:** {$context->gondolaData['width']}cm (L) x {$context->gondolaData['height']}cm (A) x {$context->gondolaData['depth']}cm (P)

## Prateleiras Disponíveis:
- **Total:** {$stats['total_shelves']} prateleiras
- **Área Total:** {$stats['total_shelf_area_cm2']} cm²

## Produtos para Alocar:
- **Total:** {$stats['total_products']} produtos
- **Área Total Estimada:** {$stats['total_product_area_cm2']} cm²
- **Utilização Estimada:** {$stats['space_utilization_estimate']}
- **Distribuição ABC:** A={$abcA}, B={$abcB}, C={$abcC}
- **Score Médio:** {$stats['avg_product_score']}

## Dados Detalhados:

### Prateleiras:
```json
{$this->formatJson($context->shelves)}
```

### Produtos:
```json
{$this->formatJson($context->products)}
```

### Hierarquia de Categorias:
```json
{$this->formatJson($context->categoryHierarchy)}
```
PROMPT;
    }

    /**
     * Prompt de regras específicas da configuração
     */
    protected function buildRulesPrompt(IAGenerateConfigDTO $config): string
    {
        $rules = [];

        $rules[] = '## ⚙️ REGRAS DE GERAÇÃO';
        $rules[] = '';
        $rules[] = '### 🎯 Metas de Ocupação (PRIORIDADE MÁXIMA):';
        $rules[] = '- ✅ **Todas as prateleiras DEVEM ser utilizadas** (0 prateleiras vazias)';
        $rules[] = '- ✅ **Ocupação mínima:** 70% por prateleira';
        $rules[] = '- ✅ **Ocupação média ideal:** 75-85% do espaço total';
        $rules[] = '- ✅ **Espaço livre máximo:** 10-15% por prateleira para reposição';
        $rules[] = '- ⚠️ **Se sobrar espaço:** Aumente facings dos produtos existentes';
        $rules[] = '- ⚠️ **Nunca:** Deixe prateleiras vazias ou com <50% de ocupação';
        $rules[] = '';
        $rules[] = '### 📊 Estratégia de Distribuição:';
        $rules[] = "- **Estratégia:** {$config->strategy}";

        if ($config->respectSeasonality) {
            $rules[] = '- **Sazonalidade:** Respeitar produtos sazonais (priorizar se em temporada)';
        }

        if ($config->applyVisualGrouping) {
            $rules[] = '- **Agrupamento Visual:** Produtos da mesma subcategoria devem ficar juntos (mesma prateleira ou adjacentes)';
        }

        if ($config->intelligentOrdering) {
            $rules[] = '- **Ordenação Inteligente:** Dentro de cada grupo, ordenar por: Marca → Linha → Tamanho';
        }

        if ($config->loadBalancing) {
            $rules[] = '- **Balanceamento:** Distribuir produtos uniformemente entre prateleiras (evitar 100% em umas e 0% em outras)';
        }

        $rules[] = '';
        $rules[] = '### 📏 Facings por Classe ABC (OBRIGATÓRIO):';
        $rules[] = '- **Produtos A:** 3-5 facings (não menos que 3)';
        $rules[] = '- **Produtos B:** 2-3 facings';
        $rules[] = '- **Produtos C:** 1-2 facings';
        $rules[] = '- Se houver espaço sobrando após alocar todos os produtos, aumente facings começando pelos produtos A';
        $rules[] = '';
        $rules[] = '### 🧩 Regra de Bloco Vertical (anti-dispersão):';
        $rules[] = '- Se um produto for repetido, manter na mesma section_id em prateleiras adjacentes (logo acima/abaixo)';
        $rules[] = '- Não espalhar o mesmo SKU em seções diferentes, exceto quando faltar espaço real';
        $rules[] = '- Máximo recomendado: 2 prateleiras por SKU repetido';
        $rules[] = '- Antes de repetir em outra prateleira, tente aumentar facings na prateleira atual';

        if ($config->additionalInstructions) {
            $rules[] = '';
            $rules[] = '### 📝 INSTRUÇÕES ADICIONAIS DO USUÁRIO';
            $rules[] = $config->additionalInstructions;
        }

        return implode("\n", $rules);
    }

    /**
     * Prompt de formato de saída esperado
     */
    protected function buildOutputFormatPrompt(): string
    {
        return <<<'PROMPT'
# 📤 FORMATO DE SAÍDA ESPERADO

Você DEVE retornar um JSON válido com a seguinte estrutura:

```json
{
    "reasoning": "Explicação detalhada da sua estratégia de distribuição e decisões tomadas",
    "confidence": 0.85,
    "allocation": [
        {
            "shelf_id": "01abc...",
            "products": [
                {
                    "product_id": "01xyz...",
                    "facings": 3,
                    "position_x": 0,
                    "justification": "Produto A de alta rotação, posicionado à esquerda"
                }
            ]
        }
    ],
    "summary": {
        "total_allocated": 45,
        "total_unallocated": 12,
        "shelves_used": 8,
        "avg_occupancy": 78.5,
        "warnings": ["Produtos X, Y, Z não couberam por falta de espaço"],
        "recommendations": ["Considere remover produtos C de baixa rotação", "Adicione prateleira adicional"]
    }
}
```

## Regras de Alocação:
1. **shelf_id:** ID da prateleira onde o produto será alocado
2. **product_id:** ID único do produto
3. **facings:** Quantidade de faces do produto (1-5)
4. **position_x:** Posição horizontal na prateleira (em cm, começando do 0)
5. **justification:** Breve explicação da decisão

## IMPORTANTE:
- Retorne APENAS o JSON, sem texto adicional antes ou depois
- Não use markdown no retorno final
- Valide que todos os produtos cabem fisicamente nas prateleiras
- Não ultrapasse a largura disponível de cada prateleira
- Considere o peso máximo suportado
PROMPT;
    }

    /**
     * Formatar JSON para o prompt
     */
    protected function formatJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
