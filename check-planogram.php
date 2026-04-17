<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = '01kfbv364cswqjxxj5k65b0b7z';

// Forçar banco específico
Config::set('database.connections.tenant.database', 'plannerate_coasgo');
DB::purge('tenant');

$p = \App\Models\Planogram::on('tenant')->find($id);

if ($p) {
    echo "Nome: {$p->name}\n";
    echo 'Status: '.($p->trashed() ? 'DELETADO' : 'ATIVO')."\n";
    echo "Tenant: {$p->tenant_id}\n";

    $c = \App\Models\Workflow\PlanogramWorkflowConfig::where('planogram_id', $id)->count();
    echo "Configs: {$c}\n\n";

    if ($c == 0 && ! $p->trashed()) {
        echo "Criando configs...\n\n";

        $templates = \App\Models\Workflow\WorkflowStepTemplate::where('is_active', true)
            ->orderBy('suggested_order')
            ->get();

        foreach ($templates as $template) {
            $config = \App\Models\Workflow\PlanogramWorkflowConfig::create([
                'tenant_id' => $p->tenant_id,
                'planogram_id' => $p->id,
                'workflow_step_template_id' => $template->id,
                'order' => $template->suggested_order,
                'is_active' => true,
                'estimated_duration_days' => $template->estimated_duration_days ?? 2,
                'is_required' => $template->is_required_by_default ?? true,
                'name' => $template->name,
                'description' => $template->description,
            ]);
            echo "✓ {$config->name}\n";
        }

        echo "\n✅ {$templates->count()} configs criados!\n";
    } elseif ($p->trashed()) {
        echo "⚠️ Planograma deletado!\n";
    } else {
        echo "✓ Planograma já tem configs!\n";
    }
} else {
    echo "❌ Planograma não existe!\n";
}
