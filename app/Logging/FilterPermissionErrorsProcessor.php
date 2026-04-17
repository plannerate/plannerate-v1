<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class FilterPermissionErrorsProcessor implements ProcessorInterface
{
    /**
     * Lista de permissões padrão do Laravel Policy que devem ter
     * seus erros filtrados dos logs (são esperadas e não são realmente erros).
     */
    protected array $ignoredPermissions = [
        'viewAny',
        'view',
        'create',
        'update',
        'delete',
        'restore',
        'forceDelete',
        'replicate',
        'reorder',
    ];

    /**
     * Processa o log record e filtra erros de permissões conhecidas do Laravel Policy.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        // Verifica se é um erro de permission checking
        if (isset($record->message) && str_contains($record->message, 'Error checking permission')) {
            // Extrai o nome da permissão da mensagem
            foreach ($this->ignoredPermissions as $permission) {
                if (str_contains($record->message, "[$permission]")) {
                    // Transforma em nível DEBUG em vez de ERROR para não poluir logs
                    $record->level = \Monolog\Level::Debug;
                    $record->levelName = 'DEBUG';
                    break;
                }
            }

            // Também filtra permissões formato "resource.action" de listagem de menu
            if (preg_match('/\[([\w\.]+\.index)\]/', $record->message, $matches)) {
                $record->level = \Monolog\Level::Debug;
                $record->levelName = 'DEBUG';
            }
        }

        return $record;
    }
}
