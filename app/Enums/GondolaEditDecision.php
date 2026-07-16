<?php

namespace App\Enums;

/**
 * Resultado da decisão de acesso ao editor de uma gôndola.
 *
 * Fonte única (App\Support\Workflow\GondolaEditGate) reutilizada tanto pela
 * página do editor quanto pelo middleware das APIs de escrita. Cada chamador
 * mapeia o caso para a resposta HTTP apropriada.
 */
enum GondolaEditDecision
{
    /** Pode editar (Kanban inativo/legado, ou execução ativa própria em etapa editável). */
    case Allowed;

    /** Gôndola sem execução de workflow ativa — não foi iniciada. */
    case NotStarted;

    /** Execução ativa iniciada por outro usuário — não é o responsável que iniciou. */
    case NotOwner;

    /** Execução ativa própria, porém a etapa atual é somente-leitura (access_mode = view). */
    case ReadOnlyStep;

    /** Usuário sem a permissão TENANT_GONDOLAS_UPDATE. */
    case Forbidden;

    public function allowsEditing(): bool
    {
        return $this === self::Allowed;
    }
}
