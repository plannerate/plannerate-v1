<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Requests;

use Callcocam\LaravelRaptorPlannerate\Enums\ReoptimizationFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReoptimizationCadenceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reoptimization_enabled' => ['required', 'boolean'],
            // Habilitar sem frequência deixaria a gôndola marcada como "reotimizando" sem nunca
            // ser reprocessada — o pior estado possível, porque parece configurado.
            'reoptimization_frequency' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->boolean('reoptimization_enabled')),
                Rule::enum(ReoptimizationFrequency::class),
            ],
        ];
    }
}
