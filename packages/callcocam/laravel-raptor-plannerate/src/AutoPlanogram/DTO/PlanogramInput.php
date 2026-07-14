<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Illuminate\Support\Collection;

/**
 * Entrada para o processo de geração do planograma.
 *
 * Carrega todos os dados necessários para o pipeline:
 * planograma, gôndola, produtos do tenant e configurações.
 */
final readonly class PlanogramInput
{
    public function __construct(
        /** ULID do planograma */
        public string $planogramId,
        /** ULID da gôndola */
        public string $gondolaId,
        /** ULID do tenant */
        public string $tenantId,
        /**
         * Produtos já filtrados por categoria para esta gôndola.
         *
         * @var Collection<int, Product>
         */
        public Collection $products,
        /**
         * Sections (módulos) da gôndola com shelves carregadas.
         *
         * @var Collection<int, Section>
         */
        public Collection $sections,
        public PlacementSettings $settings,
        /**
         * Categoria-base do planograma (âncora do escopo para validação no modo automático).
         * Null em contextos legados ou de teste onde o planograma não está disponível.
         */
        public ?string $planogramCategoryId = null,
        /**
         * Produtos rejeitados ANTES do placement (ex.: retirados do mix pela recomendação ABC).
         * Mesclados aos rejeitados do engine na saída final, mas fora da contagem de capacidade.
         *
         * @var Collection<int, array{product: Product, reason: PlacementFailureReason}>
         */
        public Collection $preRejectedProducts = new Collection,
        /**
         * Modo simulação: calcula o layout completo mas NÃO persiste nada.
         *
         * Suportado apenas em modo template — o modo automático sintetiza o template no banco
         * (cria subtemplate/slots, remove slots vazios, cria prateleiras), efeitos que não podem
         * ser desfeitos pulando a transação de escrita. AutoPlanogramService::generate() rejeita
         * a combinação dryRun + modo automático.
         *
         * Usado pela reotimização contínua para montar a proposta de layout que o usuário revisa
         * antes de aplicar.
         */
        public bool $dryRun = false,
    ) {}
}
