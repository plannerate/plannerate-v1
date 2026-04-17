<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inspiration>
 */
class InspirationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inspirations = [
            // Planogramas e Organização
            ['message' => 'Um planograma bem executado transforma prateleiras em pontos de venda estratégicos.', 'author' => 'Plannerate'],
            ['message' => 'Cada centímetro na gôndola conta. Organize com inteligência, venda com eficiência.', 'author' => 'Plannerate'],
            ['message' => 'O sucesso do varejo começa na altura dos olhos do cliente.', 'author' => 'Plannerate'],
            ['message' => 'Padronização não limita criatividade, ela multiplica resultados.', 'author' => 'Plannerate'],
            ['message' => 'Produtos certos, no lugar certo, na hora certa. Esta é a fórmula do varejo.', 'author' => 'Plannerate'],
            
            // Dados e Análise
            ['message' => 'Dados não mentem. Um facing a mais ou a menos pode fazer toda diferença nas vendas.', 'author' => 'Plannerate'],
            ['message' => 'O giro de produtos revela histórias. Aprenda a ler e agir sobre elas.', 'author' => 'Plannerate'],
            ['message' => 'Métricas transformam intuição em estratégia.', 'author' => 'Plannerate'],
            ['message' => 'Analise, ajuste, otimize. O ciclo do planograma perfeito.', 'author' => 'Plannerate'],
            ['message' => 'Cada número no seu relatório representa uma oportunidade de melhoria.', 'author' => 'Plannerate'],
            
            // Estratégia e Vendas
            ['message' => 'A ponta de gôndola não é apenas um espaço, é uma declaração de prioridade.', 'author' => 'Plannerate'],
            ['message' => 'Vender mais não é sorte, é ciência aplicada ao layout.', 'author' => 'Plannerate'],
            ['message' => 'Complementaridade aumenta ticket médio. Vizinhos de prateleira importam.', 'author' => 'Plannerate'],
            ['message' => 'O espaço é limitado, mas o potencial de vendas é infinito quando bem planejado.', 'author' => 'Plannerate'],
            ['message' => 'Cross-merchandising inteligente transforma compras em experiências.', 'author' => 'Plannerate'],
            
            // Eficiência e Processos
            ['message' => 'Planogramas eficientes economizam tempo, aumentam vendas e reduzem ruptura.', 'author' => 'Plannerate'],
            ['message' => 'Reposição ágil começa com organização lógica.', 'author' => 'Plannerate'],
            ['message' => 'Um bom planograma simplifica operações e melhora a experiência de compra.', 'author' => 'Plannerate'],
            ['message' => 'Padronização entre lojas cria identidade e facilita gestão.', 'author' => 'Plannerate'],
            ['message' => 'Otimize hoje para colher resultados amanhã.', 'author' => 'Plannerate'],
            
            // Experiência do Cliente
            ['message' => 'Clientes não buscam produtos, eles encontram soluções bem organizadas.', 'author' => 'Plannerate'],
            ['message' => 'Uma gôndola bem planejada guia o cliente naturalmente até a compra.', 'author' => 'Plannerate'],
            ['message' => 'Visual merchandising começa no planograma.', 'author' => 'Plannerate'],
            ['message' => 'Facilite a jornada de compra e o carrinho vai agradecer.', 'author' => 'Plannerate'],
            ['message' => 'Organização gera confiança. Confiança gera vendas.', 'author' => 'Plannerate'],
            
            // Inovação e Melhoria
            ['message' => 'Teste, aprenda, ajuste. O planograma perfeito está sempre evoluindo.', 'author' => 'Plannerate'],
            ['message' => 'Tecnologia e dados elevam o planejamento de prateleiras a outro nível.', 'author' => 'Plannerate'],
            ['message' => 'Inovação no varejo começa com um layout inteligente.', 'author' => 'Plannerate'],
            ['message' => 'Cada revisão de planograma é uma chance de superar resultados anteriores.', 'author' => 'Plannerate'],
            ['message' => 'O futuro do varejo é data-driven. Comece pelo seu planograma.', 'author' => 'Plannerate'],
        ];

        $inspiration = fake()->randomElement($inspirations);

        return [
            'message' => $inspiration['message'],
            'author' => $inspiration['author'],
        ];
    }
}
