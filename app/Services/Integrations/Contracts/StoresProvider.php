<?php

namespace App\Services\Integrations\Contracts;

/**
 * Fonte das lojas que o motor deve importar.
 *
 * Este é o único acoplamento do motor que carrega regra de negócio: "quais lojas
 * importar" não é o nome de uma classe, é um critério da aplicação (hoje, loja
 * publicada com documento preenchido). Por isso vem de um binding rebindável, e não
 * de uma simples class-string em config.
 *
 * Implementações assumem que o contexto de tenant já está ativo — ambos os pontos de
 * chamada rodam dentro de `$tenant->execute(...)`.
 */
interface StoresProvider
{
    /**
     * Lojas elegíveis do tenant corrente, já normalizadas.
     *
     * @return array<int, array{id: string, document: string}>
     */
    public function stores(): array;

    /**
     * Documento (só dígitos) da primeira loja elegível — usado pelo painel de
     * diagnóstico para montar um request de teste com dados reais.
     */
    public function firstDocument(): ?string;
}
