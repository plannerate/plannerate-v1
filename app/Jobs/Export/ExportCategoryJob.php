<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Jobs\Export;

use App\Services\Export\CategoryExportService;

class ExportCategoryJob extends AbstractExportJob
{
    public int $timeout = 300;

    protected function getExportService(): string
    {
        return CategoryExportService::class;
    }

    protected function jobTag(): string
    {
        return 'categories';
    }
}
