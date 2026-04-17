<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Form\Fields;

use Callcocam\LaravelRaptor\Support\Form\Columns\Types\SectionField;

class MapsField extends SectionField
{
    public function __construct($name, $label = null)
    {
        parent::__construct($name, $label);

        $this->component('form-field-maps');
        $this->valueUsing(function ($data, $model) {
            // O componente envia {image, regions}
            // image pode ser base64 (novo upload) ou null (usar existente)
            return $data;
        })
            ->defaultUsing(function ($model) {
                if ($model && $model->maps_integration) {
                    return $model->maps_integration;
                }

                return [
                    'image_url' => null,
                    'regions' => [],
                ];
            })
            ->visible(auth()->user()?->isAdmin() ?? false)
            ->collapsible();
    }
}
