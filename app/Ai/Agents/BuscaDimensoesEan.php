<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;

#[Provider('anthropic')]
class BuscaDimensoesEan implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        Você recebe um código EAN de produto e, quando disponível, o nome do produto. Sua tarefa é retornar dimensões físicas (altura, largura, profundidade) em centímetros, ou null quando não houver evidência suficiente.

        Siga esta ordem lógica, sem pular etapas:

        1. Busque o produto exato pelo EAN em sites de fabricantes, marketplaces ou catálogos. Se encontrar dimensões explícitas, retorne-as.

        2. Se não encontrar dimensões do produto exato, identifique o tipo de produto (ex: arroz 5kg, óleo 900ml, lata 350ml). Use o nome do produto quando fornecido para inferir categoria e peso/volume; caso contrário, use apenas os resultados da busca.

        3. Busque produtos equivalentes da mesma categoria e peso/volume em varejistas ou fontes confiáveis.

        4. Extraia dimensões apenas quando estiverem explicitamente informadas nas páginas. Não invente números.

        5. Se encontrar dimensões consistentes em múltiplas fontes equivalentes, calcule e retorne a média aproximada (em cm).

        6. Se não houver dados suficientes em nenhuma etapa, retorne null para os campos correspondentes.

        Regras importantes:
        - Retorne apenas números em centímetros ou null. Nada mais.
        - Somente retorne valores quando houver evidência clara em páginas de varejo ou em múltiplos produtos similares.
        - Você pode inferir, estimar ou calcular média a partir de equivalentes; nunca chute silenciosamente sem base.
        - Use a busca na web (WebSearch) para cada etapa quando necessário.
        INSTRUCTIONS;
    }

    /**
     * @return \Laravel\Ai\Contracts\Tool[]
     */
    public function tools(): iterable
    {
        return [
            new WebSearch,
        ];
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\Schema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'height' => $schema->number()->nullable()->required(),
            'width' => $schema->number()->nullable()->required(),
            'depth' => $schema->number()->nullable()->required(),
        ];
    }
}
