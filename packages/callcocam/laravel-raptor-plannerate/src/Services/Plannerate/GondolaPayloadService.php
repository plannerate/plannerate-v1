<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Store;

class GondolaPayloadService
{
    /**
     * Monta payload otimizado para o editor da gôndola.
     *
     * Quando $workflowEnabled = false, carrega todas as gôndolas do planograma
     * sem filtrar por execução de workflow ou responsável atual.
     *
     * @return array<string, mixed>
     */
    public function buildEditorPayload(Gondola $gondola, bool $workflowEnabled = true): array
    {
        $recordData = [
            'id' => $gondola->id,
            'name' => $gondola->name,
            'slug' => $gondola->slug,
            'route_gondolas' => $gondola->route_gondolas,
            'scale_factor' => $gondola->scale_factor,
            'status' => $gondola->status,
            'num_modulos' => $gondola->num_modulos,
            'side' => $gondola->side,
            'alignment' => $gondola->alignment,
            'location' => $gondola->location,
            'flow' => $gondola->flow,
            'height' => $gondola->height,
            'width' => $gondola->width,
            'depth' => $gondola->depth,
            'planogram_id' => $gondola->planogram_id,
            'linked_map_gondola_id' => $gondola->linked_map_gondola_id,
            'linked_map_gondola_category' => $gondola->linked_map_gondola_category,
            'created_at' => $gondola->created_at?->toISOString(),
            'updated_at' => $gondola->updated_at?->toISOString(),
            'deleted_at' => $gondola->deleted_at?->toISOString(),
        ];

        if ($gondola->relationLoaded('sections')) {
            $recordData['sections'] = $gondola->sections->map(function ($section) use ($gondola) {
                return [
                    'id' => $section->id,
                    'gondola_id' => $section->gondola_id,
                    'name' => $section->name,
                    'code' => $section->code,
                    'width' => $section->width,
                    'height' => $section->height,
                    'num_shelves' => $section->num_shelves,
                    'base_height' => $section->base_height,
                    'base_depth' => $section->base_depth,
                    'base_width' => $section->base_width,
                    'cremalheira_width' => $section->cremalheira_width,
                    'ordering' => $section->ordering,
                    'hole_height' => $section->hole_height,
                    'hole_spacing' => $section->hole_spacing,
                    'hole_width' => $section->hole_width,
                    'settings' => $section->settings,
                    'alignment' => $section->alignment,
                    'deleted_at' => $section->deleted_at?->toISOString(),
                    'section_width' => ($section->width + 2) + ($section->cremalheira_width * $gondola->scale_factor),
                    'section_height' => $section->height * $gondola->scale_factor,
                    'shelves' => $section->relationLoaded('shelves') ? $section->shelves->map(function ($shelf) {
                        return [
                            'id' => $shelf->id,
                            'section_id' => $shelf->section_id,
                            'code' => $shelf->code,
                            'shelf_width' => $shelf->shelf_width,
                            'shelf_height' => $shelf->shelf_height,
                            'shelf_depth' => $shelf->shelf_depth,
                            'shelf_position' => $shelf->shelf_position,
                            'ordering' => $shelf->ordering,
                            'alignment' => $shelf->alignment,
                            'product_type' => $shelf->product_type,
                            'deleted_at' => $shelf->deleted_at?->toISOString(),
                            'segments' => $shelf->relationLoaded('segments') ? $shelf->segments->map(function ($segment) {
                                return [
                                    'id' => $segment->id,
                                    'shelf_id' => $segment->shelf_id,
                                    'layer_id' => $segment->layer_id,
                                    'width' => $segment->width,
                                    'height' => $segment->height,
                                    'depth' => $segment->depth,
                                    'position_x' => $segment->position_x,
                                    'position_y' => $segment->position_y,
                                    'facings' => $segment->facings,
                                    'quantity' => $segment->quantity,
                                    'ordering' => $segment->ordering,
                                    'position' => $segment->position,
                                    'deleted_at' => $segment->deleted_at?->toISOString(),
                                    'layer' => $segment->relationLoaded('layer') && $segment->layer ? [
                                        'id' => $segment->layer->id,
                                        'segment_id' => $segment->layer->segment_id,
                                        'product_id' => $segment->layer->product_id,
                                        'quantity' => $segment->layer->quantity,
                                        'height' => $segment->layer->height,
                                        'alignment' => $segment->layer->alignment,
                                        'spacing' => $segment->layer->spacing,
                                        'deleted_at' => $segment->layer->deleted_at?->toISOString(),
                                        'product' => $segment->layer->relationLoaded('product') && $segment->layer->product ? [
                                            'id' => $segment->layer->product->id,
                                            'name' => $segment->layer->product->name,
                                            'code' => $segment->layer->product->code,
                                            'ean' => $segment->layer->product->ean,
                                            'barcode' => $segment->layer->product->barcode,
                                            'image' => $segment->layer->product->image,
                                            'image_url' => $segment->layer->product->image_url,
                                            'width' => $segment->layer->product->width,
                                            'height' => $segment->layer->product->height,
                                            'depth' => $segment->layer->product->depth,
                                            'weight' => $segment->layer->product->weight,
                                            'brand' => $segment->layer->product->brand,
                                            'price' => $segment->layer->product->price,
                                            'status' => $segment->layer->product->status,
                                            'has_dimensions' => ($segment->layer->product->width > 0 && $segment->layer->product->height > 0 && $segment->layer->product->depth > 0),
                                        ] : null,
                                    ] : null,
                                ];
                            })->values()->all() : [],
                        ];
                    })->values()->all() : [],
                ];
            })->values()->all();
        }

        if ($planogram = $gondola->planogram) {
            $recordData['planogram'] = [
                'id' => $planogram->id,
                'tenant_id' => $planogram->tenant_id,
                'client_id' => $planogram->client_id,
                'store_id' => $planogram->store_id,
                'cluster_id' => $planogram->cluster_id,
                'name' => $planogram->name,
                'slug' => $planogram->slug,
                'type' => $planogram->type,
                'category_id' => $planogram->category_id,
                'status' => $planogram->status,
                'start_date' => $planogram->start_date,
                'end_date' => $planogram->end_date,
                'start_month' => $planogram->getStartMonthInput(),
                'end_month' => $planogram->getEndMonthInput(),
                'created_at' => $planogram->created_at?->toISOString(),
                'updated_at' => $planogram->updated_at?->toISOString(),
            ];

            if ($workflowEnabled) {
                $gondolas = $planogram->gondolasStarted
                    ->map(function ($relatedGondola) {
                        $execution = $relatedGondola->workflowExecution;
                        if ($execution && $execution->current_responsible_id === auth()->id()) {
                            return [
                                'id' => $relatedGondola->id,
                                'name' => $relatedGondola->name,
                                'route_gondolas' => $relatedGondola->route_gondolas,
                            ];
                        }

                        return null;
                    })
                    ->filter()
                    ->values()
                    ->all();
            } else {
                $gondolas = $planogram->gondolas
                    ->map(fn ($relatedGondola) => [
                        'id' => $relatedGondola->id,
                        'name' => $relatedGondola->name,
                        'route_gondolas' => $relatedGondola->route_gondolas,
                    ])
                    ->values()
                    ->all();
            }

            $recordData['planogram']['gondolas'] = $gondolas;

            if ($planogram->relationLoaded('category') && $planogram->category) {
                $category = $planogram->category;
                $recordData['planogram']['category'] = [
                    'id' => $category->id,
                    'tenant_id' => $category->tenant_id,
                    'category_id' => $category->category_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'level_name' => $category->level_name,
                    'codigo' => $category->codigo,
                    'status' => $category->status,
                    'nivel' => $category->nivel,
                    'hierarchy_position' => $category->hierarchy_position,
                    'full_path' => $category->full_path,
                    'hierarchy_path' => $category->hierarchy_path,
                    'is_placeholder' => $category->is_placeholder,
                ];
            }

            if ($planogram->store_id) {
                $store = Store::find($planogram->store_id);
                if ($store) {
                    $recordData['planogram']['store'] = [
                        'id' => $store->id,
                        'name' => $store->name,
                        'map_image_path' => $store->map_image_path,
                        'map_regions' => $store->map_regions,
                    ];
                }
            }
        }

        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $recordData['tenant'] = [
            'id' => $tenant?->id,
            'name' => $tenant?->name,
            'settings' => $tenant?->settings,
        ];

        return $recordData;
    }
}
