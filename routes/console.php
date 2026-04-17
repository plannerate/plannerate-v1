<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ===== SINCRONIZAÇÕES PRINCIPAIS =====
// IMPORTANTE: APIs externas não funcionam entre 00:00 - 08:00
// Todos os syncs devem rodar APÓS as 08:00

// Produtos: Roda todos os dias às 08:30 (logo após APIs voltarem)
// ⚠️ TEMPORARIAMENTE DESABILITADO PARA TESTES
// Schedule::command('sync:sales')
//     ->dailyAt('08:30')
//     ->withoutOverlapping()
//     ->onFailure(fn () => Log::error('Falha na sincronização de produtos agendada'))
//     ->onSuccess(fn () => Log::info('Sincronização de produtos agendada concluída'));

// Vendas: Roda todos os dias às 07:00
// ✅ ATIVADO - Conexão corrigida para usar banco correto do cliente
Schedule::command('sync:sales')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('Falha na sincronização de vendas agendada'))
    ->onSuccess(fn () => Log::info('Sincronização de vendas agendada concluída'));

// Vinculação de vendas aos produtos: Roda todos os dias às 07:30
// Preenche product_id e ean nas vendas usando codigo_erp
Schedule::command('sync:link-sales')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->name('link-sales-products')
    ->onFailure(fn () => Log::error('Falha na vinculação de vendas aos produtos'))
    ->onSuccess(fn () => Log::info('Vinculação de vendas aos produtos concluída'));
 

Schedule::command('sync:stock')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('Falha na sincronização de estoque agendada'))
    ->onSuccess(fn () => Log::info('Sincronização de vendas agendada concluída'));
// ===== PROCESSAMENTO DE IMAGENS DE PRODUTOS =====
// Processa imagens de produtos para todos os clients com status 'published'
// Roda segundas, quartas e sextas às 02:00 (aproximadamente a cada 2 dias)
Schedule::command('process-product-images')
    ->days([1, 3, 5]) // Segunda, Quarta, Sexta
    ->at('02:00')
    ->withoutOverlapping()
    ->name('process-product-images')
    ->onFailure(fn () => Log::error('Falha no processamento de imagens de produtos'))
    ->onSuccess(fn () => Log::info('Processamento de imagens de produtos concluído'));

// ===== LIMPEZA E MANUTENÇÃO DE DADOS =====

// Restaurar produtos deletados que tiveram vendas recentes
// Roda toda segunda-feira às 06:00 (antes das sincronizações)
Schedule::command('sync:cleanup --restore-sold --days=30')
    ->weeklyOn(1, '06:00') // Segunda às 06:00
    ->withoutOverlapping()
    ->name('cleanup-restore-sold-products')
    ->onFailure(fn () => Log::error('Falha ao restaurar produtos com vendas'))
    ->onSuccess(fn () => Log::info('Restauração de produtos com vendas concluída'));

// Limpar vendas órfãs (sem produto correspondente)
// Roda todo domingo às 03:00 (menor tráfego)
Schedule::command('sync:cleanup --orphan-sales')
    ->weeklyOn(0, '03:00') // Domingo às 03:00
    ->withoutOverlapping()
    ->name('cleanup-orphan-sales')
    ->onFailure(fn () => Log::error('Falha ao limpar vendas órfãs'))
    ->onSuccess(fn () => Log::info('Limpeza de vendas órfãs concluída'));

// Desativar produtos sem vendas (soft delete)
// Roda no primeiro dia de cada mês às 04:00
// Considera produtos sem vendas nos últimos 180 dias
Schedule::command('sync:cleanup --inactive-products --days=180')
    ->monthlyOn(1, '04:00') // Dia 1 às 04:00
    ->withoutOverlapping()
    ->name('cleanup-inactive-products')
    ->onFailure(fn () => Log::error('Falha ao desativar produtos inativos'))
    ->onSuccess(fn () => Log::info('Desativação de produtos inativos concluída'));

// Limpar vendas antigas (anteriores ao período configurado na integração)
// Roda todo domingo às 02:00 (antes da limpeza de órfãs)
// Usa o período configurado na integração de cada cliente (ex: 365, 120 dias)
Schedule::command('sync:cleanup --old-sales')
    ->weeklyOn(0, '02:00') // Domingo às 02:00
    ->withoutOverlapping()
    ->name('cleanup-old-sales')
    ->onFailure(fn () => Log::error('Falha ao limpar vendas antigas'))
    ->onSuccess(fn () => Log::info('Limpeza de vendas antigas concluída'));
