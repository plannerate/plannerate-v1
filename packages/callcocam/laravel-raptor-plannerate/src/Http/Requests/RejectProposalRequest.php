<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Motivo é obrigatório ao rejeitar.
 *
 * Não é burocracia: uma rejeição sem motivo não ensina nada ao sistema nem a quem configurou o
 * template. "Ficou ruim" repetido por meses é o sinal de que a configuração precisa mudar — e é
 * o único registro que existe disso.
 */
class RejectProposalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => __('plannerate.reoptimization.errors.reason_required'),
        ];
    }
}
