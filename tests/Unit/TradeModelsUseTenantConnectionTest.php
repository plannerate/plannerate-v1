<?php

use Callcocam\LaravelRaptorTrade\Models\Activity;
use Callcocam\LaravelRaptorTrade\Models\ActivityAudit;
use Callcocam\LaravelRaptorTrade\Models\ActivityType;
use Callcocam\LaravelRaptorTrade\Models\Contract;
use Callcocam\LaravelRaptorTrade\Models\ContractAttachment;
use Callcocam\LaravelRaptorTrade\Models\ContractInternalApproval;
use Callcocam\LaravelRaptorTrade\Models\ContractReservation;
use Callcocam\LaravelRaptorTrade\Models\Map;
use Callcocam\LaravelRaptorTrade\Models\PurchaseIntention;
use Callcocam\LaravelRaptorTrade\Models\Reservation;
use Callcocam\LaravelRaptorTrade\Models\ReservationStoreMap;
use Callcocam\LaravelRaptorTrade\Models\Space;
use Callcocam\LaravelRaptorTrade\Models\SpaceCategory;
use Callcocam\LaravelRaptorTrade\Models\SpaceImage;
use Callcocam\LaravelRaptorTrade\Models\SpaceMapPlacement;
use Callcocam\LaravelRaptorTrade\Models\SpaceOccupation;
use Callcocam\LaravelRaptorTrade\Models\SpacePrefix;
use Callcocam\LaravelRaptorTrade\Models\SpaceType;
use Callcocam\LaravelRaptorTrade\Models\SpaceTypeLibraryItem;
use Callcocam\LaravelRaptorTrade\Models\StoreProfile;
use Callcocam\LaravelRaptorTrade\Models\WorkflowStep;
use Callcocam\LaravelRaptorTrade\Models\WorkflowStepTemplate;

/**
 * As tabelas `trade_*` vivem no banco do tenant. Um model do pacote que não
 * declare a conexão cai no banco padrão e a tela morre em produção, onde cada
 * tenant tem banco próprio — em SQLite de teste as duas conexões coincidem e o
 * erro passa despercebido. Por isso a checagem é da conexão, não da consulta.
 */
it('resolve os models do trade na conexão de tenant', function (string $model): void {
    expect((new $model)->getConnectionName())
        ->toBe(config('multitenancy.tenant_database_connection_name'));
})->with([
    Activity::class,
    ActivityAudit::class,
    ActivityType::class,
    Contract::class,
    ContractAttachment::class,
    ContractInternalApproval::class,
    ContractReservation::class,
    Map::class,
    PurchaseIntention::class,
    Reservation::class,
    ReservationStoreMap::class,
    Space::class,
    SpaceCategory::class,
    SpaceImage::class,
    SpaceMapPlacement::class,
    SpaceOccupation::class,
    SpacePrefix::class,
    SpaceType::class,
    SpaceTypeLibraryItem::class,
    StoreProfile::class,
    WorkflowStep::class,
    WorkflowStepTemplate::class,
]);
