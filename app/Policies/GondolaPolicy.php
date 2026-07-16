<?php

namespace App\Policies;

use App\Models\Gondola;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

/**
 * Policy de `App\Models\Gondola` (a classe usada pela maioria do app, inclusive
 * o editor via `EditorPlanogramController::findGondolaOrFail`).
 *
 * IMPORTANTE — separação de responsabilidades:
 * - `view()` aqui é LEITURA (só permissão RBAC). NÃO aplica a regra "só a
 *   gôndola iniciada, e só por quem iniciou, pode ser editada".
 * - Essa regra de EDIÇÃO vive em `App\Support\Workflow\GondolaEditGate`, aplicada
 *   na página do editor e no middleware `gondola.editable` das APIs de escrita.
 * - Existe uma segunda policy `Callcocam\...\Policies\GondolaPolicy` (ligada à
 *   classe de modelo do pacote) cujo `view()` é mais restrito (bloqueio Kanban
 *   por responsável atual), usada só por 3 telas de leitura do pacote
 *   (relatório de geração, proposta de reotimização, banner de pendência).
 *
 * Ao autorizar, o Gate resolve a policy pela CLASSE CONCRETA da instância —
 * `authorize('view', $appGondola)` cai aqui (fraca); com o modelo do pacote,
 * cai na do pacote. Não confie no `view()` para gatear edição.
 */
class GondolaPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_VIEW_ANY);
    }

    public function view(User $user, Gondola $gondola): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_CREATE);
    }

    public function update(User $user, Gondola $gondola): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_UPDATE);
    }

    public function delete(User $user, Gondola $gondola): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_DELETE);
    }
}
