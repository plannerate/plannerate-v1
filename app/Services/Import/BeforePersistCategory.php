<?php

namespace App\Services\Import;

use Callcocam\LaravelRaptor\Support\Import\Contracts\BeforePersistHookInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Hook antes de persistir cada linha da importação hierárquica.
 * level_name e nivel são definidos por nível na hierarquia.
 */
class BeforePersistCategory implements BeforePersistHookInterface
{
    public function beforePersist(array $data, int $rowNumber, ?Model $existing): ?array
    {
        $data['status'] = 'published';

        // Se quiser testar sobrescrita, adicione outro campo
        $data['description'] = "Importado na linha {$rowNumber}";

        return $data;
    }
}
