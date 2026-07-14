<?php

use Callcocam\LaravelRaptorPlannerate\Services\Analysis\AbcAnalysisService;

/*
 * Status e Justificativa do produto — colunas Q e S da planilha do cliente.
 *
 * Regra do VBA (docs/ABC.md, bloco ">>> Status do Produto"), janela de 120 dias:
 *
 *   venda recente + compra recente   → Ativo   / Venda e compra recentes
 *   venda recente + sem compra       → Ativo   / Venda recente, sem compra
 *   compra recente + sem venda       → Ativo   / Compra recente, sem venda
 *   nenhum dos dois                  → Sem venda e sem compra
 *                                      Inativo se estoque = 0, Ativo se estoque > 0
 *
 * A implementação anterior era uma "lógica simplificada": só olhava a última venda e
 * tratava o estoque como 0 fixo, então os dois casos de compra nunca disparavam.
 */

beforeEach(function (): void {
    $this->service = new AbcAnalysisService;
});

/** Data a N dias atrás — o corte do VBA é 120 dias. */
function haDias(int $dias): string
{
    return now()->subDays($dias)->toDateString();
}

it('venda e compra recentes → Ativo', function (): void {
    $r = $this->service->productStatus(haDias(10), haDias(30), 0.0);

    expect($r['status'])->toBe('Ativo')
        ->and($r['motivo'])->toBe('Venda e compra recentes');
});

it('venda recente sem compra → Ativo', function (): void {
    // É o caso da planilha do cliente na maioria das linhas.
    $r = $this->service->productStatus(haDias(10), null, 0.0);

    expect($r['status'])->toBe('Ativo')
        ->and($r['motivo'])->toBe('Venda recente, sem compra');
});

it('venda recente com compra ANTIGA também é "sem compra"', function (): void {
    // O VBA testa `ultimaCompra = 0 Or hoje - ultimaCompra > 120`: compra fora da janela
    // vale o mesmo que compra nenhuma.
    $r = $this->service->productStatus(haDias(10), haDias(200), 0.0);

    expect($r['motivo'])->toBe('Venda recente, sem compra');
});

it('compra recente sem venda → Ativo', function (): void {
    // Produto que entrou no estoque mas ainda não girou.
    $r = $this->service->productStatus(null, haDias(15), 0.0);

    expect($r['status'])->toBe('Ativo')
        ->and($r['motivo'])->toBe('Compra recente, sem venda');
});

it('sem venda e sem compra, COM estoque → Ativo', function (): void {
    // Parado, mas ainda ocupa espaço: continua ativo.
    $r = $this->service->productStatus(haDias(300), haDias(400), 12.0);

    expect($r['status'])->toBe('Ativo')
        ->and($r['motivo'])->toBe('Sem venda e sem compra');
});

it('sem venda e sem compra, SEM estoque → Inativo', function (): void {
    // Único caminho para Inativo em todo o VBA.
    $r = $this->service->productStatus(haDias(300), null, 0.0);

    expect($r['status'])->toBe('Inativo')
        ->and($r['motivo'])->toBe('Sem venda e sem compra');
});

it('produto que nunca vendeu nem comprou, sem estoque → Inativo', function (): void {
    $r = $this->service->productStatus(null, null, 0.0);

    expect($r['status'])->toBe('Inativo')
        ->and($r['motivo'])->toBe('Sem venda e sem compra');
});

it('exatamente 120 dias ainda é recente (o corte do VBA é <=)', function (): void {
    expect($this->service->productStatus(haDias(120), null, 0.0)['motivo'])
        ->toBe('Venda recente, sem compra');

    expect($this->service->productStatus(haDias(121), null, 0.0)['motivo'])
        ->toBe('Sem venda e sem compra');
});

it('data inválida é tratada como ausência de movimento, não como erro', function (): void {
    // Blindagem: `last_purchase_date` vem do ERP e pode chegar suja.
    $r = $this->service->productStatus('nao-e-data', '', 0.0);

    expect($r['status'])->toBe('Inativo')
        ->and($r['motivo'])->toBe('Sem venda e sem compra');
});

it('com last_purchase_date vazia — o estado de HOJE — cai sempre em "Venda recente, sem compra"', function (): void {
    // A coluna existe mas a importação não a popula (0 de 8.592 produtos no tenant de dev).
    // Enquanto isso, os dois casos de compra não disparam. A lógica está certa; falta o dado.
    // Este teste documenta o comportamento atual para que a diferença fique visível quando
    // a importação passar a preencher o campo.
    $r = $this->service->productStatus(haDias(5), null, 0.0);

    expect($r['motivo'])->toBe('Venda recente, sem compra');
});
