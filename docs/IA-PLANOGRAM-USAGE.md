# Geração de Planogramas com IA (Prism PHP)

## 📋 Visão Geral

Sistema completo de geração de planogramas usando **IA** (Large Language Models) através do **Prism PHP**. A IA analisa o contexto completo da gôndola e produtos para criar uma distribuição inteligente e otimizada.

## 🏗️ Arquitetura

```
DTOs (app/DTOs/Plannerate/IAGenerate/)
├── IAGenerateConfigDTO.php      # Configuração da geração
├── IAGenerateResultDTO.php      # Resultado estruturado
└── PlanogramContextDTO.php      # Contexto enviado à IA

Services (app/Services/Plannerate/IAGenerate/)
├── IAPlanogramService.php           # Orquestrador principal
├── IAPromptBuilderService.php       # Construção de prompts
└── IAResponseParserService.php      # Parse de respostas JSON

Controller
└── AutoPlanogramController.php      # Endpoint iaGenerate()

Request
└── IAGeneratePlanogramRequest.php   # Validação
```

## 🚀 Como Usar

### 1. **Endpoint API**

```http
POST /api/tenant/plannerate/gondolas/{gondola_id}/ia-generate
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "category_id": "01kfc8bd...",
  "strategy": "mix",
  "respect_seasonality": true,
  "apply_visual_grouping": true,
  "intelligent_ordering": true,
  "load_balancing": true,
  "additional_instructions": "Priorizar produtos orgânicos e zero açúcar",
  "model": "gpt-4o",
  "max_tokens": 4000,
  "temperature": 0.7
}
```

### 2. **Parâmetros**

#### Obrigatórios:
- `category_id` (string, ULID): Categoria raiz para buscar produtos
- `strategy` (enum): Estratégia de seleção - `sales`, `margin`, `mix`, `abc`

#### Opcionais - Filtros:
- `subcategory_id` (string, ULID): Filtrar por subcategoria específica
- `brand_id` (string, ULID): Filtrar por marca específica

#### Opcionais - Features:
- `respect_seasonality` (bool, default: true): Priorizar produtos sazonais
- `apply_visual_grouping` (bool, default: true): Agrupar produtos por subcategoria
- `intelligent_ordering` (bool, default: true): Ordenar por marca → linha → tamanho
- `load_balancing` (bool, default: true): Distribuir uniformemente entre prateleiras
- `additional_instructions` (string, max 1000 chars): Instruções específicas para IA

#### Opcionais - Configuração IA:
- `model` (string, default: `gpt-4o`): Modelo LLM a usar
  - `gpt-4o` (recomendado)
  - `gpt-4o-mini` (mais rápido/barato)
  - `claude-3-5-sonnet-20241022` (Anthropic)
  - `claude-3-5-haiku-20241022` (Anthropic, rápido)
- `max_tokens` (int, default: 4000): Máximo de tokens na resposta
- `temperature` (float, default: 0.7): Criatividade da IA (0-2)

### 3. **Resposta**

```json
{
  "success": "🤖 Planograma gerado com IA!

✅ Produtos alocados: 45
❌ Produtos não alocados: 12
📊 Prateleiras usadas: 8
🎯 Confiança: 85.5%
💬 Tokens usados: 3241
⏱️ Tempo: 8.42s

💡 Raciocínio da IA:
Distribuí os produtos priorizando a curva ABC nas alturas corretas (A=60-90%, B=30-60%, C=5-30%). Agrupei produtos da mesma subcategoria para facilitar localização..."
}
```

## 🧠 Funcionamento Interno

### Fluxo de Execução:

1. **Setup Multi-Tenancy** - Configura conexão ao banco do cliente
2. **Carrega Gôndola** - Busca estrutura completa (seções → prateleiras → camadas)
3. **Seleciona Produtos** - Usa `ProductSelectionService` com scoring ABC/vendas
4. **Constrói Contexto** - Cria `PlanogramContextDTO` com:
   - Dados da gôndola (dimensões, capacidade)
   - Lista de prateleiras (posição, dimensões, carga máxima)
   - Produtos candidatos (dimensões, ABC, scores, facings sugeridos)
   - Hierarquia de categorias
   - Regras de merchandising
5. **Gera Prompt** - `IAPromptBuilderService` cria prompt estruturado:
   - System prompt (definindo papel da IA como merchandiser)
   - Context prompt (dados estruturados em JSON)
   - Rules prompt (regras específicas da configuração)
   - Output format prompt (formato JSON esperado)
6. **Chama IA via Prism** - Envia prompt para LLM configurado
7. **Parseia Resposta** - `IAResponseParserService` valida e estrutura JSON
8. **Salva no Banco** - Cria `Segment` records com alocações
9. **Retorna Resultado** - `IAGenerateResultDTO` com métricas e raciocínio

### Prompt Enviado à IA:

```markdown
# 🎯 VOCÊ É UM ESPECIALISTA EM MERCHANDISING E PLANOGRAMAS

[Definição do papel e objetivos...]

# 📊 CONTEXTO DO PLANOGRAMA

## Gôndola:
- ID: 01kfc8bw...
- Dimensões: 120cm (L) x 200cm (A) x 40cm (P)

## Prateleiras Disponíveis:
- Total: 16 prateleiras
- Área Total: 76800 cm²

## Produtos para Alocar:
- Total: 119 produtos
- Distribuição ABC: A=25, B=45, C=49

## Dados Detalhados:
[JSON com prateleiras e produtos...]

# ⚙️ REGRAS DE GERAÇÃO
- Estratégia: mix
- Sazonalidade: Sim
- Agrupamento Visual: Sim
[...]

# 📤 FORMATO DE SAÍDA ESPERADO
[JSON schema...]
```

### Resposta da IA:

```json
{
  "reasoning": "Distribuí 45 produtos priorizando...",
  "confidence": 0.85,
  "allocation": [
    {
      "shelf_id": "01abc...",
      "products": [
        {
          "product_id": "01xyz...",
          "facings": 3,
          "position_x": 0,
          "justification": "Produto A, alta rotação"
        }
      ]
    }
  ],
  "summary": {
    "total_allocated": 45,
    "total_unallocated": 12,
    "shelves_used": 8,
    "avg_occupancy": 78.5,
    "warnings": ["Produtos X, Y não couberam"],
    "recommendations": ["Considere adicionar prateleira"]
  }
}
```

## 🎯 Vantagens vs Algoritmo Tradicional

| Feature | Tradicional (`generate`) | IA (`iaGenerate`) |
|---------|--------------------------|-------------------|
| **Velocidade** | ~2s | ~8-15s |
| **Custo** | Grátis | ~$0.10-0.50 por geração |
| **Inteligência** | Regras fixas | Raciocínio contextual |
| **Agrupamento Visual** | ❌ Não implementado | ✅ Sim |
| **Ordenação Inteligente** | ❌ Não implementado | ✅ Sim |
| **Balanceamento** | ❌ Não implementado | ✅ Sim |
| **Instruções Customizadas** | ❌ Não | ✅ Sim |
| **Explicabilidade** | ⚠️ Limitada | ✅ Raciocínio detalhado |
| **Previsibilidade** | ✅ Alta | ⚠️ Varia |

## 🔧 Configuração

### 1. Instalar Prism PHP

```bash
composer require echolabs/prism
```

### 2. Configurar API Keys

Adicione ao `.env`:

```env
# OpenAI
OPENAI_API_KEY=sk-proj-...

# Anthropic (opcional)
ANTHROPIC_API_KEY=sk-ant-...
```

### 3. Publicar Config (opcional)

```bash
php artisan vendor:publish --tag=prism-config
```

## 📊 Monitoramento

### Logs Estruturados:

```php
Log::info('🤖 Iniciando geração com IA', [
    'gondola_id' => $gondolaId,
    'config' => $config->toArray(),
]);

Log::info('📦 Produtos selecionados', [
    'total' => 119,
    'abc_distribution' => ['A' => 25, 'B' => 45, 'C' => 49],
]);

Log::info('🔮 Chamando IA via Prism', [
    'model' => 'gpt-4o',
    'max_tokens' => 4000,
    'temperature' => 0.7,
]);

Log::info('✅ Planograma gerado com sucesso', [
    'total_allocated' => 45,
    'confidence' => 0.85,
    'tokens_used' => 3241,
    'execution_time' => 8.42,
]);
```

### Métricas Importantes:

- **Tokens Used**: Custo da operação
- **Execution Time**: Performance
- **Confidence**: Confiança da IA no resultado (0-1)
- **Total Allocated**: Taxa de sucesso
- **Avg Occupancy**: Utilização de espaço

## 🧪 Testes

### Teste Manual:

```bash
# Via frontend (mesmo usado para generate)
# Apenas mude a rota de api.tenant.plannerate.gondolas.auto-generate
# para api.tenant.plannerate.gondolas.ia-generate
```

### Teste via Postman/curl:

```bash
curl -X POST "http://localhost/api/tenant/plannerate/gondolas/01kfc8bwf371cbygwe0nma6mt7/ia-generate" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "category_id": "01abc...",
    "strategy": "mix",
    "apply_visual_grouping": true,
    "load_balancing": true,
    "model": "gpt-4o"
  }'
```

## 💡 Dicas de Uso

### 1. **Escolha do Modelo**

- **gpt-4o**: Melhor qualidade, raciocínio superior, mais caro (~$0.50)
- **gpt-4o-mini**: Bom custo-benefício, rápido (~$0.10)
- **claude-3-5-sonnet**: Alternativa OpenAI, excelente qualidade
- **claude-3-5-haiku**: Mais barato da Anthropic, muito rápido

### 2. **Temperature**

- **0.0-0.3**: Mais determinístico, menos criativo
- **0.5-0.7**: Balanceado (recomendado)
- **0.8-1.2**: Mais criativo, menos previsível
- **1.3-2.0**: Muito experimental

### 3. **Additional Instructions**

Exemplos eficazes:
- "Priorizar produtos com embalagens verdes na altura dos olhos"
- "Separar produtos diet dos regulares"
- "Agrupar marcas próprias em blocos visuais"
- "Evitar colocar produtos pesados acima de 150cm"

### 4. **Otimização de Custos**

- Use `gpt-4o-mini` para testes
- Limite `max_tokens` (2000-3000 geralmente suficiente)
- Filtre produtos antes (use `subcategory_id`)
- Cache resultados quando possível

## 🐛 Troubleshooting

### Erro: "Nenhum produto encontrado"

- ✅ Verifique `category_id` correto
- ✅ Confirme produtos existem nessa categoria
- ✅ Check filtros `subcategory_id`/`brand_id`

### Erro: "Erro ao decodificar JSON"

- ✅ IA retornou texto ao invés de JSON
- ✅ Ajuste prompt ou temperatura
- ✅ Tente modelo diferente (Claude é melhor com JSON)

### Erro: "Erro ao comunicar com IA"

- ✅ Verifique API key no `.env`
- ✅ Confirme saldo/créditos da API
- ✅ Check limites de rate limiting

### Performance Lenta (>20s)

- ✅ Reduza `max_tokens`
- ✅ Limite quantidade de produtos (filtros)
- ✅ Use modelo mais rápido (`gpt-4o-mini`)

## 📚 Referências

- [Prism PHP Docs](https://prismphp.com/)
- [OpenAI API Docs](https://platform.openai.com/docs/)
- [Anthropic API Docs](https://docs.anthropic.com/)
