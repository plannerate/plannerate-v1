<?php

namespace App\Enums;

/**
 * Tipo de evidência registrada na execução em loja.
 *
 * Define a categoria da foto/arquivo anexado pelo executor e dirige as regras
 * de obrigatoriedade (ex.: ao menos 1 foto geral + 1 por módulo).
 */
enum ExecutionEvidenceType: string
{
    /** Foto geral da gôndola executada. */
    case GeneralPhoto = 'general_photo';

    /** Foto de um módulo específico. */
    case Module = 'module';

    /** Foto de um produto específico. */
    case Product = 'product';

    /** Outro tipo de evidência. */
    case Other = 'other';
}
