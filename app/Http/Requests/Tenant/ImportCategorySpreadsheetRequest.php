<?php

namespace App\Http\Requests\Tenant;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportCategorySpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'spreadsheet' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:20480',
            ],
        ];
    }
}
