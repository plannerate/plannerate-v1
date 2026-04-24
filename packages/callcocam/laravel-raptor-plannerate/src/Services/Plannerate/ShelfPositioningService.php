<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

class ShelfPositioningService
{
    /**
     * Calcula os furos em uma seção com base nas dimensões fornecidas
     *
     * @param  array  $section  Dados da seção contendo altura, dimensões dos furos, etc
     * @return array Array de furos calculados com suas dimensões e posições
     */
    public function calculateHoles(array $section): array
    {

        $sectionHeight = $section['height'];
        $holeHeight = $section['hole_height'];
        $holeWidth = $section['hole_width'];
        $holeSpacing = $section['hole_spacing'];
        $baseHeight = $section['base_height'] ?? 0;

        // Calculate available height for holes (excluding the base at the bottom)
        $availableHeight = $sectionHeight - $baseHeight;

        // Calculate how many holes we can fit
        $totalSpaceNeeded = $holeHeight + $holeSpacing;
        $holeCount = floor($availableHeight / $totalSpaceNeeded);

        // Calculate the remaining space to distribute evenly
        $remainingSpace = $availableHeight - $holeCount * $holeHeight - ($holeCount - 1) * $holeSpacing;
        $marginTop = $remainingSpace / 2; // Start from the top with margin

        $holes = [];
        for ($i = 0; $i < $holeCount; $i++) {
            $holePosition = $marginTop + $i * ($holeHeight + $holeSpacing);
            $holes[] = [
                'width' => $holeWidth,
                'height' => $holeHeight,
                'spacing' => $holeSpacing,
                'position' => $holePosition,
            ];
        }

        return $holes;
    }

    /**
     * Encontra o furo mais próximo de uma posição alvo
     *
     * @param  float  $targetPosition  Posição ideal onde queremos colocar a prateleira
     * @param  array  $holes  Array de furos disponíveis
     * @return array|null O furo mais próximo da posição alvo
     */
    public function findClosestHole(float $targetPosition, array $holes): ?array
    {
        if (empty($holes)) {
            return null;
        }

        // Começamos assumindo que o primeiro furo é o mais próximo
        $closestHole = $holes[0];
        $minDistance = abs($targetPosition - $closestHole['position']);

        // Verificamos todos os outros furos
        for ($i = 1; $i < count($holes); $i++) {
            $distance = abs($targetPosition - $holes[$i]['position']);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestHole = $holes[$i];
            }
        }

        return $closestHole;
    }

    /**
     * Calcula a posição vertical (top) da prateleira com base nos furos disponíveis
     *
     * @param  int  $totalShelves  Total de prateleiras
     * @param  float  $shelfHeight  Altura da prateleira
     * @param  array  $holes  Array de furos disponíveis
     * @param  int  $currentIndex  Índice da prateleira atual
     * @param  float  $scaleFactor  Fator de escala para ajuste de tamanho
     * @return float Posição superior da prateleira
     */
    public function calculateShelfPosition(
        int $totalShelves,
        float $shelfHeight,
        array $holes,
        int $currentIndex,
        float $scaleFactor
    ): float {
        // Dimensões e índices
        $scaledShelfHeight = $shelfHeight;

        // Verificar se existem furos disponíveis
        if (empty($holes)) {
            return 0;
        }

        // Ordenamos os furos por posição para garantir distribuição correta
        $sortedHoles = $holes;
        usort($sortedHoles, function ($a, $b) {
            return $a['position'] - $b['position'];
        });

        // Variável para armazenar o furo selecionado
        $selectedHole = null;

        // Lógica de seleção do furo adequado
        if ($totalShelves <= 1) {
            // Caso especial: apenas uma prateleira, usar furo do meio
            $middleHoleIndex = floor(count($sortedHoles) / 2);
            $selectedHole = $sortedHoles[$middleHoleIndex];
        } elseif ($currentIndex === 0) {
            // A primeira prateleira vai no primeiro furo
            $selectedHole = $sortedHoles[0];
        } elseif ($currentIndex === $totalShelves - 1) {
            // A última prateleira vai no último furo
            $selectedHole = $sortedHoles[count($sortedHoles) - 1];
        } else {
            // Prateleiras intermediárias são distribuídas uniformemente
            // Calculamos a posição ideal com base na distribuição uniforme
            $availableSpace = $sortedHoles[count($sortedHoles) - 1]['position'] - $sortedHoles[0]['position'];
            $step = $availableSpace / ($totalShelves - 1);
            $idealPosition = $sortedHoles[0]['position'] + $step * $currentIndex;

            // Encontramos o furo real mais próximo dessa posição ideal
            $selectedHole = $this->findClosestHole($idealPosition, $sortedHoles);
        }

        // Obtemos as propriedades do furo selecionado
        $holeHeight = $selectedHole['height'] ?? 15;
        $holePosition = $selectedHole['position'] ?? 0;

        // Calculamos a diferença entre a altura da prateleira e a altura do furo
        // e ajustamos a posição para centralizar a prateleira no furo
        $heightDifference = $scaledShelfHeight - $holeHeight;
        $topPosition = $holePosition - $heightDifference / 2;

        return $topPosition;
    }

    /**
     * Retorna um array com todos os estilos CSS necessários para posicionar a prateleira
     *
     * @param  int  $totalShelves  Total de prateleiras
     * @param  float  $shelfHeight  Altura da prateleira
     * @param  float  $sectionWidth  Largura da seção
     * @param  array  $holes  Array de furos disponíveis
     * @param  int  $currentIndex  Índice da prateleira atual
     * @param  float  $scaleFactor  Fator de escala para ajuste de tamanho
     * @return array Array associativo com estilos CSS
     */
    public function getShelfStyleArray(
        int $totalShelves,
        float $shelfHeight,
        float $sectionWidth,
        array $holes,
        int $currentIndex,
        float $scaleFactor
    ): array {
        $topPosition = $this->calculateShelfPosition(
            $totalShelves,
            $shelfHeight,
            $holes,
            $currentIndex,
            $scaleFactor
        );

        $scaledShelfHeight = $shelfHeight * $scaleFactor;
        $scaledSectionWidth = $sectionWidth * $scaleFactor;

        return [
            'position' => 'absolute',
            'left' => '0',
            'width' => "{$scaledSectionWidth}px",
            'height' => "{$scaledShelfHeight}px",
            'top' => "{$topPosition}px",
        ];
    }

    /**
     * Converte um array de estilos em uma string CSS inline
     *
     * @param  array  $styleArray  Array associativo com estilos CSS
     * @return string String formatada para uso como estilo inline
     */
    public function convertStyleArrayToString(array $styleArray): string
    {
        $styleString = '';
        foreach ($styleArray as $property => $value) {
            $styleString .= "{$property}: {$value}; ";
        }

        return rtrim($styleString);
    }
}
