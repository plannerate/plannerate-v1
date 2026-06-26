<?php

namespace App\Enums;

/**
 * Modo de acesso de uma coluna/etapa do workflow.
 *
 * Define o que o usuário pode fazer com a gôndola enquanto a execução
 * estiver nessa etapa: abrir o editor para modificar o planograma (Edit)
 * ou apenas visualizar o PDF gerado (View).
 */
enum WorkflowAccessMode: string
{
    /** A etapa permite abrir o editor e modificar o planograma. */
    case Edit = 'edit';

    /** A etapa é somente leitura — apenas visualizar o PDF. */
    case View = 'view';

    /**
     * Indica se este modo permite abrir o editor para edição.
     */
    public function allowsEditing(): bool
    {
        return $this === self::Edit;
    }
}
