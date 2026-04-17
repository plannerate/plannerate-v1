<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Jobs\Export;

use App\Services\Export\ProductExportService;

class ExportProductJob extends AbstractExportJob
{
    public int $timeout = 600;

    protected function getExportService(): string
    {
        return ProductExportService::class;
    }

    protected function jobTag(): string
    {
        return 'products';
    }
}
