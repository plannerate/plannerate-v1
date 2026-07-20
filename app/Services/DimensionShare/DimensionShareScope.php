<?php

namespace App\Services\DimensionShare;

/**
 * Escopo de um link público de correção de dimensões: o que delimita quais produtos
 * o portador do link enxerga e pode editar.
 *
 * O escopo é declarativo — guarda o ID da categoria, não a lista de produtos. A lista
 * é derivada na leitura, então o link acompanha mudanças posteriores no cadastro.
 *
 * Sem categoria, o escopo é o tenant inteiro.
 *
 * Não existe escopo por gôndola de propósito: produto sem dimensão nunca chega a ser
 * posicionado numa gôndola, então esse recorte seria sempre vazio. O editor emite o
 * link com a categoria do planograma, que alcança os produtos ainda não posicionados.
 */
class DimensionShareScope
{
    public function __construct(
        public readonly ?string $categoryId = null,
        public readonly ?string $categoryName = null,
    ) {}

    /**
     * Normaliza strings vazias para null — o front manda '' quando não há filtro.
     */
    public static function make(
        ?string $categoryId = null,
        ?string $categoryName = null,
    ): self {
        return new self(
            self::normalize($categoryId),
            self::normalize($categoryName),
        );
    }

    /**
     * @return array{category_id: ?string, category_name: ?string}
     */
    public function toAttributes(): array
    {
        return [
            'category_id' => $this->categoryId,
            'category_name' => $this->categoryName,
        ];
    }

    private static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
