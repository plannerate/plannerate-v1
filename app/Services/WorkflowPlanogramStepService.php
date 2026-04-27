<?php

namespace App\Services;

use App\Models\Planogram;
use App\Models\WorkflowPlanogramStep;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class WorkflowPlanogramStepService
{
    /**
     * @return EloquentCollection<int, WorkflowPlanogramStep>
     */
    public function syncForPlanogram(Planogram $planogram): EloquentCollection
    {
        return DB::transaction(function () use ($planogram): EloquentCollection {
            $templates = WorkflowTemplate::query()
                ->where('status', 'published')
                ->with('suggestedUsers:id,name')
                ->orderBy('suggested_order')
                ->get();

            $existingByTemplateId = $planogram->workflowSteps()
                ->with('availableUsers:id,name')
                ->get()
                ->keyBy('workflow_template_id');

            foreach ($templates as $template) {
                if ($existingByTemplateId->has($template->id)) {
                    continue;
                }

                $step = WorkflowPlanogramStep::query()->create([
                    'planogram_id' => $planogram->id,
                    'workflow_template_id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'estimated_duration_days' => $template->estimated_duration_days,
                    'role_id' => $template->default_role_id,
                    'is_required' => (bool) $template->is_required_by_default,
                    'is_skipped' => false,
                    'status' => $template->status ?? 'draft',
                ]);

                $templateUserIds = $template->suggestedUsers
                    ->pluck('id')
                    ->map(fn (mixed $id): string => (string) $id)
                    ->values()
                    ->all();

                if ($templateUserIds !== []) {
                    $step->availableUsers()->sync($templateUserIds);
                }
            }

            return $this->settingsForPlanogram($planogram);
        });
    }

    /**
     * @param  array<int, array{step_id: string, is_required: bool, is_skipped: bool, estimated_duration_days?: int|null, user_ids?: array<int, string>}>  $stepsPayload
     * @return EloquentCollection<int, WorkflowPlanogramStep>
     */
    public function updateSettings(Planogram $planogram, array $stepsPayload): EloquentCollection
    {
        return DB::transaction(function () use ($planogram, $stepsPayload): EloquentCollection {
            $this->syncForPlanogram($planogram);

            $steps = $planogram->workflowSteps()
                ->get()
                ->keyBy('id');

            foreach ($stepsPayload as $payload) {
                /** @var WorkflowPlanogramStep|null $step */
                $step = $steps->get($payload['step_id']);

                if ($step === null) {
                    continue;
                }

                $step->update([
                    'is_required' => $payload['is_required'],
                    'is_skipped' => $payload['is_skipped'],
                    'estimated_duration_days' => $payload['estimated_duration_days'] ?? null,
                ]);

                $step->availableUsers()->sync($payload['user_ids'] ?? []);
            }

            return $this->settingsForPlanogram($planogram);
        });
    }

    /**
     * @return EloquentCollection<int, WorkflowPlanogramStep>
     */
    public function loadDefaultSettingsForPlanogram(Planogram $planogram): EloquentCollection
    {
        return DB::transaction(function () use ($planogram): EloquentCollection {
            $templates = WorkflowTemplate::query()
                ->where('status', 'published')
                ->with('suggestedUsers:id,name')
                ->orderBy('suggested_order')
                ->get();

            foreach ($templates as $template) {
                $step = WorkflowPlanogramStep::query()->updateOrCreate(
                    [
                        'planogram_id' => $planogram->id,
                        'workflow_template_id' => $template->id,
                    ],
                    [
                        'name' => $template->name,
                        'description' => $template->description,
                        'estimated_duration_days' => $template->estimated_duration_days,
                        'role_id' => $template->default_role_id,
                        'is_required' => (bool) $template->is_required_by_default,
                        'is_skipped' => false,
                        'status' => $template->status ?? 'draft',
                    ],
                );

                $templateUserIds = $template->suggestedUsers
                    ->pluck('id')
                    ->map(fn (mixed $id): string => (string) $id)
                    ->values()
                    ->all();

                $step->availableUsers()->sync($templateUserIds);
            }

            return $this->settingsForPlanogram($planogram);
        });
    }

    /**
     * @return EloquentCollection<int, WorkflowPlanogramStep>
     */
    public function settingsForPlanogram(Planogram $planogram): EloquentCollection
    {
        return $planogram->workflowSteps()
            ->with([
                'template:id,name,description,suggested_order,color,icon,status',
                'availableUsers:id,name',
            ])
            ->get()
            ->sortBy(function (WorkflowPlanogramStep $step): int {
                return $step->template?->suggested_order ?? PHP_INT_MAX;
            })
            ->values();
    }
}
