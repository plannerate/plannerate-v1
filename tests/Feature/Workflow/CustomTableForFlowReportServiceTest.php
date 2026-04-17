<?php

namespace Tests\Feature\Workflow;

use Callcocam\LaravelRaptorFlow\Contracts\Reports\FlowReportTable;
use Callcocam\LaravelRaptorFlow\Support\Reports\FlowReportContext;

class CustomTableForFlowReportServiceTest implements FlowReportTable
{
    public static function key(): string
    {
        return 'custom_table';
    }

    public static function label(): string
    {
        return 'Tabela customizada';
    }

    public function build(FlowReportContext $context, array $options = []): array
    {
        return [
            ['value' => (int) $context->executionQuery()->count()],
        ];
    }
}
