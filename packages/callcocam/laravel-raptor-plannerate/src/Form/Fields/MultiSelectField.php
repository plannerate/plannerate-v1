<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Form\Fields;

use Callcocam\LaravelRaptor\Support\Form\Columns\Column;
use Callcocam\LaravelRaptor\Support\Form\Columns\Concerns\HasAutoComplete;
use Closure;

class MultiSelectField extends Column
{
    use HasAutoComplete;

    protected bool $isRequired = false;

    protected ?string $placeholder = null;

    protected bool $searchable = false;

    protected Closure|string|null $dependsOn = null;

    protected ?string $apiEndpoint = null;

    protected ?string $table = null;

    protected ?string $labelColumn = 'name';

    protected ?string $valueColumn = 'id';

    public function __construct(string $name, ?string $label = null)
    {
        parent::__construct($name, $label);
        $this->component('form-field-multiselect');
        $this->multiple = true;
        $this->setUp();
    }

    /**
     * Define um endpoint de API para carregar as opções
     */
    public function apiEndpoint(string $endpoint): self
    {
        $this->apiEndpoint = $endpoint;

        return $this;
    }

    /**
     * Retorna o endpoint da API configurado
     */
    public function getApiEndpoint(): ?string
    {
        return $this->apiEndpoint;
    }

    /**
     * Define a tabela para buscar opções via API
     */
    public function table(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Retorna a tabela configurada
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Define a coluna a ser usada como label
     */
    public function labelColumn(string $column): self
    {
        $this->labelColumn = $column;

        return $this;
    }

    /**
     * Retorna a coluna de label
     */
    public function getLabelColumn(): ?string
    {
        return $this->labelColumn;
    }

    /**
     * Define a coluna a ser usada como value
     */
    public function valueColumn(string $column): self
    {
        $this->valueColumn = $column;

        return $this;
    }

    /**
     * Retorna a coluna de value
     */
    public function getValueColumn(): ?string
    {
        return $this->valueColumn;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function dependsOn(Closure|string|null $dependsOn): self
    {
        $this->dependsOn = $dependsOn;

        return $this;
    }

    public function getDependsOn(): Closure|string|null
    {
        return $this->evaluate($this->dependsOn);
    }

    public function toArray($model = null): array
    {
        $optionsData = (object) [];

        // Processa as opções BRUTAS antes da normalização
        $optionKey = $this->getOptionKey();
        $optionLabel = $this->getOptionLabel();
        if (! empty($this->autoCompleteFields) || $optionKey || $optionLabel) {
            // Pega as opções brutas (antes de normalizar)
            $processed = $this->processOptionsForAutoComplete($this->getRawOptions());
            $optionsData = $processed['optionsData'];
        }

        $baseArray = array_merge(parent::toArray($model), [
            'searchable' => $this->searchable,
            'multiple' => true,
            'options' => $this->getOptions($model),
            'dependsOn' => $this->getDependsOn(),
            'apiEndpoint' => $this->getApiEndpoint(),
            'table' => $this->getTable(),
            'labelColumn' => $this->getLabelColumn(),
            'valueColumn' => $this->getValueColumn(),
        ]);
        $baseArray['optionsData'] = $optionsData;

        return array_merge($baseArray, $this->autoCompleteToArray());
    }
}
